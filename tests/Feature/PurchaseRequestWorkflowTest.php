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
                'justification' => 'Procure project materials',
                'lines' => [[
                    'budget_line_id' => $budgetLine->id,
                    'item_description' => 'Training materials',
                    'quantity' => 2,
                    'unit_price' => 100,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

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
}
