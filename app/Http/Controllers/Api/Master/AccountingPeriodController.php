<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\AccountingPeriod;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalLine;
use App\Models\Finance\BankTransaction;
use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\TaxTransaction;
use App\Models\Procurement\SupplierInvoice;
use App\Models\Expense\ExpenseRequest;
use App\Models\Asset\FixedAsset;
use App\Models\Accounting\PrepaidExpense;
use App\Http\Requests\Master\StoreAccountingPeriodRequest;
use App\Http\Requests\Master\UpdateAccountingPeriodRequest;
use App\Http\Resources\Master\AccountingPeriodResource;
use Illuminate\Http\Request;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Validation\ValidationException;

class AccountingPeriodController extends BaseMasterController
{
    protected string $modelClass = AccountingPeriod::class;
    protected string $resourceClass = AccountingPeriodResource::class;
    protected string $storeRequestClass = StoreAccountingPeriodRequest::class;
    protected string $updateRequestClass = UpdateAccountingPeriodRequest::class;
    protected array $searchableColumns = ['name'];
    protected array $defaultWith = ['fiscalYear'];

    public function close(Request $request, $id, ApprovalWorkflowService $workflow)
    {
        $period = AccountingPeriod::findOrFail($id);
        if ($period->status === 'closed') {
            throw ValidationException::withMessages(['status' => 'Accounting period sudah ditutup.']);
        }
        $readiness = $this->buildReadiness($period);
        if (! $readiness['can_close']) {
            return response()->json([
                'success' => false,
                'message' => 'Periode belum siap ditutup. Selesaikan seluruh blocker accounting, bank, dan donor/grant.',
                'data' => $readiness,
            ], 422);
        }
        $approval = $workflow->approve('period_closing', $period, $request->user(), $request->input('notes'));
        if (! $approval['managed']) {
            $workflow->start('period_closing', $period, 0, $request->user()->id);
            $approval = $workflow->approve('period_closing', $period, $request->user(), $request->input('notes'));
        }
        if ($approval['managed'] && ! $approval['completed']) {
            return response()->json(['success' => true, 'message' => "Penutupan periode menunggu approver level {$approval['next_level']}.", 'data' => $period]);
        }
        $period->update(['status' => 'closed', 'is_active' => false]);
        return response()->json(['success' => true, 'message' => 'Accounting period berhasil ditutup.', 'data' => [...$period->fresh()->toArray(), 'closing_readiness' => $readiness]]);
    }

