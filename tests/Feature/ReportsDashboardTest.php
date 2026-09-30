<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Master\BankAccount;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Permission;
use App\Models\Procurement\SupplierInvoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_reports_use_real_posted_data(): void
    {
        [$user, $budgetLine, $expenseAccount, $liabilityAccount, $bank] = $this->fixture();

        $journal = Journal::create([
            'journal_number' => 'JV-RPT-001',
            'journal_date' => '2026-09-20',
            'journal_type' => 'manual',
            'reference' => 'REAL-POSTED',
            'description' => 'Real posted project expense',
            'status' => 'posted',
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $journal->lines()->create(['account_id' => $expenseAccount->id, 'budget_line_id' => $budgetLine->id, 'debit' => 300, 'credit' => 0, 'line_order' => 1]);
        $journal->lines()->create(['account_id' => $liabilityAccount->id, 'budget_line_id' => $budgetLine->id, 'debit' => 0, 'credit' => 300, 'line_order' => 2]);

        SupplierInvoice::create([
            'invoice_number' => 'INV-RPT-001',
            'invoice_date' => '2026-09-20',
            'due_date' => '2026-09-21',
            'status' => 'posted',
            'match_status' => 'matched',
            'total_amount' => 300,
            'paid_amount' => 100,
        ]);

        $bank->transactions()->create([
            'transaction_date' => '2026-09-22',
            'reference' => 'BNK-RPT-001',
            'description' => 'Payment',
            'debit' => 0,
            'credit' => 100,
            'status' => 'matched',
        ]);

        $dashboardResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/overview?end_date=2026-09-30');

        $dashboardResponse->assertOk()
            ->assertJsonPath('summary.approved_budget', 1000)
            ->assertJsonPath('summary.actual_expense', 300)
            ->assertJsonPath('summary.open_ap', 200)
            ->assertJsonPath('summary.operating_cash', -100)
            ->assertJsonPath('recent_transactions.0.reference', 'REAL-POSTED');

        $this->getJson('/api/v1/reports/summary?end_date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('budget_vs_actual.totals.actual', 300)
            ->assertJsonPath('financial_statement.totals_by_type.expense', 300)
            ->assertJsonPath('financial_statement.totals_by_type.liability', 300)
            ->assertJsonPath('ap_aging.buckets.1_30', 200)
            ->assertJsonPath('cash_bank.total_balance', -100);

        $this->get('/api/v1/reports/profit-loss/pdf?end_date=2026-09-30')
            ->assertOk()
            ->assertDownload();
        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $this->get('/api/v1/reports/export/profit-loss/'.$format.'?end_date=2026-09-30')
                ->assertOk()
                ->assertDownload();
        }

        $this->post('/api/v1/reports/custom/export', [
            'report_type' => 'project',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ])->assertOk()
            ->assertDownload();

        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'module' => 'reports', 'action' => 'EXPORT']);
    }

    public function test_profit_loss_preview_and_csv_xlsx_pdf_exports_have_consistent_rows_and_period_filter(): void
    {
        [$user, $budgetLine, $expenseAccount, $liabilityAccount] = $this->fixture();
        $journal = Journal::create(['journal_number' => 'JV-CONSISTENT', 'journal_date' => '2026-09-20', 'journal_type' => 'manual', 'reference' => 'CONSISTENT', 'description' => 'Consistent report', 'status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);
        $journal->lines()->create(['account_id' => $expenseAccount->id, 'budget_line_id' => $budgetLine->id, 'debit' => 300, 'credit' => 0, 'line_order' => 1]);
        $journal->lines()->create(['account_id' => $liabilityAccount->id, 'budget_line_id' => $budgetLine->id, 'debit' => 0, 'credit' => 300, 'line_order' => 2]);
        $query = '?start_date=2026-09-01&end_date=2026-09-30';
        $preview = $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports/summary'.$query)->assertOk()->json('profit_loss.rows');
        $previewRows = count($preview); $previewDebit = round(array_sum(array_map(fn ($row) => (float) $row['debit'], $preview)), 2);
        $csv = $this->get('/api/v1/reports/export/profit-loss/csv'.$query)->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvRows = array_values(array_filter(array_map('str_getcsv', preg_split('/\r\n|\r|\n/', $csv->streamedContent()))));
        $this->assertNotEmpty($csvRows); $exportRows = count($csvRows) - 1; $this->assertGreaterThanOrEqual($previewRows, $exportRows);
        $xlsx = $this->get('/api/v1/reports/export/profit-loss/xlsx'.$query)->assertOk();
        $this->assertStringContainsString('spreadsheetml', strtolower((string) $xlsx->headers->get('content-type'))); $xlsxBytes = file_get_contents($xlsx->baseResponse->getFile()->getPathname()); $this->assertNotEmpty($xlsxBytes); $this->assertSame('PK', substr($xlsxBytes, 0, 2));
        $pdf = $this->get('/api/v1/reports/export/profit-loss/pdf'.$query)->assertOk();
        $this->assertStringContainsString('pdf', strtolower((string) $pdf->headers->get('content-type'))); $this->assertNotEmpty($pdf->getContent());
        $this->assertGreaterThan(0, $previewDebit);
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'Finance', 'slug' => 'finance']);
        foreach (['dashboard.view', 'reports.view', 'reports.export'] as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);

        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $cashAccount = ChartOfAccount::create(['code' => '1000', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $liabilityAccount = ChartOfAccount::create(['code' => '2000', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $expenseAccount = ChartOfAccount::create(['code' => '5100', 'name' => 'Program Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Test Bank', 'account_number' => '001', 'account_name' => 'Main', 'currency_id' => $currency->id, 'gl_account_id' => $cashAccount->id, 'is_active' => true]);
        $category = BudgetCategory::create(['code' => 'OPS', 'name' => 'Operations', 'is_active' => true]);
        $budgetLine = BudgetLine::create(['budget_category_id' => $category->id, 'line_code' => 'OPS-001', 'description' => 'Ops', 'total_amount' => 1000, 'gl_account_id' => $expenseAccount->id, 'is_active' => true]);

        return [$user, $budgetLine, $expenseAccount, $liabilityAccount, $bank];
    }
}
