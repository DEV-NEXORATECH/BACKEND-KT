<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Budget\BudgetCommitment;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Donor;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Program;
use App\Models\Master\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonorGrantRealDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_grant_dashboard_and_reporting_use_real_budget_commitment_and_posted_actual(): void
    {
        [$user, $budgetLine, $expenseAccount, $liabilityAccount, $donor, $grant, $project] = $this->fixture();

        $journal = Journal::create([
            'journal_number' => 'JV-GRANT-REAL-001',
            'journal_date' => '2026-09-20',
            'journal_type' => 'manual',
            'reference' => 'REAL-GRANT-ACTUAL',
            'description' => 'Posted grant project expense',
            'status' => 'posted',
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $journal->lines()->create(['account_id' => $expenseAccount->id, 'donor_id' => $donor->id, 'project_id' => $project->id, 'budget_line_id' => $budgetLine->id, 'debit' => 300, 'credit' => 0, 'line_order' => 1]);
        $journal->lines()->create(['account_id' => $liabilityAccount->id, 'project_id' => $project->id, 'budget_line_id' => $budgetLine->id, 'debit' => 0, 'credit' => 300, 'line_order' => 2]);

        BudgetCommitment::create([
            'budget_line_id' => $budgetLine->id,
            'source_type' => 'purchase_request',
            'source_id' => 9001,
            'reference' => 'PR-GRANT-REAL-001',
            'amount' => 200,
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/donors/dashboard?start_date=2026-09-01&end_date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('summary.total_donors', 1)
            ->assertJsonPath('summary.total_grants', 1)
            ->assertJsonPath('summary.total_budget', 1000)
            ->assertJsonPath('summary.total_actual', 300)
            ->assertJsonPath('summary.total_committed', 200)
            ->assertJsonPath('summary.total_available', 500)
            ->assertJsonPath('donors.0.actual', 300)
            ->assertJsonPath('donors.0.committed', 200)
            ->assertJsonPath('grants.0.available', 500);

        $this->getJson('/api/v1/reports/grant-reporting?start_date=2026-09-01&end_date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('summary.approved_budget', 1000)
            ->assertJsonPath('summary.actual', 300)
            ->assertJsonPath('summary.committed', 200)
            ->assertJsonPath('summary.available', 500)
            ->assertJsonPath('bva.0.project', 'Project Real')
            ->assertJsonPath('bva.0.actual', 300)
            ->assertJsonPath('bva.0.committed', 200)
            ->assertJsonPath('expenditures.0.reference', 'REAL-GRANT-ACTUAL');
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'Finance', 'slug' => 'finance']);
        $role->permissions()->attach(Permission::create(['name' => 'Reports View', 'slug' => 'reports.view']));
        $user = User::factory()->create(['role_id' => $role->id]);

        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $expenseAccount = ChartOfAccount::create(['code' => '5200', 'name' => 'Grant Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $liabilityAccount = ChartOfAccount::create(['code' => '2100', 'name' => 'Grant Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $donor = Donor::create(['code' => 'DON-REAL', 'name' => 'Real Donor', 'type' => 'institutional', 'default_currency_id' => $currency->id, 'is_active' => true]);
        $grant = GrantAgreement::create(['grant_no' => 'GR-REAL-001', 'donor_id' => $donor->id, 'agreement_name' => 'Real Grant Agreement', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'currency_id' => $currency->id, 'grant_value' => 1200, 'status' => 'active', 'is_active' => true]);
        $program = Program::create(['code' => 'PRG-REAL', 'name' => 'Program Real', 'is_active' => true]);
        $project = Project::create(['code' => 'PROJ-REAL', 'program_id' => $program->id, 'grant_agreement_id' => $grant->id, 'name' => 'Project Real', 'budget_currency_id' => $currency->id, 'total_budget' => 1000, 'is_active' => true]);
        $category = BudgetCategory::create(['code' => 'GRANT', 'name' => 'Grant Program', 'is_active' => true]);
        $budgetLine = BudgetLine::create(['grant_agreement_id' => $grant->id, 'project_id' => $project->id, 'budget_category_id' => $category->id, 'line_code' => 'GR-REAL-001', 'description' => 'Real grant activity', 'currency_id' => $currency->id, 'total_amount' => 1000, 'gl_account_id' => $expenseAccount->id, 'is_active' => true]);

        return [$user, $budgetLine, $expenseAccount, $liabilityAccount, $donor, $grant, $project];
    }
}
