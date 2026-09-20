<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalLine;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsDashboardController extends Controller
{
    public function dashboard(Request $request, BudgetMonitoringService $budgetService): JsonResponse
    {
        $period = $this->period($request);
        $budgetRows = $budgetService->summary($request->only(['project_id', 'grant_agreement_id', 'budget_category_id']));
        $postedLines = $this->postedLines($period['start'], $period['end']);
        $budgetRows = $this->applyPeriodActuals($budgetRows->all(), $postedLines);
        $budgetTotals = $this->budgetTotals($budgetRows);
        $recentTransactions = $this->recentTransactions($period['start'], $period['end']);

        $pendingApprovals = PurchaseRequest::query()->whereIn('status', ['submitted', 'pending_approval'])->count()
            + ExpenseRequest::query()->whereIn('status', ['submitted', 'approved'])->count()
            + Journal::query()->whereIn('status', ['submitted', 'reviewed'])->count()
            + SupplierInvoice::query()->whereIn('status', ['matched', 'posted', 'partially_paid'])->count();

        return response()->json([
            'success' => true,
            'period' => $period['label'],
            'summary' => [
                'operating_cash' => $this->cashPosition($period['start'], $period['end']),
                'approved_budget' => $budgetTotals['approved_budget'],
                'actual_expense' => $budgetTotals['actual'],
                'commitment' => $budgetTotals['committed'],
                'available_budget' => $budgetTotals['available'],
                'pending_approvals' => $pendingApprovals,
                'open_ap' => $this->openAp(),
                'open_ar' => $this->openAr(),
                'paid_amount' => (float) Payment::query()
                    ->when($period['start'], fn (Builder $query) => $query->whereDate('payment_date', '>=', $period['start']))
                    ->when($period['end'], fn (Builder $query) => $query->whereDate('payment_date', '<=', $period['end']))
                    ->sum('amount'),
            ],
            'charts' => [
                'budget_vs_actual' => $this->monthlyBudgetVsActual($budgetTotals['approved_budget'], $postedLines),
                'donor_utilization' => $this->donorUtilization($budgetRows),
            ],
            'pending_actions' => $this->pendingActions(),
            'recent_transactions' => $recentTransactions,
        ]);
    }

    public function reports(Request $request, BudgetMonitoringService $budgetService): JsonResponse
    {
        $period = $this->period($request);
        $budgetRows = $budgetService->summary($request->only(['project_id', 'grant_agreement_id', 'budget_category_id']));
        $postedLines = $this->postedLines($period['start'], $period['end']);
        $budgetRows = $this->applyPeriodActuals($budgetRows->all(), $postedLines);

        return response()->json([
            'success' => true,
            'period' => $period['label'],
            'financial_statement' => $this->financialStatement($postedLines),
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

    private function period(Request $request): array
    {
        $start = $request->filled('start_date') ? CarbonImmutable::parse($request->string('start_date'))->startOfDay() : null;
        $end = $request->filled('end_date') ? CarbonImmutable::parse($request->string('end_date'))->endOfDay() : now()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'label' => [
                'start_date' => $start?->toDateString(),
                'end_date' => $end?->toDateString(),
            ],
        ];
    }

    private function postedLines(?CarbonImmutable $start, ?CarbonImmutable $end)
    {
        return JournalLine::query()
            ->with(['journal:id,journal_number,journal_date,status,reference,description', 'account:id,code,name,account_type,normal_balance', 'donor:id,code,name', 'project:id,code,name'])
            ->whereHas('journal', function (Builder $query) use ($start, $end) {
                $query->where('status', 'posted')
                    ->when($start, fn (Builder $inner) => $inner->whereDate('journal_date', '>=', $start))
                    ->when($end, fn (Builder $inner) => $inner->whereDate('journal_date', '<=', $end));
            })
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

    private function cashPosition(?CarbonImmutable $start, ?CarbonImmutable $end): float
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
        $months = collect(range(5, 0))->map(fn (int $monthsAgo) => now()->subMonths($monthsAgo)->startOfMonth());
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

    private function recentTransactions(?CarbonImmutable $start, ?CarbonImmutable $end): array
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

    private function apAging(?CarbonImmutable $end): array
    {
        $asOf = $end ?: now();
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

    private function arAging(?CarbonImmutable $end): array
    {
        $asOf = $end ?: now();
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

    private function cashBank(?CarbonImmutable $start, ?CarbonImmutable $end): array
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
