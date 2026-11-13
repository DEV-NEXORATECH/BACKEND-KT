<?php

namespace Tests\Feature;

use App\Models\Master\BankAccount;
use App\Models\Master\BudgetCategory;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Donor;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetLineRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_budget_is_scoped_and_persisted_by_integrated_relation_number(): void
    {
        $role = Role::create(['name' => 'Budget Manager', 'slug' => 'budget-manager']);
        $role->permissions()->attach(Permission::create([
            'name' => 'Manage master data',
            'slug' => 'master-data.manage',
        ]));
        $user = User::factory()->create(['role_id' => $role->id]);

        $currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'is_base_currency' => true,
            'is_active' => true,
        ]);
        $donor = Donor::create([
            'code' => 'DON-BUD',
            'name' => 'Budget Donor',
            'type' => 'institutional',
            'default_currency_id' => $currency->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        $bankCoa = ChartOfAccount::create(['code' => '1101', 'name' => 'Bank IDR', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $expenseCoa = ChartOfAccount::create(['code' => '5101', 'name' => 'Program Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create([
            'bank_name' => 'Bank Budget',
            'account_number' => 'BUD-001',
            'account_name' => 'Budget Operating Account',
            'currency_id' => $currency->id,
            'gl_account_id' => $bankCoa->id,
            'is_active' => true,
        ]);
        $grant = GrantAgreement::create([
            'grant_no' => 'GR-BUD-001',
            'relation_no' => 'KT-REL-BUD-A',
            'donor_id' => $donor->id,
            'agreement_name' => 'Budget Grant',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'currency_id' => $currency->id,
            'grant_value' => 1000000,
            'bank_account_id' => $bank->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        $projectA = Project::create([
            'code' => 'P-BUD-A',
            'name' => 'Project Budget A',
            'grant_agreement_id' => $grant->id,
            'relation_no' => 'KT-REL-BUD-A',
            'bank_account_id' => $bank->id,
            'is_active' => true,
        ]);
        $projectB = Project::create([
            'code' => 'P-BUD-B',
            'name' => 'Project Budget B',
            'relation_no' => 'KT-REL-BUD-B',
            'is_active' => true,
        ]);
        $category = BudgetCategory::create([
            'code' => 'CAT-BUD',
            'name' => 'Budget Category',
            'is_active' => true,
        ]);

        $payload = [
            'project_id' => $projectA->id,
            'grant_agreement_id' => $grant->id,
            'relation_no' => 'KT-REL-BUD-A',
            'budget_category_id' => $category->id,
            'gl_account_id' => $expenseCoa->id,
            'line_code' => 'BL-REL-001',
            'description' => 'Integrated project budget',
            'unit_price' => 100000,
            'quantity' => 2,
            'total_amount' => 200000,
            'is_active' => true,
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/master/budget-lines', $payload)
            ->assertCreated()
            ->assertJsonPath('data.relation_no', 'KT-REL-BUD-A')
            ->assertJsonPath('data.gl_account.id', $expenseCoa->id)
            ->assertJsonPath('data.bank_account.id', $bank->id)
            ->assertJsonPath('data.bank_gl_account.id', $bankCoa->id)
            ->assertJsonPath('data.bank_link_status', 'linked');

        $this->getJson("/api/v1/master/budget-lines?project_id={$projectA->id}&relation_no=KT-REL-BUD-A")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.relation_no', 'KT-REL-BUD-A');

        $this->getJson("/api/v1/master/budget-lines?project_id={$projectB->id}&relation_no=KT-REL-BUD-A")
            ->assertStatus(422)
            ->assertJsonValidationErrors('relation_no');

        $this->postJson('/api/v1/master/budget-lines', array_merge($payload, [
            'line_code' => 'BL-REL-INVALID',
            'relation_no' => 'KT-REL-BUD-B',
        ]))->assertStatus(422)->assertJsonValidationErrors('relation_no');
    }
}
