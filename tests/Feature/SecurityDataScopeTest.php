<?php

namespace Tests\Feature;

use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Asset\FixedAsset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityDataScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_request_staff_scope_blocks_other_users_records_and_spoofing(): void
    {
        [$userA, $userB, $budgetLine] = $this->usersWithRole(['procurement.pr.view', 'procurement.pr.create', 'procurement.pr.submit']);

        $created = $this->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/procurement/purchase-requests', [
                'request_date' => '2026-09-20',
                'requester_id' => $userB->id,
                'justification' => 'Scoped PR',
                'lines' => [
                    ['budget_line_id' => $budgetLine->id, 'item_description' => 'Supplies', 'quantity' => 1, 'unit_price' => 100],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.requester_id', $userA->id);

        $prId = $created->json('data.id');

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/v1/procurement/purchase-requests')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson("/api/v1/procurement/purchase-requests/{$prId}")
            ->assertForbidden();
    }

    public function test_expense_staff_scope_blocks_other_users_records_and_spoofing(): void
    {
        [$userA, $userB] = $this->usersWithRole(['expense.view', 'expense.create', 'expense.submit', 'dashboard.view', 'reports.view']);

        $created = $this->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/expenses/requests', [
                'requester_id' => $userB->id,
                'expense_type' => 'reimbursement',
                'request_date' => '2026-09-20',
                'description' => 'Scoped expense',
                'lines' => [
                    ['description' => 'Taxi', 'amount' => 50],
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('expense_requests', [
            'id' => $created->json('data.id'),
            'requester_id' => $userA->id,
        ]);

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/v1/expenses/requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/dashboard/overview?end_date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('scope', 'personal')
            ->assertJsonPath('summary.actual_expense', 50)
            ->assertJsonPath('recent_transactions.0.reference', $created->json('data.request_number'));

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/v1/reports/summary?end_date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('scope', 'personal')
            ->assertJsonPath('expense.total', 0)
            ->assertJsonPath('cash_bank.total_balance', 0);

        $this->getJson('/api/v1/reports/balance-sheet')
            ->assertOk()
            ->assertJsonPath('scope', 'personal')
            ->assertJsonCount(0, 'data.rows');

        $this->getJson('/api/v1/reports/forecast')
            ->assertOk()
            ->assertJsonPath('scope', 'personal')
            ->assertJsonPath('data.next_month_projection', 0);

        $this->getJson('/api/v1/donors/dashboard')
            ->assertOk()
            ->assertJsonPath('scope', 'personal')
            ->assertJsonCount(0, 'donors');
    }

    public function test_security_headers_are_applied(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_fixed_asset_scope_blocks_action_on_another_users_asset(): void
    {
        [$userA, $userB] = $this->usersWithRole(['asset.view', 'asset.create', 'asset.capitalize']);
        $asset = FixedAsset::create([
            'asset_code' => 'FA-SCOPE-001',
            'asset_name' => 'Scoped laptop',
            'acquisition_date' => '2026-09-20',
            'acquisition_cost' => 100,
            'net_book_value' => 100,
            'status' => 'draft',
            'created_by' => $userA->id,
        ]);

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/v1/assets/fixed-assets')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/assets/fixed-assets/{$asset->id}/capitalize")
            ->assertForbidden();
    }

    private function usersWithRole(array $permissions): array
    {
        $role = Role::create(['name' => 'Scoped Role '.uniqid(), 'slug' => 'scoped-role-'.uniqid()]);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]));
        }

        $userA = User::factory()->create(['role_id' => $role->id]);
        $userB = User::factory()->create(['role_id' => $role->id]);
        $category = BudgetCategory::create(['code' => 'SEC', 'name' => 'Security', 'is_active' => true]);
        $budgetLine = BudgetLine::create(['budget_category_id' => $category->id, 'line_code' => 'SEC-001', 'description' => 'Security budget', 'total_amount' => 1000, 'is_active' => true]);

        return [$userA, $userB, $budgetLine];
    }
}
