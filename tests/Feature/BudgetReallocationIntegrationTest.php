<?php

namespace Tests\Feature;

use App\Models\Master\BankAccount;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Donor;
use App\Models\Master\FiscalYear;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetReallocationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reallocation_keeps_relation_project_grant_coa_and_bank_links_and_updates_budget_atomically(): void
    {
        [$user, $from, $to, $other] = $this->fixture();

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/budget/reallocations', [
            'from_budget_line_id' => $from->id,
            'to_budget_line_id' => $to->id,
            'amount' => 200,
            'reason' => 'Move budget to the approved activity.',
        ])->assertCreated()
            ->assertJsonPath('data.project_id', $from->project_id)
            ->assertJsonPath('data.from_line.relation_no', 'KT-REL-REALLOC')
            ->assertJsonPath('data.from_line.project.name', 'Reallocation Project')
            ->assertJsonPath('data.from_line.grant_agreement.grant_no', 'GRT-REALLOC')
            ->assertJsonPath('data.from_line.grant_agreement.donor.name', 'Reallocation Donor')
            ->assertJsonPath('data.from_line.gl_account.code', '5100')
            ->assertJsonPath('data.from_line.project.bank_account.account_number', 'REALLOC-001')
            ->assertJsonPath('data.from_line.project.bank_account.gl_account.code', '1000');

        $id = $created->json('data.id');

        $this->getJson('/api/v1/budget/reallocations?fiscal_year_id='.$from->fiscal_year_id.'&relation_no=KT-REL-REALLOC')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.to_line.gl_account.code', '5200');

        $this->postJson("/api/v1/budget/reallocations/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->postJson("/api/v1/budget/reallocations/{$id}/approve", ['notes' => 'Approved'])->assertOk()->assertJsonPath('data.status', 'applied');

        $this->assertSame(800.0, (float) $from->fresh()->total_amount);
        $this->assertSame(500.0, (float) $to->fresh()->total_amount);

        $this->postJson('/api/v1/budget/reallocations', [
            'from_budget_line_id' => $from->id,
            'to_budget_line_id' => $other->id,
            'amount' => 50,
            'reason' => 'Invalid cross-project transfer.',
        ])->assertUnprocessable()->assertJsonValidationErrors('to_budget_line_id');
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        foreach (['budget.view', 'budget.update', 'budget.approve'] as $slug) {
            $role->permissions()->attach(Permission::create(['name' => $slug, 'slug' => $slug]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);

        $year = FiscalYear::create(['year' => 2026, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'is_active' => true]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $bankCoa = ChartOfAccount::create(['code' => '1000', 'name' => 'Project Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $fromCoa = ChartOfAccount::create(['code' => '5100', 'name' => 'Workshop Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $toCoa = ChartOfAccount::create(['code' => '5200', 'name' => 'Travel Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Bank Test', 'account_number' => 'REALLOC-001', 'account_name' => 'Project Account', 'currency_id' => $currency->id, 'gl_account_id' => $bankCoa->id, 'is_active' => true]);
        $donor = Donor::create(['code' => 'D-REALLOC', 'name' => 'Reallocation Donor', 'type' => 'institutional', 'default_currency_id' => $currency->id, 'is_active' => true]);
        $grant = GrantAgreement::create(['fiscal_year_id' => $year->id, 'grant_no' => 'GRT-REALLOC', 'relation_no' => 'KT-REL-REALLOC', 'donor_id' => $donor->id, 'agreement_name' => 'Reallocation Grant', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'currency_id' => $currency->id, 'grant_value' => 5000, 'bank_account_id' => $bank->id, 'status' => 'active', 'is_active' => true]);
        $project = Project::create(['code' => 'PRJ-REALLOC', 'name' => 'Reallocation Project', 'relation_no' => 'KT-REL-REALLOC', 'grant_agreement_id' => $grant->id, 'bank_account_id' => $bank->id, 'is_active' => true]);
        $project->fiscalYears()->attach($year->id);
        $otherProject = Project::create(['code' => 'PRJ-OTHER', 'name' => 'Other Project', 'relation_no' => 'KT-REL-OTHER', 'grant_agreement_id' => $grant->id, 'is_active' => true]);
        $otherProject->fiscalYears()->attach($year->id);
        $category = BudgetCategory::create(['code' => 'OPS', 'name' => 'Operations', 'is_active' => true]);

        $base = ['fiscal_year_id' => $year->id, 'grant_agreement_id' => $grant->id, 'relation_no' => 'KT-REL-REALLOC', 'budget_category_id' => $category->id, 'currency_id' => $currency->id, 'exchange_rate' => 1, 'is_active' => true];
        $from = BudgetLine::create([...$base, 'project_id' => $project->id, 'line_code' => 'BL-FROM', 'description' => 'Source line', 'total_amount' => 1000, 'gl_account_id' => $fromCoa->id]);
        $to = BudgetLine::create([...$base, 'project_id' => $project->id, 'line_code' => 'BL-TO', 'description' => 'Destination line', 'total_amount' => 300, 'gl_account_id' => $toCoa->id]);
        $other = BudgetLine::create([...$base, 'project_id' => $otherProject->id, 'relation_no' => 'KT-REL-OTHER', 'line_code' => 'BL-OTHER', 'description' => 'Other project line', 'total_amount' => 500, 'gl_account_id' => $toCoa->id]);

        return [$user, $from, $to, $other];
    }
}
