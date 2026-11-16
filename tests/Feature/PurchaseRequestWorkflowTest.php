<?php

namespace Tests\Feature;

use App\Models\Budget\BudgetCommitment;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_purchase_request_creates_budget_commitment(): void
    {
        [$user, $budgetLine] = $this->fixture([
            'procurement.pr.create',
            'procurement.pr.submit',
            'procurement.pr.approve',
            'procurement.pr.view',
        ]);

        $purchaseRequestId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/procurement/purchase-requests', [
                'request_date' => '2026-09-20',
                'required_date' => '2026-09-27',
                'country' => 'Indonesia - IDN',
                'office' => 'Bogor',
                'requester_name' => 'Rika Putri Andini',
                'requester_program' => 'EUDR Program',
                'requester_email' => 'rika.andini@kaoemtelapak.org',
                'requester_phone' => '081280922592',
                'requester_address' => 'Sempur 05 - Bogor Jawa Barat',
                'destination' => 'Bogor Office',
                'donor_name' => 'Program Donor',
                'delivery_method' => 'hand_carry',
                'requirements' => 'Local catering and on-time delivery',
                'preferred_incoterm' => 'DAP',
                'shipping_requirements' => 'Deliver before lunch',
                'justification' => 'Procure project materials',
                'lines' => [[
                    'budget_line_id' => $budgetLine->id,
                    'item_description' => 'Training materials',
                    'account_code' => '5100',
                    'budget_code' => 'PROC-001',
                    'source_of_fund' => 'Program Donor',
                    'unit' => 'package',
                    'currency' => 'IDR',
                    'quantity' => 2,
                    'unit_price' => 100,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.requester_email', 'rika.andini@kaoemtelapak.org')
            ->assertJsonPath('data.delivery_method', 'hand_carry')
            ->assertJsonPath('data.lines.0.account_code', '5100')
            ->assertJsonPath('data.lines.0.budget_code', 'PROC-001')
            ->assertJsonPath('data.lines.0.source_of_fund', 'Program Donor')
            ->assertJsonPath('data.lines.0.unit', 'package')
            ->assertJsonPath('data.lines.0.currency', 'IDR')
            ->json('data.id');

        $this->get("/api/v1/procurement/purchase-requests/{$purchaseRequestId}/pdf")
            ->assertOk()
            ->assertDownload();

        $this->postJson("/api/v1/procurement/purchase-requests/{$purchaseRequestId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->postJson("/api/v1/procurement/purchase-requests/{$purchaseRequestId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('budget_commitments', [
            'budget_line_id' => $budgetLine->id,
            'source_id' => $purchaseRequestId,
            'amount' => 200,
            'status' => 'open',
        ]);
    }

    public function test_purchase_request_approval_blocks_over_budget_commitment(): void
    {
        [$user, $budgetLine] = $this->fixture([
            'procurement.pr.create',
            'procurement.pr.submit',
            'procurement.pr.approve',
        ], 100);

        $purchaseRequestId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/procurement/purchase-requests', [
                'request_date' => '2026-09-20',
                'justification' => 'Over budget request',
                'lines' => [[
                    'budget_line_id' => $budgetLine->id,
                    'item_description' => 'Large equipment',
                    'quantity' => 2,
                    'unit_price' => 100,
                ]],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/procurement/purchase-requests/{$purchaseRequestId}/submit")
            ->assertOk();

        $this->postJson("/api/v1/procurement/purchase-requests/{$purchaseRequestId}/approve")
            ->assertUnprocessable();

        $this->assertSame(0, BudgetCommitment::query()->count());
    }

    private function fixture(array $permissions, float $budgetAmount = 1000): array
    {
        $role = Role::create(['name' => 'PR Role', 'slug' => 'pr-role']);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::create([
                'name' => $permission,
                'slug' => $permission,
            ]));
        }

        $user = User::factory()->create(['role_id' => $role->id]);
        $category = BudgetCategory::create([
            'code' => 'PROC',
            'name' => 'Procurement',
            'is_active' => true,
        ]);
        $budgetLine = BudgetLine::create([
            'budget_category_id' => $category->id,
            'line_code' => 'PROC-001',
            'description' => 'Procurement budget',
            'unit_price' => $budgetAmount,
            'quantity' => 1,
            'total_amount' => $budgetAmount,
            'is_active' => true,
        ]);

        return [$user, $budgetLine];
    }

    public function test_requester_cannot_assign_or_change_vendor_in_pr(): void
    {
        [$requester, $budgetLine] = $this->fixture([
            'procurement.pr.create',
            'procurement.pr.update',
            'procurement.pr.view',
        ]);

        $vendor = \App\Models\Master\Vendor::create([
            'name' => 'PT Test Vendor Mandiri',
            'type' => 'company',
        ]);

        // 1. Requester creates PR with vendor_id sent -> backend must ignore/clear vendor_id
        $response = $this->actingAs($requester, 'sanctum')
            ->postJson('/api/v1/procurement/purchase-requests', [
                'request_date' => '2026-09-20',
                'vendor_id' => $vendor->id,
                'lines' => [[
                    'budget_line_id' => $budgetLine->id,
                    'item_description' => 'ATK dan supplies',
                    'quantity' => 1,
                    'unit_price' => 50000,
                ]],
            ])
            ->assertCreated();

        $prId = $response->json('data.id');
        $this->assertNull($response->json('data.vendor_id'), 'Requester PR cannot set vendor_id upon PR creation.');

        // 2. Requester tries to update PR and supply vendor_id -> backend must retain null
        $updateResponse = $this->actingAs($requester, 'sanctum')
            ->putJson("/api/v1/procurement/purchase-requests/{$prId}", [
                'request_date' => '2026-09-21',
                'vendor_id' => $vendor->id,
                'lines' => [[
                    'budget_line_id' => $budgetLine->id,
                    'item_description' => 'ATK dan supplies updated',
                    'quantity' => 2,
                    'unit_price' => 50000,
                ]],
            ])
            ->assertOk();

        $this->assertNull($updateResponse->json('data.vendor_id'), 'Requester PR cannot modify vendor_id upon PR update.');
    }

    public function test_procurement_staff_can_assign_and_change_vendor_in_pr(): void
    {
        [$procurementStaff, $budgetLine] = $this->fixture([
            'procurement.pr.create',
            'procurement.pr.update',
            'procurement.pr.view',
            'procurement.po.create',
            'procurement.rfq.create',
        ]);

        $vendor1 = \App\Models\Master\Vendor::create([
            'name' => 'PT Vendor Rekanan A',
            'type' => 'company',
        ]);

        $vendor2 = \App\Models\Master\Vendor::create([
            'name' => 'PT Vendor Rekanan B',
            'type' => 'company',
        ]);

        // 1. Procurement staff creates PR with vendor_id -> allowed
        $response = $this->actingAs($procurementStaff, 'sanctum')
            ->postJson('/api/v1/procurement/purchase-requests', [
                'request_date' => '2026-09-20',
                'vendor_id' => $vendor1->id,
                'lines' => [[
                    'budget_line_id' => $budgetLine->id,
                    'item_description' => 'Hardware Server',
                    'quantity' => 1,
                    'unit_price' => 10000000,
                ]],
            ])
            ->assertCreated();

        $prId = $response->json('data.id');
        $this->assertEquals($vendor1->id, $response->json('data.vendor_id'));

        // 2. Procurement staff updates vendor_id to vendor2 -> allowed
        $updateResponse = $this->actingAs($procurementStaff, 'sanctum')
            ->putJson("/api/v1/procurement/purchase-requests/{$prId}", [
                'request_date' => '2026-09-20',
                'vendor_id' => $vendor2->id,
                'lines' => [[
                    'budget_line_id' => $budgetLine->id,
                    'item_description' => 'Hardware Server',
                    'quantity' => 1,
                    'unit_price' => 10000000,
                ]],
            ])
            ->assertOk();

        $this->assertEquals($vendor2->id, $updateResponse->json('data.vendor_id'));
    }
}