    public function readiness($id)
    {
        $period = AccountingPeriod::with('fiscalYear')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->buildReadiness($period),
        ]);
    }

    public function reopen(Request $request, $id)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $period = AccountingPeriod::findOrFail($id);
        $period->update(['status' => 'open', 'is_active' => true]);
        return response()->json(['success' => true, 'message' => 'Accounting period dibuka kembali.', 'data' => [...$period->fresh()->toArray(), 'reopen_reason' => $data['reason'] ?? null]]);
    }

    private function buildReadiness(AccountingPeriod $period): array
    {
        $start = $period->start_date?->toDateString();
        $end = $period->end_date?->toDateString();
        $journals = Journal::withoutGlobalScope('fiscal_year_context')
            ->whereBetween('journal_date', [$start, $end])
            ->when($period->fiscal_year_id, fn ($query) => $query->where('fiscal_year_id', $period->fiscal_year_id));
        $unposted = (clone $journals)->whereIn('status', ['draft', 'submitted', 'reviewed', 'returned_for_revision'])->count();
        $posted = (clone $journals)->where('status', 'posted')->count();

        $grantExpenseLines = JournalLine::query()
            ->with([
                'account:id,account_type',
                'grantAgreement:id,donor_id,relation_no',
                'project:id,grant_agreement_id,relation_no',
                'project.grantAgreement:id,donor_id,relation_no',
                'budgetLine:id,grant_agreement_id,project_id,relation_no',
                'budgetLine.grantAgreement:id,donor_id,relation_no',
            ])
            ->whereHas('journal', fn ($query) => $query->withoutGlobalScope('fiscal_year_context')
                ->where('status', 'posted')
                ->whereBetween('journal_date', [$start, $end])
                ->when($period->fiscal_year_id, fn ($journal) => $journal->where('fiscal_year_id', $period->fiscal_year_id)))
            ->whereHas('account', fn ($query) => $query->withoutGlobalScope('fiscal_year_context')->where('account_type', 'expense'))
            ->get()
            ->filter(fn (JournalLine $line) => $line->donor_id
                || $line->grant_agreement_id
                || $line->project?->grant_agreement_id
                || $line->budgetLine?->grant_agreement_id);

        $dimensions = $grantExpenseLines->map(function (JournalLine $line) {
            $grant = $line->grantAgreement ?: $line->project?->grantAgreement ?: $line->budgetLine?->grantAgreement;
            return [
                'donor_id' => $line->donor_id ?: $grant?->donor_id,
                'grant_agreement_id' => $line->grant_agreement_id ?: $line->project?->grant_agreement_id ?: $line->budgetLine?->grant_agreement_id,
                'project_id' => $line->project_id ?: $line->budgetLine?->project_id,
                'budget_line_id' => $line->budget_line_id,
                'relation_no' => $line->relation_no ?: $line->project?->relation_no ?: $line->budgetLine?->relation_no ?: $grant?->relation_no,
            ];
        });
        $incompleteGrantLines = $dimensions->filter(fn (array $dimension) => ! $dimension['donor_id']
            || ! $dimension['grant_agreement_id']
            || ! $dimension['project_id']
            || ! $dimension['budget_line_id']
            || ! $dimension['relation_no'])->count();

        $unreconciledBank = BankTransaction::withoutGlobalScope('fiscal_year_context')
            ->whereBetween('transaction_date', [$start, $end])
            ->when($period->fiscal_year_id, fn ($query) => $query->where('fiscal_year_id', $period->fiscal_year_id))
            ->whereNotIn('status', ['matched', 'reconciled'])
            ->count();

        $pendingAp = SupplierInvoice::withoutGlobalScope('fiscal_year_context')
            ->whereBetween('invoice_date', [$start, $end])
            ->when($period->fiscal_year_id, fn ($query) => $query->where('fiscal_year_id', $period->fiscal_year_id))
            ->whereIn('status', ['draft', 'matched', 'approved'])->count();
        $pendingAr = CustomerInvoice::withoutGlobalScope('fiscal_year_context')
            ->whereBetween('invoice_date', [$start, $end])
            ->when($period->fiscal_year_id, fn ($query) => $query->where('fiscal_year_id', $period->fiscal_year_id))
            ->where('status', 'draft')->count();
        $pendingExpenses = ExpenseRequest::withoutGlobalScope('fiscal_year_context')
            ->whereBetween('request_date', [$start, $end])
            ->when($period->fiscal_year_id, fn ($query) => $query->where('fiscal_year_id', $period->fiscal_year_id))
            ->whereIn('status', ['draft', 'submitted', 'approved'])->count();
        $pendingTaxes = TaxTransaction::withoutGlobalScope('fiscal_year_context')
            ->whereBetween('transaction_date', [$start, $end])
            ->when($period->fiscal_year_id, fn ($query) => $query->where('fiscal_year_id', $period->fiscal_year_id))
            ->where('status', 'draft')->count();
        $pendingSubledgers = $pendingAp + $pendingAr + $pendingExpenses + $pendingTaxes;

        $draftAssets = FixedAsset::withoutGlobalScope('fiscal_year_context')
            ->whereBetween('acquisition_date', [$start, $end])
            ->when($period->fiscal_year_id, fn ($query) => $query->where('fiscal_year_id', $period->fiscal_year_id))
            ->where('status', 'draft')->count();
        $missingDepreciation = FixedAsset::withoutGlobalScope('fiscal_year_context')
            ->whereIn('status', ['active', 'transferred'])
            ->where('depreciation_method', '!=', 'none')
            ->whereDate('in_service_date', '<=', $end)
            ->whereColumn('net_book_value', '>', 'residual_value')
            ->when($period->fiscal_year_id, fn ($query) => $query->where('fiscal_year_id', $period->fiscal_year_id))
            ->whereDoesntHave('depreciations', fn ($query) => $query->whereBetween('depreciation_date', [$start, $end]))
            ->count();
        $prepaids = PrepaidExpense::query()->with('amortizations')->where('status', 'active')->whereDate('start_date', '<=', $end)->get();
        $unrecognizedPrepaids = $prepaids->whereNull('recognition_journal_id')->count();
        $missingAmortizations = $prepaids->filter(function (PrepaidExpense $prepaid) use ($start, $end) {
            if (! $prepaid->recognition_journal_id) return false;
            return collect(range(1, (int) $prepaid->periods))->contains(function (int $number) use ($prepaid, $start, $end) {
                $date = $prepaid->start_date->copy()->addMonths($number - 1)->toDateString();
                return $date >= $start && $date <= $end && ! $prepaid->amortizations->contains('period', $number);
            });
        })->count();
        $pendingAssetsPrepaids = $draftAssets + $missingDepreciation + $unrecognizedPrepaids + $missingAmortizations;

        $checks = [
            ['key' => 'accounting', 'label' => 'Accounting Journals', 'status' => $unposted === 0 ? 'ready' : 'blocked', 'blocking_count' => $unposted, 'message' => $unposted === 0 ? "{$posted} jurnal posted." : "{$unposted} jurnal belum posted."],
            ['key' => 'subledgers', 'label' => 'AP, AR, Expense & Tax', 'status' => $pendingSubledgers === 0 ? 'ready' : 'blocked', 'blocking_count' => $pendingSubledgers, 'message' => $pendingSubledgers === 0 ? 'Seluruh subledger periode sudah final.' : "{$pendingSubledgers} transaksi subledger belum final."],
            ['key' => 'assets_prepaids', 'label' => 'Fixed Assets & Prepaid', 'status' => $pendingAssetsPrepaids === 0 ? 'ready' : 'blocked', 'blocking_count' => $pendingAssetsPrepaids, 'message' => $pendingAssetsPrepaids === 0 ? 'Kapitalisasi, depresiasi, dan amortisasi sudah lengkap.' : "{$pendingAssetsPrepaids} proses aset/prepaid belum lengkap."],
            ['key' => 'donor_grant', 'label' => 'Donor & Grant Dimensions', 'status' => $incompleteGrantLines === 0 ? 'ready' : 'blocked', 'blocking_count' => $incompleteGrantLines, 'message' => $incompleteGrantLines === 0 ? $dimensions->count().' baris grant lengkap.' : "{$incompleteGrantLines} baris grant belum lengkap dimensinya."],
            ['key' => 'bank', 'label' => 'Bank Reconciliation', 'status' => $unreconciledBank === 0 ? 'ready' : 'blocked', 'blocking_count' => $unreconciledBank, 'message' => $unreconciledBank === 0 ? 'Seluruh mutasi bank sudah direkonsiliasi.' : "{$unreconciledBank} mutasi bank belum direkonsiliasi."],
        ];

        return [
            'period_id' => $period->id,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period' => ['start_date' => $start, 'end_date' => $end],
            'can_close' => collect($checks)->every(fn (array $check) => $check['status'] === 'ready'),
            'checks' => $checks,
            'accounting' => ['posted_journals' => $posted, 'unposted_journals' => $unposted],
            'subledgers' => ['pending_total' => $pendingSubledgers, 'accounts_payable' => $pendingAp, 'accounts_receivable' => $pendingAr, 'expense_requests' => $pendingExpenses, 'tax_transactions' => $pendingTaxes],
            'assets_prepaids' => ['pending_total' => $pendingAssetsPrepaids, 'draft_assets' => $draftAssets, 'missing_depreciation' => $missingDepreciation, 'unrecognized_prepaids' => $unrecognizedPrepaids, 'missing_amortizations' => $missingAmortizations],
            'donor_grant' => [
                'expense_lines' => $dimensions->count(),
                'incomplete_lines' => $incompleteGrantLines,
                'donors' => $dimensions->pluck('donor_id')->filter()->unique()->count(),
                'grants' => $dimensions->pluck('grant_agreement_id')->filter()->unique()->count(),
                'projects' => $dimensions->pluck('project_id')->filter()->unique()->count(),
                'relation_numbers' => $dimensions->pluck('relation_no')->filter()->unique()->values()->all(),
            ],
            'bank' => ['unreconciled_transactions' => $unreconciledBank],
        ];
    }
}
