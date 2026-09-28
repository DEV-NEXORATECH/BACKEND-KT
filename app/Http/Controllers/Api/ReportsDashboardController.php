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
use App\Models\Master\GrantReportingDeadline;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierInvoice;
use App\Services\Budget\BudgetMonitoringService;
use App\Services\Rbac\DataScopeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportsDashboardController extends Controller
{
        public function procurementDashboard(Request $request): JsonResponse
    {
        $requests = PurchaseRequest::query()->with(['requester:id,name', 'project:id,code,name'])->latest('id')->limit(100)->get();
        $orders = PurchaseOrder::query()->with(['vendor:id,code,name', 'purchaseRequest:id,pr_number,project_id', 'purchaseRequest.project:id,code,name'])->latest('id')->limit(100)->get();
        $invoices = SupplierInvoice::query()->with(['vendor:id,code,name', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number'])->latest('id')->limit(100)->get();

        $rfqsCount = \Illuminate\Support\Facades\Schema::hasTable('rfqs')
            ? \Illuminate\Support\Facades\DB::table('rfqs')->count()
            : 0;
        $cbaCount = \Illuminate\Support\Facades\Schema::hasTable('comparative_bid_analyses')
            ? \Illuminate\Support\Facades\DB::table('comparative_bid_analyses')->count()
            : 0;
        $grnCount = \Illuminate\Support\Facades\Schema::hasTable('goods_receipt_notes')
            ? \Illuminate\Support\Facades\DB::table('goods_receipt_notes')->count()
            : 0;

        $totalPrAmount = (float) \Illuminate\Support\Facades\DB::table('purchase_request_lines')->sum('total_amount');
        $totalPoAmount = (float) \Illuminate\Support\Facades\DB::table('purchase_order_lines')->sum('total_amount');

        $pipeline = [
            ['stage' => 'Purchase Requests (PR)', 'code' => 'PR', 'count' => PurchaseRequest::count(), 'total_amount' => $totalPrAmount, 'color' => '#3b82f6'],
            ['stage' => 'Requests for Quotation (RFQ)', 'code' => 'RFQ', 'count' => $rfqsCount, 'total_amount' => 0, 'color' => '#6366f1'],
            ['stage' => 'Comparative Bid Analyses (CBA)', 'code' => 'CBA', 'count' => $cbaCount, 'total_amount' => 0, 'color' => '#8b5cf6'],
            ['stage' => 'Purchase Orders (PO)', 'code' => 'PO', 'count' => PurchaseOrder::count(), 'total_amount' => $totalPoAmount, 'color' => '#059669'],
            ['stage' => 'Goods Receipt Notes (GRN)', 'code' => 'GRN', 'count' => $grnCount, 'total_amount' => 0, 'color' => '#10b981'],
            ['stage' => 'Supplier Invoices', 'code' => 'INV', 'count' => SupplierInvoice::count(), 'total_amount' => (float) SupplierInvoice::sum('total_amount'), 'color' => '#d97706'],
        ];

        // 4.2 Dashboard - 6 KPI Pipeline Statuses
        $rfqOngoingCount = \Illuminate\Support\Facades\Schema::hasTable('rfqs')
            ? \Illuminate\Support\Facades\DB::table('rfqs')->whereIn('status', ['published', 'open', 'draft'])->count()
            : 0;
        $cbaPendingCount = \Illuminate\Support\Facades\Schema::hasTable('rfqs')
            ? \Illuminate\Support\Facades\DB::table('rfqs')->where('status', 'closed')->count()
            : 0;

        $grnPendingCount = PurchaseOrder::where('status', 'approved')
            ->whereDoesntHave('goodsReceipts', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->count();

        $invoicePendingMatchCount = SupplierInvoice::where(function ($q) {
            $q->whereIn('match_status', ['unchecked', 'mismatch', 'draft'])->orWhere('status', 'draft');
        })->count();

        $pipelineStatus = [
            'pr_pending_approval' => PurchaseRequest::where('status', 'submitted')->count(),
            'rfq_ongoing' => $rfqOngoingCount,
            'cba_pending' => $cbaPendingCount,
            'po_contract_active' => PurchaseOrder::where('status', 'approved')->count(),
            'grn_pending' => $grnPendingCount,
            'invoice_pending_match' => $invoicePendingMatchCount,
        ];

        // 4.2 Dashboard - Committed budget by project & donor
        $committedByProject = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('budget_commitments') && \Illuminate\Support\Facades\Schema::hasTable('budget_lines')) {
            $committedByProject = \Illuminate\Support\Facades\DB::table('budget_commitments')
                ->join('budget_lines', 'budget_commitments.budget_line_id', '=', 'budget_lines.id')
                ->join('projects', 'budget_lines.project_id', '=', 'projects.id')
                ->whereIn('budget_commitments.status', ['open', 'converted'])
                ->groupBy('projects.id', 'projects.code', 'projects.name')
                ->selectRaw('projects.id, projects.code, projects.name, SUM(budget_commitments.amount) as committed')
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'committed' => round((float) $r->committed, 2)]);
        }

        if ($committedByProject->isEmpty()) {
            $committedByProject = \Illuminate\Support\Facades\DB::table('purchase_order_lines')
                ->join('purchase_orders', 'purchase_order_lines.purchase_order_id', '=', 'purchase_orders.id')
                ->join('purchase_requests', 'purchase_orders.purchase_request_id', '=', 'purchase_requests.id')
                ->join('projects', 'purchase_requests.project_id', '=', 'projects.id')
                ->where('purchase_orders.status', 'approved')
                ->groupBy('projects.id', 'projects.code', 'projects.name')
                ->selectRaw('projects.id, projects.code, projects.name, SUM(purchase_order_lines.total_amount) as committed')
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'committed' => round((float) $r->committed, 2)]);
        }

        $committedByDonor = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('budget_commitments') && \Illuminate\Support\Facades\Schema::hasTable('budget_lines')) {
            $committedByDonor = \Illuminate\Support\Facades\DB::table('budget_commitments')
                ->join('budget_lines', 'budget_commitments.budget_line_id', '=', 'budget_lines.id')
                ->join('grant_agreements', 'budget_lines.grant_agreement_id', '=', 'grant_agreements.id')
                ->join('donors', 'grant_agreements.donor_id', '=', 'donors.id')
                ->whereIn('budget_commitments.status', ['open', 'converted'])
                ->groupBy('donors.id', 'donors.code', 'donors.name')
                ->selectRaw('donors.id, donors.code, donors.name, SUM(budget_commitments.amount) as committed')
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'committed' => round((float) $r->committed, 2)]);
        }

        if ($committedByDonor->isEmpty()) {
            $committedByDonor = \Illuminate\Support\Facades\DB::table('purchase_order_lines')
                ->join('purchase_orders', 'purchase_order_lines.purchase_order_id', '=', 'purchase_orders.id')
                ->join('purchase_requests', 'purchase_orders.purchase_request_id', '=', 'purchase_requests.id')
                ->join('purchase_request_lines', 'purchase_requests.id', '=', 'purchase_request_lines.purchase_request_id')
                ->join('budget_lines', 'purchase_request_lines.budget_line_id', '=', 'budget_lines.id')
                ->join('grant_agreements', 'budget_lines.grant_agreement_id', '=', 'grant_agreements.id')
                ->join('donors', 'grant_agreements.donor_id', '=', 'donors.id')
                ->where('purchase_orders.status', 'approved')
                ->groupBy('donors.id', 'donors.code', 'donors.name')
                ->selectRaw('donors.id, donors.code, donors.name, SUM(purchase_order_lines.total_amount) as committed')
                ->get()
                ->map(fn ($r) => ['id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'committed' => round((float) $r->committed, 2)]);
        }

        $supplierSpend = SupplierInvoice::query()
            ->with('vendor:id,name,code')
            ->selectRaw('vendor_id, COUNT(*) as invoice_count, SUM(total_amount) as total_spend, SUM(paid_amount) as total_paid')
            ->groupBy('vendor_id')
            ->orderByDesc('total_spend')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'vendor_id' => $item->vendor_id,
                'vendor_name' => $item->vendor?->name ?? 'Unknown Vendor',
                'vendor_code' => $item->vendor?->code ?? '-',
                'invoice_count' => (int) $item->invoice_count,
                'total_spend' => round((float) $item->total_spend, 2),
                'total_paid' => round((float) $item->total_paid, 2),
                'outstanding' => round((float) $item->total_spend - (float) $item->total_paid, 2),
            ]);

        return response()->json([
            'success' => true,
            'summary' => [
                'total_pr' => PurchaseRequest::count(),
                'total_po' => PurchaseOrder::count(),
                'total_po_value' => $totalPoAmount,
                'total_invoices' => SupplierInvoice::count(),
                'total_invoice_value' => (float) SupplierInvoice::sum('total_amount'),
                'total_spend' => (float) SupplierInvoice::sum('paid_amount'),
            ],
            'pipeline' => $pipeline,
            'pipeline_status' => $pipelineStatus,
            'committed_budget' => [
                'by_project' => $committedByProject,
                'by_donor' => $committedByDonor,
            ],
            'supplier_spend' => $supplierSpend,
            'procurement' => [
                'requests' => $requests,
                'orders' => $orders,
                'invoices' => $invoices,
            ],
        ]);
    }

    public function procurementAuditChain(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        $supplierInvoice->load([
            'vendor:id,code,name',
            'tax:id,code,name,rate',
            'lines',
            'goodsReceipt.lines',
            'purchaseOrder.lines',
            'purchaseOrder.vendor:id,code,name',
            'purchaseOrder.purchaseRequest.requester:id,name,email',
            'purchaseOrder.purchaseRequest.department:id,name',
            'purchaseOrder.purchaseRequest.project:id,code,name',
            'purchaseOrder.purchaseRequest.lines.budgetLine.grantAgreement.donor:id,code,name',
        ]);

        $po = $supplierInvoice->purchaseOrder;
        $pr = $po?->purchaseRequest;
        $grn = $supplierInvoice->goodsReceipt;

        // Trace journal entry if exists
        $journal = null;
        if ($supplierInvoice->journal_id && \Illuminate\Support\Facades\Schema::hasTable('accounting_journals')) {
            $journal = \App\Models\Accounting\Journal::with('lines.account:id,code,name')->find($supplierInvoice->journal_id);
        }

        // Trace budget line info
        $budgetLines = $pr?->lines->map(fn ($line) => [
            'budget_line_id' => $line->budget_line_id,
            'budget_line_code' => $line->budgetLine?->code,
            'budget_line_name' => $line->budgetLine?->name,
            'donor_name' => $line->budgetLine?->grantAgreement?->donor?->name ?? 'Internal / General',
            'item_description' => $line->item_description,
            'total_amount' => (float) $line->total_amount,
        ])->values() ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'invoice' => [
                    'id' => $supplierInvoice->id,
                    'invoice_number' => $supplierInvoice->invoice_number,
                    'invoice_date' => $supplierInvoice->invoice_date?->toDateString(),
                    'due_date' => $supplierInvoice->due_date?->toDateString(),
                    'total_amount' => (float) $supplierInvoice->total_amount,
                    'paid_amount' => (float) $supplierInvoice->paid_amount,
                    'status' => $supplierInvoice->status,
                    'match_status' => $supplierInvoice->match_status,
                    'notes' => $supplierInvoice->notes,
                    'vendor' => $supplierInvoice->vendor,
                    'tax' => $supplierInvoice->tax,
                    'lines' => $supplierInvoice->lines,
                ],
                'grn' => $grn ? [
                    'id' => $grn->id,
                    'grn_number' => $grn->grn_number,
                    'receipt_date' => $grn->receipt_date?->toDateString(),
                    'delivery_order_number' => $grn->delivery_order_number,
                    'status' => $grn->status,
                    'lines' => $grn->lines,
                ] : null,
                'po' => $po ? [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'contract_number' => $po->contract_number,
                    'po_date' => $po->po_date?->toDateString(),
                    'contract_date' => $po->contract_date?->toDateString(),
                    'total_amount' => (float) $po->total_amount,
                    'status' => $po->status,
                    'terms' => $po->terms,
                    'lines' => $po->lines,
                ] : null,
                'pr' => $pr ? [
                    'id' => $pr->id,
                    'pr_number' => $pr->pr_number,
                    'request_date' => $pr->request_date?->toDateString(),
                    'requester' => $pr->requester?->name,
                    'department' => $pr->department?->name,
                    'justification' => $pr->justification,
                    'status' => $pr->status,
                    'total_amount' => (float) $pr->total_amount,
                ] : null,
                'budget' => [
                    'project' => $pr?->project,
                    'lines' => $budgetLines,
                ],
                'journal' => $journal ? [
                    'id' => $journal->id,
                    'journal_number' => $journal->journal_number,
                    'journal_date' => $journal->journal_date?->toDateString(),
                    'status' => $journal->status,
                    'total_debit' => (float) $journal->total_debit,
                    'total_credit' => (float) $journal->total_credit,
                    'lines' => $journal->lines,
                ] : null,
            ],
        ]);
    }

    public function dashboard(Request $request, BudgetMonitoringService $budgetService): JsonResponse
    {
        $period = $this->period($request);
        if (! app(DataScopeService::class)->canAccessAll($request->user())) {
            return $this->personalDashboard($request, $period);
        }

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

    private function personalDashboard(Request $request, array $period): JsonResponse
    {
        $userId = $request->user()->id;
        $expenses = ExpenseRequest::query()->where('requester_id', $userId)
            ->when($period['start'], fn (Builder $q) => $q->whereDate('request_date', '>=', $period['start']))
            ->when($period['end'], fn (Builder $q) => $q->whereDate('request_date', '<=', $period['end']))->get();
        $payments = Payment::query()->where('created_by', $userId)
            ->when($period['start'], fn (Builder $q) => $q->whereDate('payment_date', '>=', $period['start']))
            ->when($period['end'], fn (Builder $q) => $q->whereDate('payment_date', '<=', $period['end']))->get();
        $requests = PurchaseRequest::query()->where('requester_id', $userId)->latest('id')->take(5)->get();
        $totalExpense = (float) $expenses->sum('total_amount');

        return response()->json([
            'success' => true,
            'period' => $period['label'],
            'scope' => 'personal',
            'summary' => [
                'total_budget' => 0, 'approved_budget' => 0, 'total_actual' => $totalExpense, 'actual_expense' => $totalExpense,
                'commitment' => 0, 'remaining_budget' => 0, 'available_budget' => 0, 'utilization_percent' => 0,
                'total_income' => 0, 'total_expense' => $totalExpense, 'operating_cash' => 0, 'cash_bank_balance' => 0,
                'open_ap' => 0, 'outstanding_payable' => 0, 'open_ar' => 0, 'outstanding_receivable' => 0,
                'active_donors' => 0, 'active_grants' => 0, 'active_programs' => 0, 'active_projects' => 0,
                'pending_approvals' => $expenses->whereIn('status', ['draft', 'submitted'])->count() + $requests->where('status', 'submitted')->count(),
                'paid_amount' => (float) $payments->sum('amount'),
            ],
            'charts' => ['budget_vs_actual' => [], 'income_vs_expense' => [], 'donor_utilization' => [], 'project_utilization' => [], 'expense_by_category' => [], 'cash_position_by_bank' => [], 'approval_status' => []],
            'pending_actions' => $expenses->whereIn('status', ['draft', 'submitted'])->map(fn ($item) => ['module' => 'expense', 'reference' => $item->request_number, 'status' => $item->status])->values(),
            'recent_transactions' => $expenses->sortByDesc('created_at')->take(5)->map(fn ($item) => ['module' => 'expense', 'reference' => $item->request_number, 'description' => $item->description, 'amount' => (float) $item->total_amount, 'date' => $item->request_date?->toDateString(), 'status' => $item->status])->values(),
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
        if (! app(DataScopeService::class)->canAccessAll($request->user())) {
            return response()->json([
                'success' => true,
                'scope' => 'personal',
                'period' => $period['label'],
                'summary' => ['total_donors' => 0, 'total_grants' => 0, 'total_budget' => 0, 'total_actual' => 0, 'total_committed' => 0, 'total_available' => 0, 'overall_utilization' => 0],
                'donors' => [],
                'grants' => [],
            ]);
        }

        $donors = \App\Models\Master\Donor::query()->with(['grantAgreements'])->get();
        $grants = \App\Models\Master\GrantAgreement::query()->with(['donor', 'currency', 'projects'])->get();
        $grantDeadlines = GrantReportingDeadline::query()->whereIn('grant_agreement_id', $grants->pluck('id'))->orderBy('due_date')->get()->groupBy('grant_agreement_id');

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

        $grantSummaries = $grants->map(function ($grant) use ($budgetRows, $grantDeadlines) {
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
                'grant_value' => round((float) $grant->grant_value, 2),
                'actual' => round($actual, 2),
                'committed' => round($committed, 2),
                'available' => round($available, 2),
                'utilization_percent' => $grantTotal > 0
                    ? round((($actual + $committed) / $grantTotal) * 100, 2)
                    : 0,
                'status' => $grant->status ?? 'active',
                'reporting_deadline' => $grantDeadlines->get($grant->id)?->first()?->due_date?->toDateString(),
            ];
        });

        $endingSoon = $grantSummaries->filter(fn (array $grant) => $grant['end_date'] && $grant['end_date'] >= now()->toDateString() && $grant['end_date'] <= now()->addDays(60)->toDateString())->count();
        $reportingSoon = $grantSummaries->filter(fn (array $grant) => $grant['reporting_deadline'] && $grant['reporting_deadline'] >= now()->toDateString() && $grant['reporting_deadline'] <= now()->addDays(60)->toDateString())->count();

        return response()->json([
            'success' => true,
            'period' => $period['label'],
            'summary' => [
                'total_donors' => $donors->count(),
                'total_grants' => $grants->count(),
                'total_budget' => $totals['approved_budget'],
                'total_grant_value' => round((float) $grants->sum('grant_value'), 2),
                'total_actual' => $totals['actual'],
                'total_committed' => $totals['committed'],
                'total_available' => $totals['available'],
                'overall_utilization' => $totals['utilization_percent'],
                'grants_ending_soon' => $endingSoon,
                'reporting_deadlines' => $reportingSoon,
            ],
            'donors' => $donorSummaries,
            'grants' => $grantSummaries,
        ]);
    }

    /**
     * Grant reporting workspace data. The frontend consumes one governed
     * contract for BVA, projections, variance analysis, expenditure listings,
     * and reporting deadlines instead of rebuilding these values locally.
     */
    public function grantReporting(Request $request, BudgetMonitoringService $budgetService): JsonResponse
    {
        $period = $this->period($request);
        $filters = $request->only(['fiscal_year_id', 'start_date', 'end_date', 'donor_id', 'grant_agreement_id', 'program_id', 'project_id', 'currency_id']);
        $budgetRows = $budgetService->summary(array_filter($filters));
        $postedLines = $this->postedLines($period['start'], $period['end'], $request->input('project_id'));
        $budgetRows = $this->applyPeriodActuals($budgetRows->all(), $postedLines);
        $totals = $this->budgetTotals($budgetRows);

        $deadlines = GrantReportingDeadline::query()
            ->with('grantAgreement:id,grant_no,agreement_name')
            ->when($request->filled('grant_agreement_id'), fn (Builder $q) => $q->where('grant_agreement_id', $request->integer('grant_agreement_id')))
            ->orderBy('due_date')
            ->get()
            ->map(fn (GrantReportingDeadline $deadline) => [
                'id' => $deadline->id,
                'report_type' => $deadline->report_type,
                'due_date' => $deadline->due_date?->toDateString(),
                'status' => $deadline->status,
                'days_remaining' => $deadline->due_date ? now()->startOfDay()->diffInDays($deadline->due_date, false) : null,
                'period' => $deadline->notes,
                'notes' => $deadline->notes,
                'grant' => $deadline->grantAgreement?->grant_no ?: $deadline->grantAgreement?->agreement_name,
            ])->values()->all();

        $bva = collect($budgetRows)->map(fn (array $row) => [
            'budget_line_id' => $row['budget_line_id'] ?? null,
            'project' => $row['project']['name'] ?? 'Unassigned Project',
            'grant' => $row['grant_agreement']['code'] ?? ($row['grant_agreement']['name'] ?? 'Unassigned Grant'),
            'budget' => round((float) ($row['approved_budget'] ?? 0), 2),
            'actual' => round((float) ($row['actual'] ?? 0), 2),
            'committed' => round((float) ($row['committed'] ?? 0), 2),
            'variance' => round((float) ($row['available'] ?? 0), 2),
            'utilization_percent' => (float) ($row['utilization_percent'] ?? 0),
            'status' => $row['status'] ?? 'healthy',
        ])->values()->all();

        $expenditures = $postedLines
            ->filter(fn (JournalLine $line) => $line->account?->account_type === 'expense')
            ->map(fn (JournalLine $line) => [
                'date' => $line->journal?->journal_date?->toDateString(),
                'reference' => $line->journal?->reference ?: $line->journal?->journal_number,
                'description' => $line->journal?->description ?: $line->account?->name,
                'donor' => $line->donor?->name,
                'grant' => $line->project?->grantAgreement?->grant_no ?: $line->project?->grantAgreement?->agreement_name,
                'project' => $line->project?->name,
                'amount' => round((float) $line->debit - (float) $line->credit, 2),
                'account' => $line->account?->name,
                'account_code' => $line->account?->code,
                'status' => $line->journal?->status,
            ])->sortByDesc('date')->values()->take(100)->all();

        $deadlineOverdue = collect($deadlines)
            ->filter(fn (array $deadline) => $deadline['status'] === 'overdue' || ($deadline['days_remaining'] !== null && $deadline['days_remaining'] < 0))
            ->count();
        $bankBalances = BankTransaction::query()
            ->selectRaw('bank_account_id, COALESCE(SUM(debit - credit), 0) as balance')
            ->groupBy('bank_account_id')
            ->pluck('balance', 'bank_account_id');
        $linkedBanking = \App\Models\Master\GrantAgreement::query()
            ->with(['bankAccount:id,bank_name,account_number,account_name,currency_id', 'currency:id,code', 'projects:id,grant_agreement_id,code,name,bank_account_id', 'projects.bankAccount:id,bank_name,account_number,account_name,currency_id'])
            ->when($request->filled('grant_agreement_id'), fn (Builder $q) => $q->whereKey($request->integer('grant_agreement_id')))
            ->where(function (Builder $q) {
                $q->whereNotNull('bank_account_id')->orWhereHas('projects', fn (Builder $project) => $project->whereNotNull('bank_account_id'));
            })
            ->get()
            ->flatMap(fn ($grant) => ($grant->projects->isNotEmpty() ? $grant->projects : collect([null]))->map(function ($project) use ($grant, $bankBalances) {
                $bank = $project?->bankAccount ?: $grant->bankAccount;
                return [
                    'project' => $project?->name,
                    'grant' => $grant->grant_no,
                    'grant_name' => $grant->agreement_name,
                    'bank_account_id' => $bank?->id,
                    'banking_source' => $project?->bankAccount ? 'project' : 'grant',
                    'bank_name' => $bank?->bank_name,
                    'account_name' => $bank?->account_name,
                    'account_number' => $bank?->account_number,
                    'currency' => $grant->currency?->code,
                    'balance' => round((float) ($bankBalances[$bank?->id] ?? 0), 2),
                ];
            }))->values()->all();
        return response()->json([
            'success' => true,
            'period' => $period['label'],
            'summary' => [
                'approved_budget' => $totals['approved_budget'],
                'actual' => $totals['actual'],
                'committed' => $totals['committed'],
                'available' => $totals['available'],
                'utilization_percent' => $totals['utilization_percent'],
                'projected_total' => round($totals['actual'] + $totals['committed'], 2),
                'deadline_total' => count($deadlines),
                'deadline_overdue' => (int) $deadlineOverdue,
            ],
            'bva' => $bva,
            'variance' => collect($bva)->sortByDesc(fn (array $row) => abs($row['variance']))->values()->take(20)->all(),
            'projections' => collect($bva)->map(fn (array $row) => [
                'project' => $row['project'],
                'budget' => $row['budget'],
                'projected' => round($row['actual'] + $row['committed'], 2),
                'remaining' => $row['variance'],
            ])->values()->take(20)->all(),
            'expenditures' => $expenditures,
            'deadlines' => $deadlines,
            'banking' => $linkedBanking,
        ]);
    }

    public function reports(Request $request, BudgetMonitoringService $budgetService): JsonResponse
    {
        $period = $this->period($request);
        if (! app(DataScopeService::class)->canAccessAll($request->user())) {
            return $this->personalReports($request, $period);
        }

        $projectId = $request->input('project_id');
        $budgetLineId = $request->input('budget_line_id');
        $budgetRows = $budgetService->summary($request->only(['project_id', 'grant_agreement_id', 'budget_category_id', 'budget_line_id']));
        $postedLines = $this->postedLines($period['start'], $period['end'], $projectId, $budgetLineId);
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
            'recent_transactions' => $budgetLineId
                ? $postedLines->sortByDesc(fn (JournalLine $line) => $line->journal?->journal_date)->take(100)->map(fn (JournalLine $line) => ['date' => $line->journal?->journal_date?->toDateString(), 'reference' => $line->journal?->reference ?: $line->journal?->journal_number, 'description' => $line->line_description ?: $line->journal?->description, 'status' => $line->journal?->status, 'amount' => round((float) $line->debit - (float) $line->credit, 2)])->values()->all()
                : $this->recentTransactions($period['start'], $period['end']),
        ]);
    }

    /**
     * A staff member may have reports.view for their operational report, but
     * must not receive organisation-wide AP, AR, bank, donor, or GL values.
     */
    private function personalReports(Request $request, array $period): JsonResponse
    {
        $expenses = ExpenseRequest::query()
            ->where('requester_id', $request->user()->id)
            ->when($period['start'], fn (Builder $q) => $q->whereDate('request_date', '>=', $period['start']))
            ->when($period['end'], fn (Builder $q) => $q->whereDate('request_date', '<=', $period['end']))
            ->latest('id')
            ->get();

        $total = (float) $expenses->sum('total_amount');
        $rows = $expenses->map(fn (ExpenseRequest $expense) => [
            'request_id' => $expense->id,
            'reference' => $expense->request_number,
            'date' => $expense->request_date?->toDateString(),
            'description' => $expense->description,
            'status' => $expense->status,
            'amount' => (float) $expense->total_amount,
        ])->values();

        return response()->json([
            'success' => true,
            'scope' => 'personal',
            'period' => $period['label'],
            'financial_statement' => ['rows' => [], 'totals_by_type' => []],
            'balance_sheet' => ['rows' => [], 'totals_by_type' => []],
            'budget_vs_actual' => ['totals' => ['approved_budget' => 0, 'actual' => 0, 'committed' => 0, 'available' => 0, 'utilization_percent' => 0], 'rows' => []],
            'ap_aging' => ['buckets' => []],
            'ar_aging' => ['buckets' => []],
            'cash_bank' => ['total_balance' => 0, 'accounts' => []],
            'procurement' => ['summary' => [], 'rows' => []],
            'expense' => ['total' => $total, 'rows' => $rows],
            'recent_transactions' => $rows,
        ]);
    }

    /**
     * Returns cumulative balance sheet balances. Depreciation journals posted by
     * FixedAssetController are included automatically in the asset and expense
     * account balances, so the report always reflects the current net book value.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        if (! app(DataScopeService::class)->canAccessAll($request->user())) {
            return response()->json([
                'success' => true,
                'scope' => 'personal',
                'as_of' => $request->filled('as_of') ? CarbonImmutable::parse($request->string('as_of'))->toDateString() : CarbonImmutable::now()->toDateString(),
                'data' => ['rows' => [], 'totals_by_type' => []],
            ]);
        }

        $asOf = $request->filled('as_of')
            ? CarbonImmutable::parse($request->string('as_of'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return response()->json([
            'success' => true,
            'as_of' => $asOf->toDateString(),
            'data' => $this->balanceSheetData($asOf->toDateString()),
        ]);
    }

    /** Download an official Profit & Loss PDF from posted journal data only. */
    public function profitLossPdf(Request $request): Response
    {
        $this->ensureExportScope($request);
        $period = $this->period($request);
        $rows = $this->financialStatement($this->postedLines($period['start'], $period['end'], $request->input('project_id')))['rows'];
        $this->auditExport($request, 'profit_loss_pdf', count($rows));

        return Pdf::loadView('reports.profit-loss-pdf', [
            'rows' => collect($rows),
            'period_label' => $this->periodLabel($period),
        ])->setPaper('a4')->download('laporan-laba-rugi-'.now()->format('YmdHis').'.pdf');
    }

    /** Download an official balance sheet PDF as of the selected reporting date. */
    public function balanceSheetPdf(Request $request): Response
    {
        $this->ensureExportScope($request);
        $asOf = $request->filled('as_of') ? CarbonImmutable::parse($request->string('as_of'))->toDateString() : CarbonImmutable::now()->toDateString();
        $data = $this->balanceSheetData($asOf);
        $this->auditExport($request, 'balance_sheet_pdf', count($data['accounts']));

        return Pdf::loadView('reports.balance-sheet-pdf', ['data' => $data, 'as_of' => $asOf])
            ->setPaper('a4')->download('neraca-'.now()->format('YmdHis').'.pdf');
    }

    public function forecast(Request $request): JsonResponse
    {
        if (! app(DataScopeService::class)->canAccessAll($request->user())) {
            return response()->json([
                'success' => true,
                'scope' => 'personal',
                'data' => ['months' => [], 'average_monthly_expense' => 0, 'next_month_projection' => 0, 'method' => 'not_available_for_personal_scope'],
            ]);
        }

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

    private function ensureExportScope(Request $request): void
    {
        if (! app(DataScopeService::class)->canAccessAll($request->user())) {
            abort(Response::HTTP_FORBIDDEN, 'Export laporan keuangan organisasi tidak tersedia untuk scope personal.');
        }
    }

    private function auditExport(Request $request, string $format, int $rows): void
    {
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'reports',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => 'EXPORT',
            'entity_type' => self::class,
            'entity_id' => null,
            'previous_values' => null,
            'new_values' => ['format' => $format, 'rows' => $rows],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function periodLabel(array $period): string
    {
        $start = $period['start']?->format('d/m/Y') ?? 'Awal';
        $end = $period['end']?->format('d/m/Y') ?? 'Saat ini';
        return "{$start} s.d. {$end}";
    }

    private function postedLines(?CarbonInterface $start, ?CarbonInterface $end, ?string $projectId = null, ?string $budgetLineId = null)
    {
        return JournalLine::query()
            ->with(['journal:id,journal_number,journal_date,status,reference,description', 'account:id,code,name,account_type,normal_balance', 'donor:id,code,name', 'project:id,code,name,grant_agreement_id', 'project.grantAgreement:id,grant_no,agreement_name'])
            ->whereHas('journal', function (Builder $query) use ($start, $end) {
                $query->where('status', 'posted')
                    ->when($start, fn (Builder $inner) => $inner->whereDate('journal_date', '>=', $start))
                    ->when($end, fn (Builder $inner) => $inner->whereDate('journal_date', '<=', $end));
            })
            ->when($projectId, fn (Builder $query) => $query->where('project_id', $projectId))
            ->when($budgetLineId, fn (Builder $query) => $query->where('budget_line_id', $budgetLineId))
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

        // NGO Statement of Activities: Restricted vs Unrestricted
        $revenueLines = $postedLines->filter(fn (JournalLine $l) => $l->account?->account_type === 'revenue');
        $expenseLines = $postedLines->filter(fn (JournalLine $l) => $l->account?->account_type === 'expense');

        $revUnrestricted = (float) $revenueLines->whereNull('donor_id')->sum(fn ($l) => (float) $l->credit - (float) $l->debit);
        $revRestricted = (float) $revenueLines->whereNotNull('donor_id')->sum(fn ($l) => (float) $l->credit - (float) $l->debit);
        $totalRevenue = $revUnrestricted + $revRestricted;

        $expProgram = (float) $expenseLines->whereNotNull('project_id')->sum(fn ($l) => (float) $l->debit - (float) $l->credit);
        $expSupport = (float) $expenseLines->whereNull('project_id')->sum(fn ($l) => (float) $l->debit - (float) $l->credit);
        $totalExpense = $expProgram + $expSupport;
        $changeInNetAssets = $totalRevenue - $totalExpense;

        // Cash flow statement
        $operatingCash = (float) $postedLines->filter(fn (JournalLine $l) => in_array($l->account?->account_type, ['revenue', 'expense']))
            ->sum(fn (JournalLine $l) => $l->account?->account_type === 'revenue' ? ((float) $l->credit - (float) $l->debit) : -((float) $l->debit - (float) $l->credit));
        $investingCash = (float) $postedLines->filter(fn (JournalLine $l) => str_starts_with((string) $l->account?->code, '1-2'))
            ->sum(fn (JournalLine $l) => -((float) $l->debit - (float) $l->credit));
        $financingCash = (float) $postedLines->filter(fn (JournalLine $l) => in_array($l->account?->account_type, ['liability', 'equity']))
            ->sum(fn (JournalLine $l) => (float) $l->credit - (float) $l->debit);

        return [
            'totals_by_type' => $rows->groupBy('account_type')->map(fn ($items) => round((float) $items->sum('balance'), 2))->all(),
            'rows' => $rows->all(),
            'activities' => [
                'revenue_unrestricted' => round($revUnrestricted, 2),
                'revenue_restricted' => round($revRestricted, 2),
                'total_revenue' => round($totalRevenue, 2),
                'expenses_program' => round($expProgram, 2),
                'expenses_support' => round($expSupport, 2),
                'total_expenses' => round($totalExpense, 2),
                'change_in_net_assets' => round($changeInNetAssets, 2),
            ],
            'cash_flow' => [
                'operating_activities' => round($operatingCash, 2),
                'investing_activities' => round($investingCash, 2),
                'financing_activities' => round($financingCash, 2),
                'net_change_in_cash' => round($operatingCash + $investingCash + $financingCash, 2),
            ],
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

    public function aging(Request $request): JsonResponse
    {
        $asOf = $request->input('as_of') ? CarbonImmutable::parse($request->input('as_of')) : CarbonImmutable::now();
        $ap = $this->apAging($asOf);
        $ar = $this->arAging($asOf);

        return response()->json([
            'success' => true,
            'as_of' => $asOf->toDateString(),
            'ap' => [
                'total_outstanding' => round(array_sum($ap['buckets']), 2),
                'buckets' => $ap['buckets'],
                'rows' => $ap['rows'],
                'by_vendor' => collect($ap['rows'])->groupBy('vendor')->map(function ($items, $vendor) {
                    return [
                        'vendor' => $vendor,
                        'total' => round((float) $items->sum('outstanding'), 2),
                        'current' => round((float) $items->where('bucket', 'current')->sum('outstanding'), 2),
                        'bucket_1_30' => round((float) $items->where('bucket', '1_30')->sum('outstanding'), 2),
                        'bucket_31_60' => round((float) $items->where('bucket', '31_60')->sum('outstanding'), 2),
                        'bucket_61_90' => round((float) $items->where('bucket', '61_90')->sum('outstanding'), 2),
                        'over_90' => round((float) $items->where('bucket', 'over_90')->sum('outstanding'), 2),
                        'count' => $items->count(),
                    ];
                })->values()->sortByDesc('total')->values()->all(),
            ],
            'ar' => [
                'total_outstanding' => round(array_sum($ar['buckets']), 2),
                'buckets' => $ar['buckets'],
                'rows' => $ar['rows'],
                'by_customer' => collect($ar['rows'])->groupBy('customer')->map(function ($items, $customer) {
                    return [
                        'customer' => $customer,
                        'total' => round((float) $items->sum('outstanding'), 2),
                        'current' => round((float) $items->where('bucket', 'current')->sum('outstanding'), 2),
                        'bucket_1_30' => round((float) $items->where('bucket', '1_30')->sum('outstanding'), 2),
                        'bucket_31_60' => round((float) $items->where('bucket', '31_60')->sum('outstanding'), 2),
                        'bucket_61_90' => round((float) $items->where('bucket', '61_90')->sum('outstanding'), 2),
                        'over_90' => round((float) $items->where('bucket', 'over_90')->sum('outstanding'), 2),
                        'count' => $items->count(),
                    ];
                })->values()->sortByDesc('total')->values()->all(),
            ],
        ]);
    }

    public function cashBankReport(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $accounts = \App\Models\Master\BankAccount::query()->with('currency:id,code')->get()->map(function ($acc) use ($period) {
            $opening = (float) BankTransaction::query()
                ->where('bank_account_id', $acc->id)
                ->when($period['start'], fn ($q) => $q->whereDate('transaction_date', '<', $period['start']))
                ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
                ->value('balance');
            $periodDebits = (float) BankTransaction::query()
                ->where('bank_account_id', $acc->id)
                ->when($period['start'], fn ($q) => $q->whereDate('transaction_date', '>=', $period['start']))
                ->when($period['end'], fn ($q) => $q->whereDate('transaction_date', '<=', $period['end']))
                ->sum('debit');
            $periodCredits = (float) BankTransaction::query()
                ->where('bank_account_id', $acc->id)
                ->when($period['start'], fn ($q) => $q->whereDate('transaction_date', '>=', $period['start']))
                ->when($period['end'], fn ($q) => $q->whereDate('transaction_date', '<=', $period['end']))
                ->sum('credit');
            $closing = $opening + $periodDebits - $periodCredits;
            $unreconciled = BankTransaction::query()
                ->where('bank_account_id', $acc->id)
                ->whereNotIn('status', ['reconciled', 'matched', 'excluded'])
                ->count();

            return [
                'id' => $acc->id,
                'bank_name' => $acc->bank_name,
                'account_name' => $acc->account_name,
                'account_number' => $acc->account_number,
                'currency' => $acc->currency?->code ?? 'IDR',
                'is_active' => (bool) $acc->is_active,
                'opening_balance' => round($opening, 2),
                'total_inflow' => round($periodDebits, 2),
                'total_outflow' => round($periodCredits, 2),
                'closing_balance' => round($closing, 2),
                'unreconciled_transactions' => $unreconciled,
            ];
        });

        $recentTransactions = BankTransaction::query()
            ->with('bankAccount:id,bank_name,account_number')
            ->when($period['start'], fn ($q) => $q->whereDate('transaction_date', '>=', $period['start']))
            ->when($period['end'], fn ($q) => $q->whereDate('transaction_date', '<=', $period['end']))
            ->latest('transaction_date')
            ->latest('id')
            ->take(50)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'date' => $tx->transaction_date?->toDateString(),
                'bank' => $tx->bankAccount?->bank_name,
                'reference' => $tx->reference,
                'description' => $tx->description,
                'debit' => (float) $tx->debit,
                'credit' => (float) $tx->credit,
                'status' => $tx->status,
            ]);

        return response()->json([
            'success' => true,
            'period' => $period['label'],
            'total_liquidity' => round($accounts->sum('closing_balance'), 2),
            'total_inflow' => round($accounts->sum('total_inflow'), 2),
            'total_outflow' => round($accounts->sum('total_outflow'), 2),
            'unreconciled_count' => $accounts->sum('unreconciled_transactions'),
            'accounts' => $accounts,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    public function drilldown(Request $request): JsonResponse
    {
        $accountId = $request->input('account_id');
        $projectId = $request->input('project_id');
        $donorId = $request->input('donor_id');
        $budgetLineId = $request->input('budget_line_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = JournalLine::query()
            ->with([
                'journal:id,journal_number,journal_date,status,reference,description',
                'account:id,code,name,account_type',
                'donor:id,code,name',
                'project:id,code,name',
                'department:id,name',
            ])
            ->whereHas('journal', function (Builder $q) use ($startDate, $endDate) {
                $q->where('status', 'posted')
                    ->when($startDate, fn ($inner) => $inner->whereDate('journal_date', '>=', $startDate))
                    ->when($endDate, fn ($inner) => $inner->whereDate('journal_date', '<=', $endDate));
            });

        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($donorId) {
            $query->where('donor_id', $donorId);
        }
        if ($budgetLineId) {
            $query->where('budget_line_id', $budgetLineId);
        }

        $lines = $query->orderByDesc('id')->take(200)->get()->map(function (JournalLine $line) {
            return [
                'id' => $line->id,
                'journal_id' => $line->journal_id,
                'journal_number' => $line->journal?->journal_number,
                'journal_date' => $line->journal?->journal_date?->toDateString(),
                'reference' => $line->journal?->reference ?: $line->journal?->journal_number,
                'description' => $line->line_description ?: $line->journal?->description,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'account_type' => $line->account?->account_type,
                'project_name' => $line->project?->name,
                'donor_name' => $line->donor?->name,
                'debit' => round((float) $line->debit, 2),
                'credit' => round((float) $line->credit, 2),
                'net' => round((float) $line->debit - (float) $line->credit, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $lines->count(),
            'total_debit' => round($lines->sum('debit'), 2),
            'total_credit' => round($lines->sum('credit'), 2),
            'net_total' => round($lines->sum('net'), 2),
            'rows' => $lines,
        ]);
    }
}

