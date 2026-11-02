<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'menus',
        'grant_reporting_deadlines',
        'holiday_calendars',
        'project_logframes',
        'project_workplans',
        'notifications',
        'project_assignments',
        'journal_lines',
        'recurring_journal_lines',
        'purchase_request_lines',
        'goods_receipt_lines',
        'supplier_invoice_lines',
        'customer_invoice_lines',
        'recurring_journals',
        'expense_request_lines',
        'purchase_order_lines',
        'bank_transactions',
        'forecast_entries',
        'grant_forecast_items',
        'budget_commitments',
        'budget_reallocations',
        'vendor_quotations',
        'vendor_evaluations',
        'po_amendments',
        'supplier_contract_notifications',
        'asset_depreciations',
        'saved_reports',
        'approval_workflow_actions',
        'approval_workflow_events',
        'approval_workflow_runs',
        'application_settings',
        'permissions',
        'roles',
        'report_definitions',
        'report_chart_types',
        'audit_logs',
        'cash_advance_reimbursements',
        'cash_advance_returns',
        'expense_request_fundings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
