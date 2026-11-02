<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Final guard for the trial dataset. It deliberately runs last so a clean
 * migrate:fresh --seed can never silently finish with an empty feature page.
 */
class TrialDataCoverageSeeder extends Seeder
{
    public function run(): void
    {
        $missing = [];

        foreach ([2025, 2026, 2027] as $year) {
            if (! DB::table('fiscal_years')->where('year', $year)->exists()) {
                $missing[] = "fiscal_years:{$year}";
                continue;
            }
            $fiscalYearId = DB::table('fiscal_years')->where('year', $year)->value('id');
            if (DB::table('accounting_periods')->where('fiscal_year_id', $fiscalYearId)->count() < 12) {
                $missing[] = "accounting_periods:{$year} (expected 12)";
            }
        }

        // These are operational screens, excluding RBAC, menus, pivots, and
        // global reference masters that are intentionally not year-scoped.
        $featureTables = [
            'programs', 'projects', 'activities', 'grant_agreements', 'budget_lines',
            'project_workplans', 'project_logframes', 'grant_reporting_deadlines',
            'forecast_entries', 'grant_disbursement_requests', 'expense_requests',
            'expense_request_lines', 'expense_request_fundings', 'approval_workflow_runs',
            'approval_workflow_actions', 'approval_workflow_events', 'purchase_requests',
            'purchase_request_lines', 'rfqs', 'rfq_vendors', 'vendor_quotations',
            'comparative_bid_analyses', 'purchase_orders', 'purchase_order_lines',
            'goods_receipts', 'goods_receipt_lines', 'supplier_invoices',
            'supplier_invoice_lines', 'supplier_contract_notifications', 'payments',
            'bank_transactions', 'journals', 'journal_lines', 'tax_transactions',
            'timesheet_entries', 'fixed_assets', 'asset_depreciations',
            'customer_invoices', 'customer_invoice_lines', 'membership_dues',
            'cash_advance_returns', 'cash_advance_reimbursements', 'budget_reallocations',
            'procurement_contracts', 'procurement_waivers', 'po_amendments',
            'vendor_evaluations', 'report_definitions', 'report_chart_types',
            'saved_reports', 'notifications', 'petty_cashes', 'holiday_calendars',
        ];

        foreach ($featureTables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->count() === 0) {
                $missing[] = $table;
            }
        }

        // Year-scoped transactional tables must have at least one row in each
        // year, otherwise changing the year selector still produces blanks.
        $yearScoped = [
            'budget_lines', 'expense_requests', 'purchase_requests', 'purchase_orders',
            'supplier_invoices', 'journals', 'tax_transactions', 'timesheet_entries',
            'fixed_assets', 'customer_invoices', 'forecast_entries',
            'grant_disbursement_requests', 'procurement_waivers',
        ];
        foreach ($yearScoped as $table) {
            if (! Schema::hasColumn($table, 'fiscal_year_id')) continue;
            foreach ([2025, 2026, 2027] as $year) {
                $fiscalYearId = DB::table('fiscal_years')->where('year', $year)->value('id');
                if ($fiscalYearId && DB::table($table)->where('fiscal_year_id', $fiscalYearId)->count() === 0) {
                    $missing[] = "{$table}:{$year}";
                }
            }
        }

        if ($missing !== []) {
            $message = "Trial data coverage failed:\n- ".implode("\n- ", $missing);
            $this->command?->error($message);
            throw new RuntimeException($message);
        }

        $this->command?->info('Trial data coverage passed: all required feature tables and fiscal years are populated.');
    }
}
