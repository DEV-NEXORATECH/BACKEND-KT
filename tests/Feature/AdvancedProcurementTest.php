<?php

namespace Tests\Feature;

use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Vendor;
use App\Models\Permission;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedProcurementTest extends TestCase
{
    use RefreshDatabase;

    public function test_rfq_cba_and_po_from_selected_vendor(): void
    {
        [$user, $pr, $vendorA, $vendorB] = $this->fixture();

        $rfqResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/procurement/purchase-requests/{$pr->id}/rfqs", [
                'rfq_date' => '2026-09-20',
                'submission_deadline' => '2026-09-25',
                'vendor_ids' => [$vendorA->id, $vendorB->id],
                'terms' => 'Submit best offer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'issued');

        $rfqId = $rfqResponse->json('data.id');

        $this->postJson("/api/v1/procurement/rfqs/{$rfqId}/quotations", [
            'vendor_id' => $vendorA->id,
            'quotation_number' => 'Q-A',
            'quotation_date' => '2026-09-21',
            'total_amount' => 950,
            'technical_score' => 85,
            'financial_score' => 95,
            'delivery_terms' => '7 days',
        ])->assertCreated()->assertJsonPath('data.total_score', '91.00');

        $quotationB = $this->postJson("/api/v1/procurement/rfqs/{$rfqId}/quotations", [
            'vendor_id' => $vendorB->id,
            'quotation_number' => 'Q-B',
            'quotation_date' => '2026-09-21',
            'total_amount' => 980,
            'technical_score' => 95,
            'financial_score' => 90,
            'delivery_terms' => '5 days',
        ])->assertCreated()->json('data.id');

        $cbaId = $this->postJson("/api/v1/procurement/rfqs/{$rfqId}/cba", [
            'analysis_date' => '2026-09-22',
            'selected_quotation_id' => $quotationB,
            'selection_reason' => 'Best technical score and fastest delivery.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.selected_vendor_id', $vendorB->id)
            ->json('data.id');

        $this->postJson("/api/v1/procurement/cba/{$cbaId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $poId = $this->postJson("/api/v1/procurement/cba/{$cbaId}/purchase-orders", [
            'po_date' => '2026-09-23',
            'contract_number' => 'CON-001',
            'contract_date' => '2026-09-23',
        ])
            ->assertCreated()
            ->assertJsonPath('data.vendor_name', $vendorB->name)
            ->assertJsonPath('data.contract_number', 'CON-001')
            ->json('data.id');

        $this->postJson("/api/v1/procurement/purchase-orders/{$poId}/approve")->assertOk();
        $amendment = $this->postJson("/api/v1/procurement/purchase-orders/{$poId}/amendments", ['terms' => 'Delivery within 14 days', 'reason' => 'Supplier delivery schedule revised.'])
            ->assertCreated()->assertJsonPath('data.status', 'pending_approval');
        $this->postJson('/api/v1/procurement/po-amendments/'.$amendment->json('data.id').'/approve')->assertOk();
        $this->postJson("/api/v1/procurement/purchase-orders/{$poId}/vendor-evaluations", ['quality_score' => 5, 'delivery_score' => 4, 'price_score' => 4, 'service_score' => 5])
            ->assertCreated()->assertJsonPath('data.overall_score', '4.5');
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'Advanced Procurement Role', 'slug' => 'advanced-procurement-role']);
        foreach (['procurement.rfq.create', 'procurement.cba.create', 'procurement.cba.approve', 'procurement.po.create', 'procurement.po.approve'] as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $vendorA = Vendor::create(['code' => 'V-A', 'name' => 'Vendor A', 'type' => 'company', 'is_active' => true]);
        $vendorB = Vendor::create(['code' => 'V-B', 'name' => 'Vendor B', 'type' => 'company', 'is_active' => true]);
        $category = BudgetCategory::create(['code' => 'CAT', 'name' => 'Category', 'is_active' => true]);
        $account = ChartOfAccount::create(['code' => '5100', 'name' => 'Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $budgetLine = BudgetLine::create(['budget_category_id' => $category->id, 'line_code' => 'BL-1', 'description' => 'Budget', 'total_amount' => 5000, 'gl_account_id' => $account->id, 'is_active' => true]);
        $pr = PurchaseRequest::create(['pr_number' => 'PR-ADV-001', 'request_date' => '2026-09-20', 'requester_id' => $user->id, 'justification' => 'Advanced procurement', 'status' => 'approved']);
        $pr->lines()->create(['budget_line_id' => $budgetLine->id, 'item_description' => 'Equipment', 'quantity' => 1, 'unit_price' => 1000, 'total_amount' => 1000, 'line_order' => 1]);

        return [$user, $pr, $vendorA, $vendorB];
    }
}
