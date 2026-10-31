<?php

namespace Database\Seeders;

use App\Models\Master\ApprovalMatrix;
use App\Models\Master\FiscalYear;
use App\Models\Role;
use App\Models\User;
use App\Models\ApprovalWorkflowAction;
use App\Models\ApprovalWorkflowRun;
use Illuminate\Database\Seeder;

class ApprovalMatrixSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([2025, 2026, 2027] as $year) {
            FiscalYear::updateOrCreate(['year' => $year], [
                'name' => 'Fiscal Year '.$year,
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
            'finance_officer' => Role::firstOrCreate(['slug' => 'finance-officer'], ['name' => 'Finance Officer']),
            'finance' => Role::firstOrCreate(['slug' => 'finance-manager'], ['name' => 'Finance Manager']),
        ];

        // Bind the seeded approval chain to the real login accounts and their
        // employee records so web/PWA previews show the actual approver.
        $approvers = collect([
            'abil' => 'abil.akbar@kaoemtelapak.org',
            'denny' => 'denny.bhatara@kaoemtelapak.org',
            'sarah' => 'sarah.megumi@kaoemtelapak.org',
            'riki' => 'riki.suryandi@kaoemtelapak.org',
            'winda' => 'winda.apriani@kaoemtelapak.org',
            'siti' => 'siti@contoh.org',
            'budi' => 'budi@contoh.org',
            'syaifani' => 'syaifani.havid@kaoemtelapak.org',
        ])->mapWithKeys(fn (string $email, string $key) => [$key => User::with('employee')->where('email', $email)->first()]);

        // Only generic rows are replaced. Donor/project/department-specific
        // overrides remain intact and continue to take precedence.
        $modules = [
            'cash_advance' => 'Cash Advance',
            'reimbursement' => 'Reimbursement',
            'settlement' => 'Expense Settlement',
            'procurement' => 'Purchase Request',
            'cba' => 'Competitive Bid Analysis',
            'po' => 'Purchase Order',
            'scn' => 'Supplier Contract Notification',
            'waiver' => 'Procurement Waiver / Justification',
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

        // Deliberately vary the seeded approvers by module for UAT. The
        // approval order remains level 1 -> level 2 -> level 3, while each
        // transaction family can be tested with a different chain.
        $moduleApprovers = [
            'cash_advance' => ['denny', 'siti', 'syaifani'],
            'reimbursement' => ['sarah', 'winda', 'budi'],
            'settlement' => ['denny', 'siti', 'syaifani'],
            'procurement' => ['denny', 'winda', 'syaifani'],
            'cba' => ['riki', 'winda', 'budi'],
            'po' => ['sarah', 'winda', 'syaifani'],
            'scn' => ['riki', 'siti', 'budi'],
            'waiver' => ['denny', 'winda', 'syaifani'],
            'ap' => ['riki', 'siti', 'syaifani'],
            'payment' => ['sarah', 'winda', 'syaifani'],
            'ar' => ['denny', 'siti', 'budi'],
            'journal' => ['sarah', 'winda', 'syaifani'],
            'bank_reconciliation' => ['denny', 'siti', 'syaifani'],
            'asset' => ['abil', 'winda', 'syaifani'],
            'asset_disposal' => ['denny', 'siti', 'syaifani'],
            'period_closing' => ['sarah', 'winda', 'syaifani'],
            'tax_transaction' => ['denny', 'siti', 'syaifani'],
            'timesheet' => ['abil', 'siti', 'syaifani'],
            'budget_reallocation' => ['denny', 'winda', 'syaifani'],
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
            [2, 5000001, $roles['finance_officer'], 'Finance Officer'],
            [3, 25000001, $roles['finance'], 'Finance Manager'],
        ];

        foreach ($years as $year => $fiscalYearId) {
            foreach ($modules as $module => $label) {
                foreach ($levels as [$level, $minAmount, $role, $title]) {
                    $approverKey = $moduleApprovers[$module][$level - 1] ?? null;
                    $approver = $approverKey ? $approvers->get($approverKey) : null;
                    ApprovalMatrix::create([
                        'fiscal_year_id' => $fiscalYearId,
                        'module' => $module,
                        'level' => $level,
                        'min_amount' => $minAmount,
                        'max_amount' => null,
                        'role_id' => $role->id,
                        'user_id' => $approver?->id,
                        'employee_id' => $approver?->employee_id,
                        'approver_title' => $title,
                        'is_conditional_project_manager' => $level === 1,
                        'description' => sprintf('%s level %d for amounts starting at IDR %s. The previous approval level must be completed first.', $label, $level, number_format($minAmount, 0, ',', '.')),
                        'is_active' => true,
                    ]);
                }
            }
        }

        // Expense delegation limits are separate from the normal module
        // thresholds. Abil is a project-only budget holder up to USD 10,000;
        // Denny covers amounts above that limit, including entity/internal KT
        // budgets, and the Board is added above USD 15,000.
        $this->seedExpenseDelegationMatrix($years, $roles, $approvers);
    }

    private function seedExpenseDelegationMatrix($years, array $roles, $approvers): void
    {
        $budgetHolder = Role::firstOrCreate(['slug' => 'budget-holder'], ['name' => 'Budget Holder']);
        $board = Role::firstOrCreate(['slug' => 'board-director'], ['name' => 'Board of Executives']);

        ApprovalMatrix::query()->where('module', 'expense')->delete();

        $abil = $approvers->get('abil');
        $denny = $approvers->get('denny');
        $financeOfficer = $approvers->get('winda');
        $financeManager = $approvers->get('syaifani');
        $rows = [
            [1, 0, 178000000, $budgetHolder, $abil, 'Project Budget Holder (Abil Ahmad Akbar)', 'Project expenses up to USD 10,000 / IDR 178,000,000.'],
            [1, 178000000.01, null, $budgetHolder, $denny, 'Program Manager & Operational (Denny Bhatara)', 'Project expenses above USD 10,000; Denny remains the budget holder for higher project values.'],
            [2, 0, null, $roles['finance_officer'], $financeOfficer, 'Finance Officer', 'Finance review is required after the Budget Holder stage.'],
            [3, 0, null, $roles['finance'], $financeManager, 'Finance Manager', 'Finance Manager verification is required before completion.'],
            [4, 267000000.01, null, $board, null, 'Board of Executives', 'Expenses above USD 15,000 / IDR 267,000,000 require Board approval.'],
        ];

        foreach ($years as $fiscalYearId) {
            foreach ($rows as [$level, $min, $max, $role, $user, $title, $description]) {
                ApprovalMatrix::create([
                    'fiscal_year_id' => $fiscalYearId,
                    'module' => 'expense',
                    'level' => $level,
                    'min_amount' => $min,
                    'max_amount' => $max,
                    'role_id' => $role->id,
                    'user_id' => $user?->id,
                    'employee_id' => $user?->employee_id,
                    'approver_title' => $title,
                    'is_conditional_project_manager' => $level === 1,
                    'description' => $description,
                    'is_active' => true,
                ]);
            }
        }
    }
}
