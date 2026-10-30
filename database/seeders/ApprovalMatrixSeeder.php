<?php

namespace Database\Seeders;

use App\Models\Master\ApprovalMatrix;
use App\Models\Master\FiscalYear;
use App\Models\Role;
use App\Models\ApprovalWorkflowAction;
use App\Models\ApprovalWorkflowRun;
use Illuminate\Database\Seeder;

class ApprovalMatrixSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([2025, 2026, 2027] as $year) {
            FiscalYear::updateOrCreate(['year' => $year], [
                'name' => 'Tahun Fiskal '.$year,
                'calendar_code' => 'JAN-DEC',
                'start_month' => 1,
                'start_date' => $year.'-01-01',
                'end_date' => $year.'-12-31',
                'status' => 'open',
                'is_active' => $year === 2026,
            ]);
        }
        $years = FiscalYear::query()->whereIn('year', [2025, 2026, 2027])->pluck('id', 'year');
        if ($years->isEmpty()) return;

        $roles = [
            'project' => Role::firstOrCreate(['slug' => 'project-manager'], ['name' => 'Project Manager']),
            'finance' => Role::firstOrCreate(['slug' => 'finance-manager'], ['name' => 'Finance Manager']),
            'director' => Role::firstOrCreate(['slug' => 'executive-director'], ['name' => 'Executive Director']),
            'board' => Role::firstOrCreate(['slug' => 'board-director'], ['name' => 'Board of Trustees']),
        ];

        // Only generic rows are replaced. Donor/project/department-specific
        // overrides remain intact and continue to take precedence.
        $modules = [
            'expense' => 'Expense Request',
            'cash_advance' => 'Cash Advance',
            'reimbursement' => 'Reimbursement',
            'settlement' => 'Expense Settlement',
            'procurement' => 'Purchase Request',
            'cba' => 'Competitive Bid Analysis',
            'po' => 'Purchase Order',
            'scn' => 'Supplier Contract Notification',
            'ap' => 'Supplier Invoice / Accounts Payable',
            'payment' => 'Payment',
            'ar' => 'Customer Invoice / Accounts Receivable',
            'journal' => 'Journal',
            'bank_reconciliation' => 'Bank Reconciliation',
            'asset' => 'Fixed Asset Capitalization',
            'asset_disposal' => 'Fixed Asset Disposal',
            'period_closing' => 'Accounting Period Closing',
            'tax_transaction' => 'Tax Transaction',
            'timesheet' => 'Timesheet',
            'budget_reallocation' => 'Budget Reallocation',
        ];

        ApprovalMatrix::query()
            ->whereIn('module', array_keys($modules))
            ->whereNull('project_id')->whereNull('donor_id')->whereNull('department_id')
            ->delete();

        // Remove duplicate actions created by older matrix seeders. One
        // document must have one action per level; otherwise the same level
        // would appear repeatedly and require approval multiple times.
        $runIds = ApprovalWorkflowRun::query()->whereIn('module', array_keys($modules))->pluck('id');
        ApprovalWorkflowAction::query()->whereIn('approval_workflow_run_id', $runIds)->get()
            ->groupBy(fn ($action) => $action->approval_workflow_run_id.'-'.$action->level)
            ->each(function ($actions) {
                $actions->sortBy('id')->skip(1)->each->delete();
            });

        $levels = [
            [1, 0, $roles['project'], 'Project Manager / Budget Holder'],
            [2, 5000001, $roles['finance'], 'Finance Manager'],
            [3, 25000001, $roles['director'], 'Executive Director'],
            [4, 100000001, $roles['board'], 'Board of Trustees'],
        ];

        foreach ($years as $year => $fiscalYearId) {
            foreach ($modules as $module => $label) {
                foreach ($levels as [$level, $minAmount, $role, $title]) {
                    ApprovalMatrix::create([
                        'fiscal_year_id' => $fiscalYearId,
                        'module' => $module,
                        'level' => $level,
                        'min_amount' => $minAmount,
                        'max_amount' => null,
                        'role_id' => $role->id,
                        'approver_title' => $title,
                        'is_conditional_project_manager' => $level === 1,
                        'description' => sprintf('%s level %d untuk nominal mulai Rp %s. Level approval sebelumnya wajib selesai lebih dahulu.', $label, $level, number_format($minAmount, 0, ',', '.')),
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
