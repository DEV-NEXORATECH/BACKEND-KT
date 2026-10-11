<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Master\AccountingPeriod;
use App\Models\Master\BankAccount;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Donor;
use App\Models\Master\ExpenseCategory;
use App\Models\Master\FiscalYear;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Program;
use App\Models\Master\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndDonorToReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_donor_grant_expense_payment_reaches_general_ledger_and_report_in_same_fiscal_year(): void
    {
        [$user, $fiscalYear, $donor, $grant, $program, $project, $budgetLine, $category, $bank] = $this->fixture();

        $expenseId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/expenses/requests', [
                'request_number' => 'E2E-2026-001',
                'fiscal_year_id' => $fiscalYear->id,
                'donor_id' => $donor->id,
                'grant_agreement_id' => $grant->id,
                'program_id' => $program->id,
                'project_id' => $project->id,
                'expense_type' => 'reimbursement',
                'request_date' => '2026-09-20',
                'currency_code' => 'IDR',
                'description' => 'End-to-end donor grant field expense',
                'attachments' => ['receipt-e2e.pdf'],
                'lines' => [[
                    'expense_category_id' => $category->id,
                    'budget_line_id' => $budgetLine->id,
                    'description' => 'Field transport',
                    'amount' => 150,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.fiscal_year_id', $fiscalYear->id)
            ->json('data.id');

        $this->postJson("/api/v1/expenses/requests/{$expenseId}/submit")->assertOk();
        $this->postJson("/api/v1/expenses/requests/{$expenseId}/verify")->assertOk();
        $this->postJson("/api/v1/expenses/requests/{$expenseId}/approve")->assertOk();
        $this->postJson("/api/v1/expenses/requests/{$expenseId}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');
        $this->postJson("/api/v1/expenses/requests/{$expenseId}/pay", [
            'bank_account_id' => $bank->id,
            'payment_date' => '2026-09-21',
            'amount' => 150,
            'reference' => 'E2E-PAY-001',
        ])->assertCreated()->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('expense_requests', [
            'id' => $expenseId,
            'fiscal_year_id' => $fiscalYear->id,
            'donor_id' => $donor->id,
            'project_id' => $project->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('journals', [
            'fiscal_year_id' => $fiscalYear->id,
            'reference' => 'E2E-2026-001',
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'fiscal_year_id' => $fiscalYear->id,
            'credit' => 150,
            'status' => 'matched',
        ]);

        $this->getJson("/api/v1/accounting/general-ledger?fiscal_year_id={$fiscalYear->id}&project_id={$project->id}")
            ->assertOk()
            ->assertJsonPath('totals.debit', 300)
            ->assertJsonPath('totals.credit', 300)
            ->assertJsonFragment(['reference' => 'E2E-2026-001']);

        $this->getJson("/api/v1/reports/summary?fiscal_year_id={$fiscalYear->id}&start_date=2026-01-01&end_date=2026-12-31")
            ->assertOk()
            ->assertJsonPath('financial_statement.totals_by_type.expense', 150)
            ->assertJsonPath('budget_vs_actual.totals.actual', 150);
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'E2E Finance', 'slug' => 'finance-manager']);
        foreach (['expense.view', 'expense.create', 'expense.submit', 'expense.verify', 'expense.approve', 'expense.post', 'expense.pay', 'accounting.view', 'reports.view'] as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $fiscalYear = FiscalYear::create(['year' => 2026, 'name' => 'Tahun Fiskal 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'is_active' => true]);
        AccountingPeriod::create(['fiscal_year_id' => $fiscalYear->id, 'period_number' => 9, 'name' => 'September 2026', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'open', 'is_active' => true]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true, 'fiscal_year_id' => $fiscalYear->id]);
        $cash = ChartOfAccount::create(['code' => '1000-E2E', 'name' => 'E2E Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true, 'fiscal_year_id' => $fiscalYear->id]);
        $expense = ChartOfAccount::create(['code' => '5100-E2E', 'name' => 'E2E Grant Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true, 'fiscal_year_id' => $fiscalYear->id]);
        ChartOfAccount::create(['code' => '2000-E2E', 'name' => 'E2E Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true, 'fiscal_year_id' => $fiscalYear->id]);
        $bank = BankAccount::create(['fiscal_year_id' => $fiscalYear->id, 'bank_name' => 'E2E Bank', 'account_number' => 'E2E-001', 'account_name' => 'E2E Main', 'currency_id' => $currency->id, 'gl_account_id' => $cash->id, 'is_active' => true]);
        $donor = Donor::create(['code' => 'DON-E2E', 'name' => 'E2E Donor', 'type' => 'institutional', 'default_currency_id' => $currency->id, 'fiscal_year_id' => $fiscalYear->id, 'status' => 'active', 'is_active' => true]);
        $grant = GrantAgreement::create(['fiscal_year_id' => $fiscalYear->id, 'grant_no' => 'GR-E2E-001', 'donor_id' => $donor->id, 'agreement_name' => 'E2E Grant', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'currency_id' => $currency->id, 'grant_value' => 1000, 'status' => 'active', 'is_active' => true]);
        $program = Program::create(['fiscal_year_id' => $fiscalYear->id, 'code' => 'PRG-E2E', 'name' => 'E2E Program', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $project = Project::create(['program_id' => $program->id, 'grant_agreement_id' => $grant->id, 'name' => 'E2E Project', 'code' => 'PRJ-E2E', 'budget_currency_id' => $currency->id, 'total_budget' => 1000, 'is_active' => true]);
        $project->fiscalYears()->attach($fiscalYear->id);
        $budgetCategory = BudgetCategory::create(['code' => 'E2E', 'name' => 'E2E Category', 'is_active' => true, 'fiscal_year_id' => $fiscalYear->id]);
        $budgetLine = BudgetLine::create(['fiscal_year_id' => $fiscalYear->id, 'grant_agreement_id' => $grant->id, 'project_id' => $project->id, 'budget_category_id' => $budgetCategory->id, 'line_code' => 'E2E-001', 'description' => 'E2E budget', 'currency_id' => $currency->id, 'total_amount' => 1000, 'gl_account_id' => $expense->id, 'is_active' => true]);
        $category = ExpenseCategory::create(['code' => 'E2E', 'name' => 'E2E Travel', 'default_gl_account_id' => $expense->id, 'is_active' => true, 'fiscal_year_id' => $fiscalYear->id]);

        return [$user, $fiscalYear, $donor, $grant, $program, $project, $budgetLine, $category, $bank];
    }
}
