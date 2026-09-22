<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalLine;
use App\Models\Asset\FixedAsset;
use App\Models\Budget\BudgetCommitment;
use App\Models\Expense\ExpenseRequest;
use App\Models\Finance\BankTransaction;
use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\Payment;
use App\Models\Master\ChartOfAccount;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierInvoice;
use App\Services\Budget\BudgetMonitoringService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsDashboardController extends Controller
{
    public function dashboard(Request $request, BudgetMonitoringService $budgetService): JsonResponse
    {
        $period = $this->period($request);

        // Apply filters
        $filters = $request->only([
            'fiscal_year_id', 'start_date', 'end_date', 'organization_id',
            'office_location_id', 'department_id', 'program_id', 'project_id',
            'donor_id', 'grant_agreement_id', 'currency_id'
        ]);

        $budgetRows = $budgetService->summary(array_filter($filters));
        $postedLines = $this->postedLines($period['start'], $period['end'], $request->input('project_id'));
        $budgetRows = $this->applyPeriodActuals($budgetRows->all(), $postedLines);
        $budgetTotals = $this->budgetTotals($budgetRows);
        $recentTransactions = $this->recentTransactions($period['start'], $period['end']);

        $pendingApprovals = PurchaseRequest::query()->whereIn('status', ['submitted', 'pending_approval'])->count()
            + ExpenseRequest::query()->whereIn('status', ['submitted', 'approved'])->count()
            + Journal::query()->whereIn('status', ['submitted', 'reviewed'])->count()
            + SupplierInvoice::query()->whereIn('status', ['matched', 'posted', 'partially_paid'])->count();

        $activeDonorsCount = \App\Models\Master\Donor::query()->where('is_active', true)->count();
        $activeGrantsCount = \App\Models\Master\GrantAgreement::query()->where('is_active', true)->count();
        $activeProgramsCount = \App\Models\Master\Program::query()->where('is_active', true)->count();
        $activeProjectsCount = \App\Models\Master\Project::query()->where('is_active', true)->count();

        $totalIncome = (float) $postedLines
            ->filter(fn (JournalLine $line) => $line->account?->account_type === 'revenue')
            ->sum(fn (JournalLine $line) => (float) $line->credit - (float) $line->debit);

        $totalExpense = (float) $postedLines
            ->filter(fn (JournalLine $line) => $line->account?->account_type === 'expense')
            ->sum(fn (JournalLine $line) => (float) $line->debit - (float) $line->credit);

        return response()->json([
            'success' => true,
            'period' => $period['label'],
            'summary' => [
                'total_budget' => $budgetTotals['approved_budget'],
                'approved_budget' => $budgetTotals['approved_budget'],
                'total_actual' => $budgetTotals['actual'],
                'actual_expense' => $budgetTotals['actual'],
                'commitment' => $budgetTotals['committed'],
                'remaining_budget' => $budgetTotals['available'],
                'available_budget' => $budgetTotals['available'],
                'utilization_percent' => $budgetTotals['utilization_percent'],
                'total_income' => round($totalIncome, 2),
                'total_expense' => round($totalExpense, 2),
                'operating_cash' => $this->cashPosition($period['start'], $period['end']),
                'cash_bank_balance' => $this->cashPosition($period['start'], $period['end']),
                'open_ap' => $this->openAp(),
                'outstanding_payable' => $this->openAp(),
                'open_ar' => $this->openAr(),
                'outstanding_receivable' => $this->openAr(),
                'active_donors' => $activeDonorsCount,
                'active_grants' => $activeGrantsCount,
                'active_programs' => $activeProgramsCount,
                'active_projects' => $activeProjectsCount,
                'pending_approvals' => $pendingApprovals,
                'paid_amount' => (float) Payment::query()
                    ->when($period['start'], fn (Builder $query) => $query->whereDate('payment_date', '>=', $period['start']))
                    ->when($period['end'], fn (Builder $query) => $query->whereDate('payment_date', '<=', $period['end']))
                    ->sum('amount'),
            ],
            'charts' => [
                'budget_vs_actual' => $this->monthlyBudgetVsActual($budgetTotals['approved_budget'], $postedLines),
                'income_vs_expense' => $this->monthlyIncomeVsExpense($postedLines),
                'donor_utilization' => $this->donorUtilization($budgetRows),
                'project_utilization' => $this->projectUtilization($budgetRows),
                'expense_by_category' => $this->expenseByCategory($postedLines),
                'cash_position_by_bank' => $this->cashBank($period['start'], $period['end']),
                'approval_status' => $this->approvalStatusBreakdown(),
            ],
            'pending_actions' => $this->pendingActions(),
            'recent_transactions' => $recentTransactions,
        ]);
    }

    private function monthlyIncomeVsExpense($postedLines): array
    {
        $months = collect(range(5, 0))->map(fn (int $monthsAgo) => CarbonImmutable::now()->subMonths($monthsAgo)->startOfMonth());

        return $months->map(function ($month) use ($postedLines) {
            $income = $postedLines
                ->filter(fn (JournalLine $line) => $line->journal?->journal_date?->format('Y-m') === $month->format('Y-m'))
                ->sum(fn (JournalLine $line) => $line->account?->account_type === 'revenue' ? ((float) $line->credit - (float) $line->debit) : 0);

            $expense = $postedLines
                ->filter(fn (JournalLine $line) => $line->journal?->journal_date?->format('Y-m') === $month->format('Y-m'))
                ->sum(fn (JournalLine $line) => $line->account?->account_type === 'expense' ? ((float) $line->debit - (float) $line->credit) : 0);

            return [
                'month' => $month->format('M'),
                'income' => round($income, 2),
                'expense' => round($expense, 2),
            ];
        })->all();
    }

    private function projectUtilization(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn (array $row) => $row['project']['name'] ?? 'Unassigned Project')
            ->map(function ($items, string $project) {
                $approved = (float) $items->sum('approved_budget');
                $used = (float) $items->sum(fn (array $row) => $row['actual'] + $row['committed']);

                return [
                    'name' => $project,
                    'approved_budget' => round($approved, 2),
                    'used' => round($used, 2),
                    'utilization_percent' => $approved > 0 ? round(($used / $approved) * 100, 2) : 0,
                ];
            })
            ->sortByDesc('used')
            ->take(6)
            ->values()
            ->all();
    }

    private function expenseByCategory($postedLines): array
    {
        return $postedLines
            ->filter(fn (JournalLine $line) => $line->account?->account_type === 'expense')
            ->groupBy(fn (JournalLine $line) => $line->account?->name ?? 'General Expense')
            ->map(function ($items, string $accountName) {
                $total = $items->sum(fn (JournalLine $line) => (float) $line->debit - (float) $line->credit);

                return [
                    'category' => $accountName,
                    'total' => round($total, 2),
                ];
            })
            ->sortByDesc('total')
            ->take(6)
            ->values()
            ->all();
    }

    private function approvalStatusBreakdown(): array
    {
        return [
            'pending' => ExpenseRequest::query()->where('status', 'submitted')->count() + PurchaseRequest::query()->whereIn('status', ['submitted', 'pending_approval'])->count(),
            'approved' => ExpenseRequest::query()->where('status', 'approved')->count() + PurchaseRequest::query()->where('status', 'approved')->count(),
            'rejected' => ExpenseRequest::query()->where('status', 'rejected')->count() + PurchaseRequest::query()->where('status', 'rejected')->count(),
            'need_revision' => ExpenseRequest::query()->where('status', 'draft')->count() + PurchaseRequest::query()->where('status', 'draft')->count(),
        ];
    }

    public function donorDashboard(Request $request, BudgetMonitoringService $budgetService): JsonResponse
    {
        $period = $this->period($request);
        $donors = \App\Models\Master\Donor::query()->with(['grantAgreements'])->get();
        $grants = \App\Models\Master\GrantAgreement::query()->with(['donor', 'currency', 'projects'])->get();

        $budgetRows = $budgetService->summary($request->only(['project_id', 'grant_agreement_id', 'donor_id']));
        $postedLines = $this->postedLines($period['start'], $period['end']);
        $budgetRows = $this->applyPeriodActuals($budgetRows->all(), $postedLines);
        $totals = $this->budgetTotals($budgetRows);

        $donorSummaries = $donors->map(function ($donor) use ($budgetRows) {
            $donorLines = collect($budgetRows)->filter(fn ($r) => ($r['donor']['id'] ?? null) == $donor->id);
            $approved = (float) $donorLines->sum('approved_budget');
            $actual = (float) $donorLines->sum('actual');
            $committed = (float) $donorLines->sum('committed');
            $available = (float) $donorLines->sum('available');

            return [
                'id' => $donor->id,
                'code' => $donor->code,
                'name' => $donor->name,
                'type' => $donor->donor_type ?? 'Institutional',
                'active_grants_count' => $donor->grantAgreements->count(),
                'approved_budget' => round($approved, 2),
                'actual' => round($actual, 2),
                'committed' => round($committed, 2),
                'available' => round($available, 2),
                'utilization_percent' => $approved > 0 ? round((($actual + $committed) / $approved) * 100, 2) : 0,
            ];
        });

        $grantSummaries = $grants->map(function ($grant) use ($budgetRows) {
            $grantLines = collect($budgetRows)->filter(fn ($r) => ($r['grant_agreement']['id'] ?? null) == $grant->id);
            $approved = (float) $grantLines->sum('approved_budget');
            $actual = (float) $grantLines->sum('actual');
            $committed = (float) $grantLines->sum('committed');
            $available = (float) $grantLines->sum('available');
            $grantTotal = $approved > 0 ? $approved : (float) $grant->total_amount;

            return [
                'id' => $grant->id,
                'grant_no' => $grant->grant_no,
                'agreement_name' => $grant->agreement_name,
                'donor_name' => $grant->donor?->name ?? '-',
                'start_date' => $grant->start_date?->toDateString(),
                'end_date' => $grant->end_date?->toDateString(),
                'approved_budget' => round($grantTotal, 2),
                'actual' => round($actual, 2),
                'committed' => round($committed, 2),
                'available' => round($available, 2),
                'utilization_percent' => $grantTotal > 0
                    ? round((($actual + $committed) / $grantTotal) * 100, 2)
                    : 0,
                'status' => $grant->status ?? 'active',
            ];
        });

        return response()->json([
            'success' => true,
            'period' => $period['label'],
            'summary' => [
                'total_donors' => $donors->count(),
                'total_grants' => $grants->count(),
                'total_budget' => $totals['approved_budget'],
                'total_actual' => $totals['actual'],
                'total_committed' => $totals['committed'],
                'total_available' => $totals['available'],
                'overall_utilization' => $totals['utilization_percent'],
            ],
            'donors' => $donorSummaries,
            'grants' => $grantSummaries,
        ]);
    }

    public function reports(Request $request, BudgetMonitoringService $budgetService): JsonResponse
    {
        $period = $this->period($request);
        $projectId = $request->input('project_id');
        $budgetRows = $budgetService->summary($request->only(['project_id', 'grant_agreement_id', 'budget_category_id']));
        $postedLines = $this->postedLines($period['start'], $period['end'], $projectId);
        $budgetRows = $this->applyPeriodActuals($budgetRows->all(), $postedLines);

        return response()->json([
            'success' => true,
            'period' => $period['label'],
            'financial_statement' => $this->financialStatement($postedLines),
            'balance_sheet' => $this->balanceSheetData($request->input('as_of', $period['end']?->toDateString())),
            'budget_vs_actual' => [
                'totals' => $this->budgetTotals($budgetRows),
                'rows' => array_values(collect($budgetRows)->map(fn (array $row) => [
                    'budget_line_id' => $row['budget_line_id'],
                    'line_code' => $row['line_code'],
                    'description' => $row['description'],
                    'donor' => $row['donor']['name'] ?? '-',
                    'program' => $row['program']['name'] ?? '-',
                    'project' => $row['project']['name'] ?? '-',
                    'approved_budget' => $row['approved_budget'],
                    'actual' => $row['actual'],
                    'committed' => $row['committed'],
                    'available' => $row['available'],
                    'utilization_percent' => $row['utilization_percent'],
                    'status' => $row['status'],
                ])->all()),
            ],
            'ap_aging' => $this->apAging($period['end']),
            'ar_aging' => $this->arAging($period['end']),
            'cash_bank' => $this->cashBank($period['start'], $period['end']),
            'procurement' => $this->procurementReport(),
            'expense' => $this->expenseReport(),
            'recent_transactions' => $this->recentTransactions($period['start'], $period['end']),
        ]);
    }

    /**
     * Returns cumulative balance sheet balances. Depreciation journals posted by
     * FixedAssetController are included automatically in the asset and expense
     * account balances, so the report always reflects the current net book value.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $asOf = $request->filled('as_of')
            ? CarbonImmutable::parse($request->string('as_of'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return response()->json([
            'success' => true,
            'as_of' => $asOf->toDateString(),
            'data' => $this->balanceSheetData($asOf->toDateString()),
        ]);
    }

    public function forecast(Request $request): JsonResponse
    {
        $months = max(3, min(12, (int) $request->input('months', 6)));
        $from = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
        $rows = JournalLine::query()->whereHas('journal', fn (Builder $q) => $q->where('status', 'posted')->whereDate('journal_date', '>=', $from))->with('journal:id,journal_date')->get();
        $monthly = collect(range(0, $months - 1))->mapWithKeys(function ($index) use ($from, $rows) { $month = $from->addMonths($index); $key = $month->format('Y-m'); $value = $rows->filter(fn (JournalLine $line) => $line->journal?->journal_date?->format('Y-m') === $key)->sum(fn (JournalLine $line) => (float) $line->debit - (float) $line->credit); return [$key => round(max(0, $value), 2)]; });
        $average = round($monthly->avg(), 2);
        return response()->json(['success' => true, 'data' => ['months' => $monthly, 'average_monthly_expense' => $average, 'next_month_projection' => $average, 'method' => 'rolling_average']]);
    }

    private function period(Request $request): array
    {
        $start = $request->filled('start_date') ? CarbonImmutable::parse($request->string('start_date'))->startOfDay() : null;
        $end = $request->filled('end_date') ? CarbonImmutable::parse($request->string('end_date'))->endOfDay() : CarbonImmutable::now()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'label' => [
                'start_date' => $start?->toDateString(),
                'end_date' => $end?->toDateString(),
            ],
        ];
    }

    private function postedLines(?CarbonInterface $start, ?CarbonInterface $end, ?string $projectId = null)
    {
        return JournalLine::query()
            ->with(['journal:id,journal_number,journal_date,status,reference,description', 'account:id,code,name,account_type,normal_balance', 'donor:id,code,name', 'project:id,code,name'])
            ->whereHas('journal', function (Builder $query) use ($start, $end) {
                $query->where('status', 'posted')
                    ->when($start, fn (Builder $inner) => $inner->whereDate('journal_date', '>=', $start))
                    ->when($end, fn (Builder $inner) => $inner->whereDate('journal_date', '<=', $end));
            })
            ->when($projectId, fn (Builder $query) => $query->where('project_id', $projectId))
            ->get();
    }

    private function budgetTotals(array $rows): array
    {
        $totals = [
            'approved_budget' => round(array_sum(array_column($rows, 'approved_budget')), 2),
            'actual' => round(array_sum(array_column($rows, 'actual')), 2),
            'committed' => round(array_sum(array_column($rows, 'committed')), 2),
            'available' => round(array_sum(array_column($rows, 'available')), 2),
        ];
        $totals['utilization_percent'] = $totals['approved_budget'] > 0
            ? round((($totals['actual'] + $totals['committed']) / $totals['approved_budget']) * 100, 2)
            : 0;

        return $totals;
    }

    private function applyPeriodActuals(array $rows, $postedLines): array
    {
        $actualByBudgetLine = $postedLines
            ->filter(fn (JournalLine $line) => $line->budget_line_id && $line->account?->account_type === 'expense')
            ->groupBy('budget_line_id')
            ->map(fn ($items) => round((float) $items->sum(fn (JournalLine $line) => (float) $line->debit - (float) $line->credit), 2));

        return collect($rows)->map(function (array $row) use ($actualByBudgetLine) {
            $actual = (float) ($actualByBudgetLine[$row['budget_line_id']] ?? 0);
            $approved = (float) $row['approved_budget'];
            $committed = (float) $row['committed'];
            $available = round($approved - $actual - $committed, 2);

            return [
                ...$row,
                'actual' => round($actual, 2),
                'available' => $available,
                'utilization_percent' => $approved > 0 ? round((($actual + $committed) / $approved) * 100, 2) : 0,
                'status' => $available < 0 ? 'over_budget' : ((($actual + $committed) / max($approved, 1)) >= 0.9 ? 'warning' : 'healthy'),
            ];
        })->all();
    }

    private function cashPosition(?CarbonInterface $start, ?CarbonInterface $end): float
    {
        return (float) BankTransaction::query()
            ->when($start, fn (Builder $query) => $query->whereDate('transaction_date', '>=', $start))
            ->when($end, fn (Builder $query) => $query->whereDate('transaction_date', '<=', $end))
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');
    }

    private function openAp(): float
    {
        return (float) SupplierInvoice::query()
            ->whereIn('status', ['posted', 'partially_paid', 'approved', 'matched'])
            ->get()
            ->sum(fn (SupplierInvoice $invoice) => max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount));
    }

    private function openAr(): float
    {
        return (float) CustomerInvoice::query()
            ->whereIn('status', ['posted', 'partially_received'])
            ->get()
            ->sum(fn (CustomerInvoice $invoice) => max(0, (float) $invoice->total_amount - (float) $invoice->received_amount));
    }

    private function monthlyBudgetVsActual(float $approvedBudget, $postedLines): array
    {
        $months = collect(range(5, 0))->map(fn (int $monthsAgo) => CarbonImmutable::now()->subMonths($monthsAgo)->startOfMonth());
        $monthlyBudget = $approvedBudget > 0 ? round($approvedBudget / 12, 2) : 0;

        return $months->map(function ($month) use ($monthlyBudget, $postedLines) {
            $actual = $postedLines
                ->filter(fn (JournalLine $line) => $line->journal?->journal_date?->format('Y-m') === $month->format('Y-m'))
                ->sum(fn (JournalLine $line) => $line->account?->account_type === 'expense' ? ((float) $line->debit - (float) $line->credit) : 0);

            return [
                'month' => $month->format('M'),
                'budget' => $monthlyBudget,
                'actual' => round($actual, 2),
            ];
        })->all();
    }

    private function donorUtilization(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn (array $row) => $row['donor']['name'] ?? 'Unassigned')
            ->map(function ($items, string $donor) {
                $approved = (float) $items->sum('approved_budget');
                $used = (float) $items->sum(fn (array $row) => $row['actual'] + $row['committed']);

                return [
                    'name' => $donor,
                    'approved_budget' => round($approved, 2),
                    'used' => round($used, 2),
                    'utilization_percent' => $approved > 0 ? round(($used / $approved) * 100, 2) : 0,
                ];
            })
            ->sortByDesc('used')
            ->take(5)
            ->values()
            ->all();
    }

    private function pendingActions(): array
    {
        return [
            ['module' => 'Expense', 'label' => 'Submitted Expense', 'count' => ExpenseRequest::query()->where('status', 'submitted')->count()],
            ['module' => 'Expense', 'label' => 'Approved not Posted', 'count' => ExpenseRequest::query()->where('status', 'approved')->count()],
            ['module' => 'Procurement', 'label' => 'Submitted PR', 'count' => PurchaseRequest::query()->whereIn('status', ['submitted', 'pending_approval'])->count()],
            ['module' => 'Accounting', 'label' => 'Journal to Post', 'count' => Journal::query()->whereIn('status', ['submitted', 'reviewed'])->count()],
            ['module' => 'AP', 'label' => 'Open AP Invoice', 'count' => SupplierInvoice::query()->whereIn('status', ['matched', 'posted', 'partially_paid'])->count()],
        ];
    }

    private function recentTransactions(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        return Journal::query()
            ->with('lines.account:id,code,name,account_type')
            ->where('status', 'posted')
            ->when($start, fn (Builder $query) => $query->whereDate('journal_date', '>=', $start))
            ->when($end, fn (Builder $query) => $query->whereDate('journal_date', '<=', $end))
            ->latest('journal_date')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (Journal $journal) {
                $debit = (float) $journal->lines->sum('debit');
                $expense = (float) $journal->lines
                    ->filter(fn (JournalLine $line) => $line->account?->account_type === 'expense')
                    ->sum(fn (JournalLine $line) => (float) $line->debit - (float) $line->credit);

                return [
                    'date' => $journal->journal_date?->toDateString(),
                    'reference' => $journal->reference ?: $journal->journal_number,
                    'description' => $journal->description,
                    'status' => $journal->status,
                    'amount' => round($expense ?: $debit, 2),
                    'direction' => $expense > 0 ? 'out' : 'neutral',
                ];
            })
            ->all();
    }

    private function financialStatement($postedLines): array
    {
        $types = ['asset', 'liability', 'equity', 'revenue', 'expense'];
        $accounts = ChartOfAccount::query()->whereIn('account_type', $types)->orderBy('code')->get();

        $rows = $accounts->map(function (ChartOfAccount $account) use ($postedLines) {
            $debit = (float) $postedLines->where('account_id', $account->id)->sum('debit');
            $credit = (float) $postedLines->where('account_id', $account->id)->sum('credit');
            $normalCredit = in_array($account->normal_balance, ['credit'], true);
            $balance = $normalCredit ? $credit - $debit : $debit - $credit;

            return [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => round($balance, 2),
            ];
        })->filter(fn (array $row) => $row['debit'] != 0.0 || $row['credit'] != 0.0)->values();

        return [
            'totals_by_type' => $rows->groupBy('account_type')->map(fn ($items) => round((float) $items->sum('balance'), 2))->all(),
            'rows' => $rows->all(),
        ];
    }

    private function balanceSheetData(?string $asOf): array
    {
        $date = $asOf ? CarbonImmutable::parse($asOf)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $lines = JournalLine::query()
            ->with('account:id,code,name,account_type,normal_balance')
            ->whereHas('journal', fn (Builder $query) => $query->where('status', 'posted')->whereDate('journal_date', '<=', $date))
            ->get();

        $accounts = $lines->groupBy('account_id')->map(function ($items) {
            $account = $items->first()->account;
            if (! $account || ! in_array($account->account_type, ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
                return null;
            }
            $debit = (float) $items->sum('debit');
            $credit = (float) $items->sum('credit');
            $balance = $account->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;
            return [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => round($balance, 2),
            ];
        })->filter()->filter(fn (array $row) => abs($row['balance']) > 0.00001)->sortBy('code')->values();

        $byType = $accounts->groupBy('account_type')->map(fn ($rows) => round((float) $rows->sum('balance'), 2));
        $assets = (float) ($byType['asset'] ?? 0);
        $liabilities = (float) ($byType['liability'] ?? 0);
        $equity = (float) ($byType['equity'] ?? 0);
        $currentResult = (float) ($byType['revenue'] ?? 0) - (float) ($byType['expense'] ?? 0);

        $fixedAssets = FixedAsset::query()
            ->whereDate('acquisition_date', '<=', $date)
            ->whereIn('status', ['active', 'transferred', 'disposed'])
            ->get();
        $fixedAssetSummary = [
            'acquisition_cost' => round((float) $fixedAssets->sum('acquisition_cost'), 2),
            'accumulated_depreciation' => round((float) $fixedAssets->sum('accumulated_depreciation'), 2),
            'net_book_value' => round((float) $fixedAssets->sum('net_book_value'), 2),
            'asset_count' => $fixedAssets->count(),
        ];
        $balanceAccounts = $accounts->whereIn('account_type', ['asset', 'liability', 'equity'])->values();

        return [
            'accounts' => $balanceAccounts->all(),
            'assets' => $balanceAccounts->where('account_type', 'asset')->values()->all(),
            'liabilities' => $balanceAccounts->where('account_type', 'liability')->values()->all(),
            'equity' => $balanceAccounts->where('account_type', 'equity')->values()->all(),
            'totals' => [
                'assets' => round($assets, 2),
                'liabilities' => round($liabilities, 2),
                'equity' => round($equity, 2),
                'current_result' => round($currentResult, 2),
                'liabilities_and_equity' => round($liabilities + $equity + $currentResult, 2),
                'difference' => round($assets - ($liabilities + $equity + $currentResult), 2),
                'balanced' => abs($assets - ($liabilities + $equity + $currentResult)) < 0.01,
            ],
            'fixed_assets' => $fixedAssetSummary,
        ];
    }

    private function apAging(?CarbonInterface $end): array
    {
        $asOf = $end ?: CarbonImmutable::now();
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
        $rows = SupplierInvoice::query()->with('vendor:id,code,name')->whereNotIn('status', ['paid', 'cancelled'])->get()->map(function (SupplierInvoice $invoice) use (&$buckets, $asOf) {
            $outstanding = max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount);
            $days = $invoice->due_date ? $invoice->due_date->diffInDays($asOf, false) : 0;
            $bucket = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : 'over_90')));
            $buckets[$bucket] += $outstanding;

            return [
                'invoice_number' => $invoice->invoice_number,
                'vendor' => $invoice->vendor?->name ?? '-',
                'due_date' => $invoice->due_date?->toDateString(),
                'days_overdue' => max(0, $days),
                'outstanding' => round($outstanding, 2),
                'bucket' => $bucket,
            ];
        });

        return ['buckets' => array_map(fn ($value) => round($value, 2), $buckets), 'rows' => $rows->values()->all()];
    }

    private function arAging(?CarbonInterface $end): array
    {
        $asOf = $end ?: CarbonImmutable::now();
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
        $rows = CustomerInvoice::query()->with('customer:id,code,name')->whereNotIn('status', ['received', 'void'])->get()->map(function (CustomerInvoice $invoice) use (&$buckets, $asOf) {
            $outstanding = max(0, (float) $invoice->total_amount - (float) $invoice->received_amount);
            $days = $invoice->due_date ? $invoice->due_date->diffInDays($asOf, false) : 0;
            $bucket = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : 'over_90')));
            $buckets[$bucket] += $outstanding;

            return [
                'invoice_number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->name ?? '-',
                'due_date' => $invoice->due_date?->toDateString(),
                'days_overdue' => max(0, $days),
                'outstanding' => round($outstanding, 2),
                'bucket' => $bucket,
            ];
        });

        return ['buckets' => array_map(fn ($value) => round($value, 2), $buckets), 'rows' => $rows->values()->all()];
    }

    private function cashBank(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $rows = BankTransaction::query()
            ->with('bankAccount:id,bank_name,account_number,account_name')
            ->when($start, fn (Builder $query) => $query->whereDate('transaction_date', '>=', $start))
            ->when($end, fn (Builder $query) => $query->whereDate('transaction_date', '<=', $end))
            ->get()
            ->groupBy('bank_account_id')
            ->map(function ($items) {
                $first = $items->first();
                $debit = (float) $items->sum('debit');
                $credit = (float) $items->sum('credit');

                return [
                    'bank_account_id' => $first->bank_account_id,
                    'bank' => trim(($first->bankAccount?->bank_name ?? '-') . ' ' . ($first->bankAccount?->account_number ?? '')),
                    'debit' => round($debit, 2),
                    'credit' => round($credit, 2),
                    'balance' => round($debit - $credit, 2),
                    'unreconciled_count' => $items->where('status', '!=', 'matched')->count(),
                ];
            })
            ->values()
            ->all();

        return ['total_balance' => round(array_sum(array_column($rows, 'balance')), 2), 'rows' => $rows];
    }

    private function procurementReport(): array
    {
        return [
            'purchase_requests' => PurchaseRequest::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'purchase_orders' => PurchaseOrder::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'commitments_open' => (float) BudgetCommitment::query()->where('status', 'open')->sum('amount'),
        ];
    }

    private function expenseReport(): array
    {
        return [
            'by_status' => ExpenseRequest::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'paid_amount' => (float) ExpenseRequest::query()->sum('paid_amount'),
            'total_amount' => (float) ExpenseRequest::query()->with('lines')->get()->sum(fn (ExpenseRequest $expense) => (float) $expense->total_amount),
        ];
    }
}
