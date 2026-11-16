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
                'po_number' => 'PO-TEMPLATE-001',
                'po_date' => '2026-09-20',
                'required_delivery_date' => '2026-09-25',
                'delivery_method' => 'Hand carry',
                'payment_terms' => 'Net 30',
                'delivery_address' => 'Bogor Office',
                'delivery_contact_name' => 'Delivery Contact',
                'delivery_contact_email' => 'delivery@example.org',
                'supplier_company_name' => 'Supplier Example',
                'supplier_contact_name' => 'Supplier Contact',
                'supplier_email' => 'supplier@example.org',
                'supplier_phone' => '021123456',
                'supplier_fax' => '021654321',
                'supplier_mobile' => '0812000000',
                'supplier_address' => 'Supplier Address',
                'procurement_contact_name' => 'Procurement Contact',
                'procurement_contact_email' => 'procurement@kaoemtelapak.org',
                'procurement_address' => 'Kaoem Telapak Bogor',
                'other_tax' => 10,
                'other_charges' => 5,
                'checked_by' => 'Ziadatunnisa Ilmi Latifa',
                'checked_by_title' => 'Campaigner',
                'budget_holder_name' => 'Abu Meridian',
                'budget_holder_title' => 'Campaign Leader',
                'supplier_acceptance_name' => 'Supplier Signatory',
                'supplier_acceptance_title' => 'Director',
                'terms' => 'Net 30',
            ])
            ->assertCreated()
            ->assertJsonPath('data.po_number', 'PO-TEMPLATE-001')
            ->assertJsonPath('data.supplier_company_name', 'Supplier Example')
            ->assertJsonPath('data.delivery_contact_name', 'Delivery Contact')
            ->assertJsonPath('data.checked_by_title', 'Campaigner')
            ->assertJsonPath('data.subtotal_amount', '200.00')
            ->assertJsonPath('data.total_amount', '215.00')
            ->assertJsonPath('data.lines.0.budget_code', 'PROC-001')
            ->assertJsonPath('data.lines.0.pr_number', 'PR-001')
            ->assertJsonPath('data.lines.0.pr_line_item_no', 1)
            ->assertJsonPath('data.lines.0.unit', 'package')
            ->assertJsonPath('data.lines.0.currency', 'IDR')
            ->json('data');

        $this->postJson("/api/v1/procurement/purchase-orders/{$po['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $poLineId = $po['lines'][0]['id'];
        $grn = $this->postJson("/api/v1/procurement/purchase-orders/{$po['id']}/goods-receipts", [
            'grn_number' => 'GRNKT001',
            'receipt_date' => '2026-09-21',
            'supplier_waybill_number' => 'DN-001',
            'receiving_location' => 'Bogor Office',
            'acceptable_for_payment' => true,
            'remarks' => 'Goods received in good condition.',
            'receipt_by_name' => 'Storekeeper',
            'receipt_by_position' => 'Supply Chain Officer',
            'requester_name' => 'Programme Requester',
            'requester_position' => 'Campaign Lead',
            'lines' => [[
                'purchase_order_line_id' => $poLineId,
                'received_quantity' => 2,
                'quality_check' => 'Acceptable',
                'comment' => 'New and in good condition',
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.grn_number', 'GRNKT001')
            ->assertJsonPath('data.pr_number', 'PR-001')
            ->assertJsonPath('data.supplier_waybill_number', 'DN-001')
            ->assertJsonPath('data.acceptable_for_payment', true)
            ->assertJsonPath('data.lines.0.quantity_expected', '2.00')
            ->assertJsonPath('data.lines.0.received_quantity', '2.00')
            ->assertJsonPath('data.lines.0.quality_check', 'Acceptable')
            ->assertJsonPath('data.lines.0.comment', 'New and in good condition')
            ->json('data');

        $this->getJson('/api/v1/procurement/goods-receipts')
            ->assertOk()
            ->assertJsonPath('data.0.grn_number', 'GRNKT001')
            ->assertJsonPath('data.0.lines.0.item_description', 'Materials');

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

        $this->putJson("/api/v1/procurement/goods-receipts/{$grn['id']}", ['acceptable_for_payment' => false])
            ->assertOk()
            ->assertJsonPath('data.acceptable_for_payment', false);

        $this->postJson("/api/v1/procurement/supplier-invoices/{$invoice['id']}/three-way-match")
            ->assertOk()
            ->assertJsonPath('data.match_status', 'mismatch');

        $this->putJson("/api/v1/procurement/goods-receipts/{$grn['id']}", ['acceptable_for_payment' => true])
            ->assertOk()
            ->assertJsonPath('data.acceptable_for_payment', true);

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
            'procurement.grn.view',
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
            'budget_code' => 'PROC-001',
            'unit' => 'package',
            'currency' => 'IDR',
            'item_description' => 'Materials',
            'quantity' => 2,
            'unit_price' => 100,
            'total_amount' => 200,
            'line_order' => 1,
        ]);

        return [$user, $purchaseRequest];
    }
}
