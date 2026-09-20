<?php

namespace Tests\Feature;

use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Permission;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_po_grn_invoice_can_run_three_way_match(): void
    {
        [$user, $purchaseRequest] = $this->fixture();

        $po = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/procurement/purchase-requests/{$purchaseRequest->id}/purchase-orders", [
                'po_date' => '2026-09-20',
                'terms' => 'Net 30',
            ])
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/v1/procurement/purchase-orders/{$po['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $poLineId = $po['lines'][0]['id'];
        $grn = $this->postJson("/api/v1/procurement/purchase-orders/{$po['id']}/goods-receipts", [
            'receipt_date' => '2026-09-21',
            'lines' => [[
                'purchase_order_line_id' => $poLineId,
                'received_quantity' => 2,
            ]],
        ])
            ->assertCreated()
            ->json('data');

        $invoice = $this->postJson("/api/v1/procurement/purchase-orders/{$po['id']}/supplier-invoices", [
            'goods_receipt_id' => $grn['id'],
            'invoice_number' => 'INV-001',
            'invoice_date' => '2026-09-22',
            'lines' => [[
                'purchase_order_line_id' => $poLineId,
                'item_description' => 'Materials',
                'quantity' => 2,
                'unit_price' => 100,
            ]],
        ])
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/v1/procurement/supplier-invoices/{$invoice['id']}/three-way-match")
            ->assertOk()
            ->assertJsonPath('data.match_status', 'matched')
            ->assertJsonPath('data.po_total', 200)
            ->assertJsonPath('data.invoice_total', 200);
    }

    private function fixture(): array
    {
        $permissions = [
            'procurement.po.create',
            'procurement.po.approve',
            'procurement.grn.create',
            'procurement.invoice.create',
            'procurement.invoice.match',
        ];
        $role = Role::create(['name' => 'Fulfillment Role', 'slug' => 'fulfillment-role']);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $category = BudgetCategory::create(['code' => 'PROC', 'name' => 'Procurement', 'is_active' => true]);
        $budgetLine = BudgetLine::create([
            'budget_category_id' => $category->id,
            'line_code' => 'PROC-001',
            'description' => 'Procurement budget',
            'unit_price' => 1000,
            'quantity' => 1,
            'total_amount' => 1000,
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::create([
            'pr_number' => 'PR-001',
            'request_date' => '2026-09-20',
            'requester_id' => $user->id,
            'justification' => 'Approved procurement',
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        $purchaseRequest->lines()->create([
            'budget_line_id' => $budgetLine->id,
            'item_description' => 'Materials',
            'quantity' => 2,
            'unit_price' => 100,
            'total_amount' => 200,
            'line_order' => 1,
        ]);

        return [$user, $purchaseRequest];
    }
}
