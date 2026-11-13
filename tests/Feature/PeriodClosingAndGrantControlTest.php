<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Accounting\PrepaidExpense;
use App\Models\Asset\FixedAsset;
use App\Models\Finance\BankTransaction;
use App\Models\Master\AccountingPeriod;
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
use App\Models\Procurement\SupplierInvoice;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PeriodClosingAndGrantControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_period_rejects_backdated_transaction(): void
    {
        $year = FiscalYear::create([
            'year' => 2026, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'status' => 'open', 'is_active' => true,
        ]);
        AccountingPeriod::create([
            'fiscal_year_id' => $year->id, 'period_number' => 9, 'name' => 'September 2026',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'closed', 'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);
        app(AccountingPeriodService::class)->ensureOpen('2026-09-15', 'journal_date');
    }

    public function test_open_period_allows_current_transaction_date(): void
    {
        $year = FiscalYear::create([
            'year' => 2026, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'status' => 'open', 'is_active' => true,
        ]);
        AccountingPeriod::create([
            'fiscal_year_id' => $year->id, 'period_number' => 10, 'name' => 'October 2026',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'open', 'is_active' => true,
        ]);

        app(AccountingPeriodService::class)->ensureOpen('2026-10-15', 'journal_date');
        $this->assertTrue(true);
    }

    public function test_closing_readiness_integrates_accounting_donor_grant_and_bank(): void
    {
        $role = Role::create(['name' => 'Period Manager', 'slug' => 'period-manager']);
        foreach (['master-data.view', 'settings.manage'] as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $year = FiscalYear::create(['year' => 2026, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'is_active' => true]);
        $period = AccountingPeriod::create(['fiscal_year_id' => $year->id, 'period_number' => 9, 'name' => 'September 2026', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'status' => 'open', 'is_active' => true]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $bankCoa = ChartOfAccount::create(['fiscal_year_id' => $year->id, 'code' => '1101', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $expenseCoa = ChartOfAccount::create(['fiscal_year_id' => $year->id, 'code' => '5101', 'name' => 'Grant Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create(['fiscal_year_id' => $year->id, 'bank_name' => 'Grant Bank', 'account_number' => 'PERIOD-001', 'account_name' => 'Grant Account', 'currency_id' => $currency->id, 'gl_account_id' => $bankCoa->id, 'is_active' => true]);
        $donor = Donor::create(['code' => 'DON-PERIOD', 'name' => 'Period Donor', 'type' => 'institutional', 'default_currency_id' => $currency->id, 'status' => 'active', 'is_active' => true]);
        $grant = GrantAgreement::create(['fiscal_year_id' => $year->id, 'grant_no' => 'GR-PERIOD', 'relation_no' => 'KT-REL-PERIOD', 'donor_id' => $donor->id, 'agreement_name' => 'Period Grant', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'currency_id' => $currency->id, 'grant_value' => 1000, 'bank_account_id' => $bank->id, 'status' => 'active', 'is_active' => true]);
        $project = Project::create(['code' => 'PRJ-PERIOD', 'name' => 'Period Project', 'relation_no' => 'KT-REL-PERIOD', 'grant_agreement_id' => $grant->id, 'bank_account_id' => $bank->id, 'is_active' => true]);
        $category = BudgetCategory::create(['code' => 'PERIOD', 'name' => 'Period Cost', 'is_active' => true]);
        $budgetLine = BudgetLine::create(['fiscal_year_id' => $year->id, 'grant_agreement_id' => $grant->id, 'project_id' => $project->id, 'relation_no' => 'KT-REL-PERIOD', 'budget_category_id' => $category->id, 'line_code' => 'BL-PERIOD', 'description' => 'Period expense', 'total_amount' => 1000, 'gl_account_id' => $expenseCoa->id, 'is_active' => true]);

        $posted = Journal::withoutGlobalScope('fiscal_year_context')->create(['fiscal_year_id' => $year->id, 'journal_number' => 'JV-PERIOD-POSTED', 'journal_date' => '2026-09-10', 'journal_type' => 'manual', 'description' => 'Grant expense', 'status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);
        $posted->lines()->create(['account_id' => $expenseCoa->id, 'donor_id' => $donor->id, 'grant_agreement_id' => $grant->id, 'project_id' => $project->id, 'budget_line_id' => $budgetLine->id, 'relation_no' => 'KT-REL-PERIOD', 'line_description' => 'Grant expense', 'debit' => 100, 'credit' => 0, 'line_order' => 1]);
        $draft = Journal::withoutGlobalScope('fiscal_year_context')->create(['fiscal_year_id' => $year->id, 'journal_number' => 'JV-PERIOD-DRAFT', 'journal_date' => '2026-09-20', 'journal_type' => 'manual', 'description' => 'Pending journal', 'status' => 'draft', 'created_by' => $user->id]);
        $transaction = BankTransaction::create(['fiscal_year_id' => $year->id, 'bank_account_id' => $bank->id, 'transaction_date' => '2026-09-10', 'reference' => 'BANK-PERIOD', 'description' => 'Pending reconciliation', 'debit' => 0, 'credit' => 100, 'status' => 'unmatched']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/master/accounting-periods/'.$period->id.'/readiness')
            ->assertOk()
            ->assertJsonPath('data.can_close', false)
            ->assertJsonPath('data.accounting.unposted_journals', 1)
            ->assertJsonPath('data.donor_grant.donors', 1)
            ->assertJsonPath('data.donor_grant.grants', 1)
            ->assertJsonPath('data.donor_grant.relation_numbers.0', 'KT-REL-PERIOD')
            ->assertJsonPath('data.bank.unreconciled_transactions', 1);

        $this->postJson('/api/v1/master/accounting-periods/'.$period->id.'/close')
            ->assertUnprocessable()
            ->assertJsonPath('data.can_close', false);

        $draft->update(['status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);
        $transaction->update(['status' => 'matched']);

        $this->getJson('/api/v1/master/accounting-periods/'.$period->id.'/readiness')
            ->assertOk()
            ->assertJsonPath('data.can_close', true)
            ->assertJsonPath('data.accounting.unposted_journals', 0)
            ->assertJsonPath('data.bank.unreconciled_transactions', 0);

        $pendingInvoice = SupplierInvoice::create(['fiscal_year_id' => $year->id, 'invoice_number' => 'AP-CLOSE-001', 'invoice_date' => '2026-09-22', 'status' => 'draft', 'total_amount' => 50]);
        $this->getJson('/api/v1/master/accounting-periods/'.$period->id.'/readiness')
            ->assertOk()->assertJsonPath('data.can_close', false)->assertJsonPath('data.subledgers.accounts_payable', 1);
        $pendingInvoice->update(['status' => 'posted']);

        $draftAsset = FixedAsset::create(['fiscal_year_id' => $year->id, 'asset_code' => 'FA-CLOSE-001', 'asset_name' => 'Closing Asset', 'acquisition_date' => '2026-09-23', 'acquisition_cost' => 100, 'net_book_value' => 100, 'status' => 'draft']);
        $this->getJson('/api/v1/master/accounting-periods/'.$period->id.'/readiness')
            ->assertOk()->assertJsonPath('data.can_close', false)->assertJsonPath('data.assets_prepaids.draft_assets', 1);
        $draftAsset->update(['status' => 'disposed']);

        $prepaid = PrepaidExpense::create(['reference' => 'PRE-CLOSE-001', 'description' => 'Closing prepaid', 'start_date' => '2026-09-01', 'periods' => 12, 'total_amount' => 120, 'prepaid_account_id' => $bankCoa->id, 'expense_account_id' => $expenseCoa->id, 'project_id' => $project->id, 'grant_agreement_id' => $grant->id, 'donor_id' => $donor->id, 'relation_no' => 'KT-REL-PERIOD', 'status' => 'active']);
        $this->getJson('/api/v1/master/accounting-periods/'.$period->id.'/readiness')
            ->assertOk()->assertJsonPath('data.can_close', false)->assertJsonPath('data.assets_prepaids.unrecognized_prepaids', 1);
        $prepaid->update(['status' => 'on_hold']);

        $this->getJson('/api/v1/master/accounting-periods/'.$period->id.'/readiness')
            ->assertOk()->assertJsonPath('data.can_close', true)->assertJsonPath('data.subledgers.pending_total', 0)->assertJsonPath('data.assets_prepaids.pending_total', 0);
    }
}
