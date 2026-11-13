<?php

namespace App\Http\Controllers\Api\Asset;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Asset\FixedAsset;
use App\Models\Master\AssetCategory;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Project;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Rbac\DataScopeService;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FixedAssetController extends Controller
{
    private array $with = ['category:id,code,name,useful_life_months,depreciation_method,is_non_depreciable,asset_gl_account_id,depreciation_gl_account_id,accumulated_gl_account_id', 'category.assetGlAccount:id,code,name', 'category.depreciationGlAccount:id,code,name', 'category.accumulatedGlAccount:id,code,name', 'assetGlAccount:id,code,name', 'depreciationGlAccount:id,code,name', 'accumulatedGlAccount:id,code,name', 'vendor:id,code,name', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'supplierInvoice:id,invoice_number,status,journal_id,total_amount', 'donor:id,code,name', 'program:id,code,name', 'project:id,code,relation_no,name,grant_agreement_id,program_id,bank_account_id', 'project.grantAgreementUnscoped:id,donor_id,grant_no,relation_no,agreement_name,bank_account_id', 'project.bankAccount:id,bank_name,account_number,gl_account_id', 'custodian:id,employee_id_number,name', 'disposalBankAccount:id,bank_name,account_number,gl_account_id', 'depreciations'];

    public function index(Request $request): JsonResponse
    {
        $query = FixedAsset::query()->with($this->with);
        app(\App\Services\Rbac\DataScopeService::class)->applyScope(
            $query,
            $request->user(),
            'created_by',
            'project_id',
            'organization_id',
            []
        );

        $assets = $query
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('project_id'), fn (Builder $q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('relation_no'), fn (Builder $q) => $q->whereHas('project', fn (Builder $project) => $project->withoutGlobalScope('fiscal_year_context')->where('relation_no', trim((string) $request->query('relation_no')))))
            ->latest('id')
            ->get();

        $totals = [
            'acquisition_cost' => round((float) $assets->sum('acquisition_cost'), 2),
            'accumulated_depreciation' => round((float) $assets->sum('accumulated_depreciation'), 2),
            'net_book_value' => round((float) $assets->sum('net_book_value'), 2),
            'assets' => $assets->count(),
            // Until a dedicated review_due_date is introduced, draft assets and
            // active/transferred assets not updated for one year form the review queue.
            'due_for_review' => $assets->filter(fn (FixedAsset $asset) =>
                $asset->status === 'draft'
                || (in_array($asset->status, ['active', 'transferred'], true)
                    && $asset->updated_at?->lt(now()->subYear()))
            )->count(),
        ];

        return response()->json(['success' => true, 'totals' => $totals, 'data' => $assets->map(fn (FixedAsset $asset) => $this->format($asset))]);
    }

    public function export(Request $request)
    {
        $query = FixedAsset::query();
        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'project_id', 'organization_id', []);
        $assets = $query->latest('id')->get();
        $filename = 'fixed-assets-'.now()->format('Ymd_His').'.csv';
        return response()->streamDownload(function () use ($assets) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Asset Code', 'Asset Name', 'Relation No', 'Project ID', 'Category ID', 'Acquisition Date', 'Purchase Cost', 'Useful Life (Months)', 'Depreciation Method', 'Status', 'Description']);
            foreach ($assets as $asset) fputcsv($handle, [$asset->asset_code, $asset->asset_name, $asset->project?->relation_no, $asset->project_id, $asset->asset_category_id, $asset->acquisition_date?->toDateString(), $asset->acquisition_cost, $asset->useful_life_months, $asset->depreciation_method, $asset->status, $asset->description]);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reconciliation(Request $request): JsonResponse
    {
        $register = FixedAsset::query()
            ->whereNotIn('status', ['disposed'])
            ->selectRaw('COALESCE(SUM(acquisition_cost), 0) as cost, COALESCE(SUM(accumulated_depreciation), 0) as accumulated, COALESCE(SUM(net_book_value), 0) as nbv')
            ->first();
        $categories = AssetCategory::query()->get(['asset_gl_account_id', 'accumulated_gl_account_id']);
        $assetAccountIds = $categories->pluck('asset_gl_account_id')->filter()->unique()->values();
        $accumulatedAccountIds = $categories->pluck('accumulated_gl_account_id')->filter()->unique()->values();
        $postedLines = \App\Models\Accounting\JournalLine::query()->whereHas('journal', fn ($q) => $q->where('status', 'posted'));
        $assetGl = (clone $postedLines)->when($assetAccountIds->isNotEmpty(), fn ($q) => $q->whereIn('account_id', $assetAccountIds))->when($assetAccountIds->isEmpty(), fn ($q) => $q->whereRaw('1 = 0'))->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')->value('balance');
        $accumulatedGl = (clone $postedLines)->when($accumulatedAccountIds->isNotEmpty(), fn ($q) => $q->whereIn('account_id', $accumulatedAccountIds))->when($accumulatedAccountIds->isEmpty(), fn ($q) => $q->whereRaw('1 = 0'))->selectRaw('COALESCE(SUM(credit - debit), 0) as balance')->value('balance');
        $registerCost = round((float) $register->cost, 2);
        $registerAccumulated = round((float) $register->accumulated, 2);
        $glCost = round((float) $assetGl, 2);
        $glAccumulated = round((float) $accumulatedGl, 2);
        return response()->json(['success' => true, 'data' => [
            'status' => abs($registerCost - $glCost) < 0.01 && abs($registerAccumulated - $glAccumulated) < 0.01 ? 'reconciled' : 'difference_found',
            'register' => ['cost' => $registerCost, 'accumulated_depreciation' => $registerAccumulated, 'net_book_value' => round((float) $register->nbv, 2)],
            'gl' => ['cost' => $glCost, 'accumulated_depreciation' => $glAccumulated, 'net_book_value' => round($glCost - $glAccumulated, 2)],
            'difference' => ['cost' => round($registerCost - $glCost, 2), 'accumulated_depreciation' => round($registerAccumulated - $glAccumulated, 2)],
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $category = isset($payload['asset_category_id']) ? AssetCategory::find($payload['asset_category_id']) : null;
        $cost = round((float) $payload['acquisition_cost'], 2);

        $asset = FixedAsset::create([
            ...$payload,
            'asset_code' => $payload['asset_code'] ?? $this->nextAssetCode(),
            'useful_life_months' => $payload['useful_life_months'] ?? $category?->useful_life_months ?? 60,
            'depreciation_method' => ($payload['is_non_depreciable'] ?? false) ? 'none' : ($payload['depreciation_method'] ?? $category?->depreciation_method ?? 'straight_line'),
            'is_non_depreciable' => (bool) ($payload['is_non_depreciable'] ?? $category?->is_non_depreciable ?? false),
            'accumulated_depreciation' => 0,
            'net_book_value' => $cost,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ])->load($this->with);

        return response()->json(['success' => true, 'message' => 'Fixed asset berhasil dibuat.', 'data' => $this->format($asset)], Response::HTTP_CREATED);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']]);
        $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet(); $rows = $sheet->toArray(null, true, true, true); $headers = array_map(fn ($v) => strtolower(trim((string) $v)), array_shift($rows) ?: []); $map = [];
        foreach ($headers as $col => $header) $map[preg_replace('/[^a-z0-9]+/', '_', $header)] = $col;
        $get = fn ($row, $keys) => collect($keys)->first(fn ($key) => isset($map[$key])) !== null ? ($row[$map[collect($keys)->first(fn ($key) => isset($map[$key]))]] ?? null) : null;
        $created = 0; $errors = [];
        foreach ($rows as $number => $row) { if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) continue; try { $this->store(Request::create('/', 'POST', ['asset_code' => $get($row, ['asset_code']), 'asset_name' => $get($row, ['asset_name']), 'description' => $get($row, ['description']), 'asset_category_id' => $get($row, ['asset_category_id', 'category_id']) ?: null, 'acquisition_date' => $get($row, ['acquisition_date']), 'in_service_date' => $get($row, ['in_service_date']) ?: null, 'acquisition_cost' => (float) $get($row, ['acquisition_cost', 'purchase_cost']), 'residual_value' => (float) ($get($row, ['residual_value']) ?: 0), 'useful_life_months' => (int) ($get($row, ['useful_life_months']) ?: 60), 'depreciation_method' => $get($row, ['depreciation_method']) ?: 'straight_line', 'is_non_depreciable' => filter_var($get($row, ['is_non_depreciable']), FILTER_VALIDATE_BOOLEAN), 'asset_gl_account_id' => $get($row, ['asset_gl_account_id']) ?: null, 'depreciation_gl_account_id' => $get($row, ['depreciation_gl_account_id']) ?: null, 'accumulated_gl_account_id' => $get($row, ['accumulated_gl_account_id']) ?: null, 'donor_id' => $get($row, ['donor_id']) ?: null, 'program_id' => $get($row, ['program_id']) ?: null, 'project_id' => $get($row, ['project_id']) ?: null, 'vendor_id' => $get($row, ['vendor_id']) ?: null, 'location' => $get($row, ['location']), 'notes' => $get($row, ['notes'])])); $created++; } catch (\Throwable $e) { $errors[] = 'Baris '.($number + 2).': '.$e->getMessage(); } }
        return response()->json(['success' => $created > 0, 'message' => "{$created} fixed asset berhasil diimport, ".count($errors).' gagal.', 'created' => $created, 'errors' => $errors]);
    }

    public function update(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        if ($fixedAsset->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Detail asset hanya dapat diubah saat masih draft.']);
        }

        $payload = $this->validatePayload($request, true, $fixedAsset->id);
        $fixedAsset->update($payload + ['updated_by' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'message' => 'Detail fixed asset berhasil diperbarui.',
            'data' => $this->format($fixedAsset->fresh($this->with)),
        ]);
    }

    public function submitCapitalization(Request $request, FixedAsset $fixedAsset, ApprovalWorkflowService $workflow): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        if ($fixedAsset->status !== 'draft') throw ValidationException::withMessages(['status' => 'Hanya asset draft yang dapat diajukan kapitalisasi.']);
        $run = $workflow->start('asset', $fixedAsset, (float) $fixedAsset->acquisition_cost, $request->user()->id);
        return response()->json(['success' => true, 'workflow_managed' => (bool) $run, 'message' => $run ? 'Asset submitted to the capitalization Approval Matrix.' : 'No active asset Approval Matrix; the asset is ready for capitalization.', 'data' => $this->format($fixedAsset->fresh($this->with))]);
    }

    public function capitalize(Request $request, FixedAsset $fixedAsset, ApprovalWorkflowService $workflow): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        app(AccountingPeriodService::class)->ensureOpen($fixedAsset->acquisition_date->toDateString(), 'acquisition_date');
        if ($fixedAsset->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Asset hanya dapat dikapitalisasi dari draft.']);
        }
        $approval = $workflow->approve('asset', $fixedAsset, $request->user(), $request->input('notes'));
        if (! $approval['managed'] && $workflow->requiresApproval('asset', $fixedAsset, (float) $fixedAsset->acquisition_cost)) throw ValidationException::withMessages(['approval' => 'The asset must be submitted to the Approval Matrix before capitalization.']);
        if ($approval['managed'] && ! $approval['completed']) return response()->json(['success' => true, 'message' => "Approval asset tahap selesai. Menunggu approver level {$approval['next_level']}.", 'data' => $this->format($fixedAsset->fresh($this->with))]);

        $category = $fixedAsset->category;
        $assetAccount = $fixedAsset->assetGlAccount ?: $category?->assetGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('is_header', false)->first();
        $clearingAccount = ChartOfAccount::query()->where('account_type', 'liability')->where('is_header', false)->first()
            ?: ChartOfAccount::query()->where('account_type', 'equity')->where('is_header', false)->first();
        if (! $assetAccount || ! $clearingAccount) {
            throw ValidationException::withMessages(['account' => 'Asset and clearing/liability accounts must be configured.']);
        }

        $sourceJournal = $fixedAsset->supplierInvoice?->journal_id
            ? Journal::with('lines')->find($fixedAsset->supplierInvoice->journal_id)
            : null;
        if ($fixedAsset->supplierInvoice && ! in_array($fixedAsset->supplierInvoice->status, ['posted', 'paid'], true)) {
            throw ValidationException::withMessages(['supplier_invoice_id' => 'The supplier invoice must be posted before the asset is capitalized.']);
        }

        $asset = DB::transaction(function () use ($fixedAsset, $assetAccount, $clearingAccount, $request, $sourceJournal) {
            $journal = Journal::create([
                'fiscal_year_id' => $fixedAsset->fiscal_year_id,
                'journal_number' => 'FA-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $fixedAsset->acquisition_date,
                'journal_type' => 'manual',
                'reference' => $fixedAsset->asset_code,
                'description' => 'Asset capitalization '.$fixedAsset->asset_name,
                'status' => 'posted',
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
                'source_type' => 'fixed_asset', 'source_id' => $fixedAsset->id,
            ]);
            $dimensions = $this->journalDimensions($fixedAsset);
            $journal->lines()->create(['account_id' => $assetAccount->id, ...$dimensions, 'line_description' => 'Capitalized asset', 'debit' => $fixedAsset->acquisition_cost, 'credit' => 0, 'line_order' => 1]);

            if ($sourceJournal && $sourceJournal->lines->sum('debit') > 0) {
                $sourceDebit = (float) $sourceJournal->lines->sum('debit');
                $lineOrder = 2;
                foreach ($sourceJournal->lines->where('debit', '>', 0) as $sourceLine) {
                    $credit = round((float) $fixedAsset->acquisition_cost * ((float) $sourceLine->debit / $sourceDebit), 2);
                    if ($credit <= 0) continue;
                    $journal->lines()->create(['account_id' => $sourceLine->account_id, 'donor_id' => $sourceLine->donor_id ?: $dimensions['donor_id'], 'program_id' => $sourceLine->program_id ?: $dimensions['program_id'], 'project_id' => $sourceLine->project_id ?: $dimensions['project_id'], 'grant_agreement_id' => $sourceLine->grant_agreement_id ?: $dimensions['grant_agreement_id'], 'relation_no' => $sourceLine->relation_no ?: $dimensions['relation_no'], 'budget_line_id' => $sourceLine->budget_line_id, 'department_id' => $sourceLine->department_id, 'line_description' => 'Reclassify AP source expense to fixed asset', 'debit' => 0, 'credit' => $credit, 'line_order' => $lineOrder++]);
                }
            } else {
                $journal->lines()->create(['account_id' => $clearingAccount->id, ...$dimensions, 'line_description' => 'Asset clearing/source', 'debit' => 0, 'credit' => $fixedAsset->acquisition_cost, 'line_order' => 2]);
            }

            $fixedAsset->update(['status' => 'active', 'journal_id' => $journal->id, 'capitalization_journal_id' => $journal->id, 'capitalized_by' => $request->user()->id, 'capitalized_at' => now(), 'in_service_date' => $fixedAsset->in_service_date ?: $fixedAsset->acquisition_date]);

            return $fixedAsset->fresh($this->with);
        });

        return response()->json(['success' => true, 'message' => 'Asset berhasil dikapitalisasi.', 'data' => $this->format($asset)]);
    }

    public function depreciate(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        if (! in_array($fixedAsset->status, ['active', 'transferred'], true)) {
            throw ValidationException::withMessages(['status' => 'The asset must be active or transferred before depreciation.']);
        }
        if ($fixedAsset->depreciation_method === 'none') {
            throw ValidationException::withMessages(['depreciation_method' => 'Asset category tidak menggunakan depresiasi.']);
        }
        $data = $request->validate(['depreciation_date' => ['required', 'date'], 'amount' => ['nullable', 'numeric', 'min:0.01']]);
        app(AccountingPeriodService::class)->ensureOpen($data['depreciation_date'], 'depreciation_date');
        $category = $fixedAsset->category;
        $depreciationAccount = $fixedAsset->depreciationGlAccount ?: $category?->depreciationGlAccount ?: ChartOfAccount::query()->where('account_type', 'expense')->where('is_header', false)->first();
        $accumulatedAccount = $fixedAsset->accumulatedGlAccount ?: $category?->accumulatedGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('normal_balance', 'credit')->where('is_header', false)->first();
        if (! $depreciationAccount || ! $accumulatedAccount) {
            throw ValidationException::withMessages(['account' => 'COA depreciation dan accumulated depreciation harus tersedia.']);
        }

        $remaining = round((float) $fixedAsset->net_book_value, 2);
        $lifeMonths = max(1, (int) $fixedAsset->useful_life_months);
        $depreciableBase = max(0, (float) $fixedAsset->acquisition_cost - (float) $fixedAsset->residual_value);
        $remainingDepreciable = max(0, $depreciableBase - (float) $fixedAsset->accumulated_depreciation);
        $monthly = $data['amount'] ?? ($fixedAsset->depreciation_method === 'declining_balance'
            ? round($remaining * (2 / $lifeMonths), 2)
            : round($depreciableBase / $lifeMonths, 2));
        $amount = min($monthly, $remainingDepreciable);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Net book value sudah habis.']);
        }

        $asset = DB::transaction(function () use ($fixedAsset, $data, $amount, $depreciationAccount, $accumulatedAccount) {
            $journal = Journal::create([
                'fiscal_year_id' => $fixedAsset->fiscal_year_id,
                'journal_number' => 'DEP-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $data['depreciation_date'],
                'journal_type' => 'adjustment',
                'reference' => $fixedAsset->asset_code,
                'description' => 'Depreciation '.$fixedAsset->asset_name,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
                'source_type' => 'fixed_asset_depreciation', 'source_id' => $fixedAsset->id,
            ]);
            $dimensions = $this->journalDimensions($fixedAsset);
            $journal->lines()->create(['account_id' => $depreciationAccount->id, ...$dimensions, 'line_description' => 'Depreciation expense', 'debit' => $amount, 'credit' => 0, 'line_order' => 1]);
            $journal->lines()->create(['account_id' => $accumulatedAccount->id, ...$dimensions, 'line_description' => 'Accumulated depreciation', 'debit' => 0, 'credit' => $amount, 'line_order' => 2]);

            $accumulated = round((float) $fixedAsset->accumulated_depreciation + $amount, 2);
            $nbv = max((float) $fixedAsset->residual_value, round((float) $fixedAsset->acquisition_cost - $accumulated - (float) $fixedAsset->impairment_amount, 2));
            $fixedAsset->depreciations()->create(['depreciation_date' => $data['depreciation_date'], 'amount' => $amount, 'accumulated_depreciation' => $accumulated, 'net_book_value' => $nbv, 'journal_id' => $journal->id, 'created_by' => request()->user()->id]);
            $fixedAsset->update(['accumulated_depreciation' => $accumulated, 'net_book_value' => $nbv]);

            return $fixedAsset->fresh($this->with);
        });

        return response()->json(['success' => true, 'message' => 'Depresiasi asset berhasil diposting.', 'data' => $this->format($asset)]);
    }

    public function transfer(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        $data = $request->validate(['location' => ['nullable', 'string', 'max:150'], 'custodian_id' => ['nullable', 'integer', 'exists:employees,id']]);
        $fixedAsset->update([...$data, 'status' => 'transferred']);

        return response()->json(['success' => true, 'message' => 'Asset berhasil ditransfer.', 'data' => $this->format($fixedAsset->fresh($this->with))]);
    }

    public function impair(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        if (! in_array($fixedAsset->status, ['active', 'transferred'], true)) throw ValidationException::withMessages(['status' => 'The asset must be active or transferred before impairment.']);
        $data = $request->validate(['impairment_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'min:0.01'], 'reason' => ['required', 'string', 'max:500']]);
        app(AccountingPeriodService::class)->ensureOpen($data['impairment_date'], 'impairment_date');
        $amount = min((float) $data['amount'], (float) $fixedAsset->net_book_value);
        if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Net book value sudah habis.']);
        $category = $fixedAsset->category;
        $lossAccount = $category?->depreciationGlAccount ?: ChartOfAccount::query()->where('account_type', 'expense')->where('is_header', false)->first();
        $accumulatedAccount = $category?->accumulatedGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('normal_balance', 'credit')->where('is_header', false)->first();
        if (! $lossAccount || ! $accumulatedAccount) throw ValidationException::withMessages(['account' => 'COA impairment loss dan accumulated asset harus tersedia.']);
        $asset = DB::transaction(function () use ($fixedAsset, $data, $amount, $lossAccount, $accumulatedAccount, $request) {
            $journal = Journal::create(['fiscal_year_id' => $fixedAsset->fiscal_year_id, 'journal_number' => 'IMP-'.now()->format('YmdHis').'-'.random_int(100, 999), 'journal_date' => $data['impairment_date'], 'journal_type' => 'adjustment', 'reference' => $fixedAsset->asset_code, 'description' => 'Impairment '.$fixedAsset->asset_name.' - '.$data['reason'], 'status' => 'posted', 'posted_by' => $request->user()->id, 'posted_at' => now(), 'source_type' => 'fixed_asset_impairment', 'source_id' => $fixedAsset->id]);
            $dimensions = $this->journalDimensions($fixedAsset);
            $journal->lines()->create(['account_id' => $lossAccount->id, ...$dimensions, 'line_description' => 'Impairment loss', 'debit' => $amount, 'credit' => 0, 'line_order' => 1]);
            $journal->lines()->create(['account_id' => $accumulatedAccount->id, ...$dimensions, 'line_description' => 'Accumulated impairment', 'debit' => 0, 'credit' => $amount, 'line_order' => 2]);
            $fixedAsset->update(['impairment_amount' => round((float) $fixedAsset->impairment_amount + $amount, 2), 'net_book_value' => max((float) $fixedAsset->residual_value, round((float) $fixedAsset->net_book_value - $amount, 2)), 'impairment_journal_id' => $journal->id]);
            return $fixedAsset->fresh($this->with);
        });
        return response()->json(['success' => true, 'message' => 'Impairment asset berhasil diposting.', 'data' => $this->format($asset)]);
    }

    public function dispose(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        if ($fixedAsset->status === 'disposed') {
            throw ValidationException::withMessages(['status' => 'Asset sudah disposed.']);
        }
        $data = $request->validate(['disposed_date' => ['nullable', 'date'], 'disposal_reason' => ['nullable', 'string'], 'disposal_type' => ['nullable', 'in:sale,writeoff,donation,lost'], 'disposal_proceeds' => ['nullable', 'numeric', 'min:0'], 'disposal_bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id']]);
        $data['disposed_date'] ??= $fixedAsset->disposal_requested_date?->toDateString();
        $data['disposal_reason'] ??= $fixedAsset->disposal_requested_reason;
        if (! $data['disposed_date'] || ! $data['disposal_reason']) throw ValidationException::withMessages(['disposed_date' => 'The disposal date and reason are required before disposal.']);
        $data['disposal_type'] ??= 'writeoff';
        $data['disposal_proceeds'] = round((float) ($data['disposal_proceeds'] ?? 0), 2);
        if ($data['disposal_type'] === 'sale' && $data['disposal_proceeds'] > 0 && empty($data['disposal_bank_account_id'])) throw ValidationException::withMessages(['disposal_bank_account_id' => 'A bank account is required for sale proceeds.']);
        app(AccountingPeriodService::class)->ensureOpen($data['disposed_date'], 'disposed_date');
        $approval = app(ApprovalWorkflowService::class)->approve('asset_disposal', $fixedAsset, $request->user(), $request->input('notes'));
        if (! $approval['managed'] && app(ApprovalWorkflowService::class)->requiresApproval('asset_disposal', $fixedAsset, (float) $fixedAsset->net_book_value)) throw ValidationException::withMessages(['approval' => 'The disposal must be submitted to the Approval Matrix before posting.']);
        if ($approval['managed'] && ! $approval['completed']) return response()->json(['success' => true, 'message' => "Approval disposal tahap selesai. Menunggu approver level {$approval['next_level']}.", 'data' => $this->format($fixedAsset->fresh($this->with))]);
        $category = $fixedAsset->category;
        $assetAccount = $category?->assetGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('is_header', false)->first();
        $accumulatedAccount = $category?->accumulatedGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('normal_balance', 'credit')->where('is_header', false)->first();
        $resultAccount = ChartOfAccount::query()->where('account_type', 'expense')->where('is_header', false)->first();
        $bank = ! empty($data['disposal_bank_account_id']) ? BankAccount::find($data['disposal_bank_account_id']) : null;
        if ($data['disposal_type'] === 'sale' && $data['disposal_proceeds'] > 0 && ! $bank?->gl_account_id) throw ValidationException::withMessages(['disposal_bank_account_id' => 'Bank account disposal belum memiliki GL account.']);
        if (! $assetAccount || ! $accumulatedAccount || ! $resultAccount) throw ValidationException::withMessages(['account' => 'COA asset, accumulated depreciation, dan disposal result harus tersedia.']);
        $asset = DB::transaction(function () use ($fixedAsset, $data, $assetAccount, $accumulatedAccount, $resultAccount, $bank) {
            $netBookValue = round((float) $fixedAsset->net_book_value, 2);
            $proceeds = (float) $data['disposal_proceeds'];
            $loss = max(0, round($netBookValue - $proceeds, 2));
            $gain = max(0, round($proceeds - $netBookValue, 2));
            $journal = Journal::create(['fiscal_year_id' => $fixedAsset->fiscal_year_id, 'journal_number' => 'DISP-'.now()->format('YmdHis').'-'.random_int(100, 999), 'journal_date' => $data['disposed_date'], 'journal_type' => 'adjustment', 'reference' => $fixedAsset->asset_code, 'description' => 'Disposal '.$fixedAsset->asset_name, 'status' => 'posted', 'posted_by' => request()->user()->id, 'posted_at' => now(), 'source_type' => 'fixed_asset_disposal', 'source_id' => $fixedAsset->id]);
            $dimensions = $this->journalDimensions($fixedAsset);
            $line = 1;
            if ((float) $fixedAsset->accumulated_depreciation > 0) $journal->lines()->create(['account_id' => $accumulatedAccount->id, ...$dimensions, 'line_description' => 'Remove accumulated depreciation', 'debit' => $fixedAsset->accumulated_depreciation, 'credit' => 0, 'line_order' => $line++]);
            if ($proceeds > 0 && $bank?->gl_account_id) $journal->lines()->create(['account_id' => $bank->gl_account_id, ...$dimensions, 'line_description' => 'Disposal proceeds', 'debit' => $proceeds, 'credit' => 0, 'line_order' => $line++]);
            if ($loss > 0) $journal->lines()->create(['account_id' => $resultAccount->id, ...$dimensions, 'line_description' => 'Loss on disposal', 'debit' => $loss, 'credit' => 0, 'line_order' => $line++]);
            if ($gain > 0) $journal->lines()->create(['account_id' => $resultAccount->id, ...$dimensions, 'line_description' => 'Gain on disposal', 'debit' => 0, 'credit' => $gain, 'line_order' => $line++]);
            $journal->lines()->create(['account_id' => $assetAccount->id, ...$dimensions, 'line_description' => 'Remove asset cost', 'debit' => 0, 'credit' => $fixedAsset->acquisition_cost, 'line_order' => $line]);
            $journal->syncBankTransactions();
            $fixedAsset->update([...$data, 'status' => 'disposed', 'journal_id' => $fixedAsset->journal_id, 'disposal_journal_id' => $journal->id, 'net_book_value' => 0]);
            return $fixedAsset->fresh($this->with);
        });
        return response()->json(['success' => true, 'message' => 'Asset berhasil disposed dan jurnal pelepasan diposting.', 'data' => $this->format($asset)]);
    }

    public function submitDisposal(Request $request, FixedAsset $fixedAsset, ApprovalWorkflowService $workflow): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        if (! in_array($fixedAsset->status, ['active', 'transferred'], true)) throw ValidationException::withMessages(['status' => 'The asset must be active or transferred before disposal submission.']);
        $data = $request->validate(['disposed_date' => ['required', 'date'], 'disposal_reason' => ['required', 'string']]);
        app(AccountingPeriodService::class)->ensureOpen($data['disposed_date'], 'disposed_date');
        $fixedAsset->update(['disposal_requested_date' => $data['disposed_date'], 'disposal_requested_reason' => $data['disposal_reason']]);
        $run = $workflow->start('asset_disposal', $fixedAsset, (float) $fixedAsset->net_book_value, $request->user()->id);
        return response()->json(['success' => true, 'workflow_managed' => (bool) $run, 'message' => $run ? 'Asset disposal submitted to the Approval Matrix.' : 'No active disposal Approval Matrix; the asset is ready for disposal.', 'data' => $this->format($fixedAsset->fresh($this->with))]);
    }

    public function bulkDepreciate(Request $request): JsonResponse
    {
        $depreciationDate = $request->input('depreciation_date', now()->toDateString());
        app(AccountingPeriodService::class)->ensureOpen($depreciationDate, 'depreciation_date');

        $activeAssetsQuery = FixedAsset::query()
            ->whereIn('status', ['active', 'transferred'])
            ->where('depreciation_method', '!=', 'none')
            ->where('net_book_value', '>', 0);
        app(DataScopeService::class)->applyScope($activeAssetsQuery, $request->user(), 'created_by', 'project_id', 'organization_id', []);
        $activeAssets = $activeAssetsQuery->get();

        $processedCount = 0;
        $totalDepreciationAmount = 0;
        $skipped = [];

        foreach ($activeAssets as $asset) {
            try {
                if ($asset->depreciations()->whereDate('depreciation_date', $depreciationDate)->exists()) {
                    $skipped[] = ['asset_id' => $asset->id, 'reason' => 'Depresiasi untuk tanggal ini sudah pernah diposting.'];
                    continue;
                }
                $category = $asset->category;
                $depreciationAccount = $asset->depreciationGlAccount ?: $category?->depreciationGlAccount ?: ChartOfAccount::query()->where('account_type', 'expense')->where('is_header', false)->first();
                $accumulatedAccount = $asset->accumulatedGlAccount ?: $category?->accumulatedGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('normal_balance', 'credit')->where('is_header', false)->first();

                if (! $depreciationAccount || ! $accumulatedAccount) {
                    $skipped[] = ['asset_id' => $asset->id, 'reason' => 'COA depresiasi atau akumulasi belum dikonfigurasi.'];
                    continue;
                }

                $remaining = round((float) $asset->net_book_value, 2);
                $lifeMonths = max(1, (int) $asset->useful_life_months);
                $depreciableBase = max(0, (float) $asset->acquisition_cost - (float) $asset->residual_value);
                $remainingDepreciable = max(0, $depreciableBase - (float) $asset->accumulated_depreciation);
                $monthly = $asset->depreciation_method === 'declining_balance'
                    ? round($remaining * (2 / $lifeMonths), 2)
                    : round($depreciableBase / $lifeMonths, 2);
                $amount = min($monthly, $remainingDepreciable);

                if ($amount <= 0) {
                    $skipped[] = ['asset_id' => $asset->id, 'reason' => 'Net book value sudah habis.'];
                    continue;
                }

                DB::transaction(function () use ($asset, $depreciationDate, $amount, $depreciationAccount, $accumulatedAccount) {
                    $journal = Journal::create([
                        'fiscal_year_id' => $asset->fiscal_year_id,
                        'journal_number' => 'BULKDEP-'.now()->format('YmdHis').'-'.random_int(100, 999),
                        'journal_date' => $depreciationDate,
                        'journal_type' => 'adjustment',
                        'reference' => $asset->asset_code,
                        'description' => 'Bulk Depreciation '.$asset->asset_name,
                        'status' => 'posted',
                        'posted_by' => request()->user()->id,
                        'posted_at' => now(),
                        'source_type' => 'fixed_asset_depreciation', 'source_id' => $asset->id,
                    ]);
                    $dimensions = $this->journalDimensions($asset);
                    $journal->lines()->create(['account_id' => $depreciationAccount->id, ...$dimensions, 'line_description' => 'Bulk Depreciation expense', 'debit' => $amount, 'credit' => 0, 'line_order' => 1]);
                    $journal->lines()->create(['account_id' => $accumulatedAccount->id, ...$dimensions, 'line_description' => 'Accumulated depreciation', 'debit' => 0, 'credit' => $amount, 'line_order' => 2]);

                    $accumulated = round((float) $asset->accumulated_depreciation + $amount, 2);
                    $nbv = max((float) $asset->residual_value, round((float) $asset->acquisition_cost - $accumulated - (float) $asset->impairment_amount, 2));
                    $asset->depreciations()->create(['depreciation_date' => $depreciationDate, 'amount' => $amount, 'accumulated_depreciation' => $accumulated, 'net_book_value' => $nbv, 'journal_id' => $journal->id, 'created_by' => request()->user()->id]);
                    $asset->update(['accumulated_depreciation' => $accumulated, 'net_book_value' => $nbv]);
                });

                $processedCount++;
                $totalDepreciationAmount += $amount;
            } catch (\Throwable $exception) {
                report($exception);
                    $skipped[] = ['asset_id' => $asset->id, 'reason' => 'Failed to post depreciation.'];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk penyusutan selesai untuk {$processedCount} aset.",
            'processed_count' => $processedCount,
            'total_amount' => round($totalDepreciationAmount, 2),
            'skipped_count' => count($skipped),
            'skipped' => $skipped,
        ]);
    }

    public function stockOpname(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opname_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fixed_asset_id' => ['required', 'integer', 'exists:fixed_assets,id'],
            'items.*.physical_status' => ['required', 'string', 'in:good,damaged,missing'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $updatedCount = 0;
        foreach ($data['items'] as $item) {
            $asset = FixedAsset::find($item['fixed_asset_id']);
            if ($asset) {
                $this->ensureAssetScope($request, $asset);
                $notes = "Stocktake {$data['opname_date']} [Physical Status: {$item['physical_status']}]: ".($item['notes'] ?? 'No notes');
                $asset->update([
                    'notes' => $asset->notes ? $asset->notes." | ".$notes : $notes,
                ]);
                $updatedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Hasil stock opname fisik berhasil dicatat untuk {$updatedCount} aset.",
            'updated_count' => $updatedCount,
        ]);
    }

    private function validatePayload(Request $request, bool $updating = false, ?int $assetId = null): array
    {
        $payload = $request->validate([
            'asset_code' => ['nullable', 'string', 'max:50', 'unique:fixed_assets,asset_code'.($updating && $assetId ? ','.$assetId : '')],
            'asset_name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'asset_category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'is_non_depreciable' => ['nullable', 'boolean'],
            'acquisition_date' => ['required', 'date'],
            'in_service_date' => ['nullable', 'date', 'after_or_equal:acquisition_date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'residual_value' => ['nullable', 'numeric', 'min:0', 'lte:acquisition_cost'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'goods_receipt_id' => ['nullable', 'integer', 'exists:goods_receipts,id'],
            'supplier_invoice_id' => ['nullable', 'integer', 'exists:supplier_invoices,id'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'custodian_id' => ['nullable', 'integer', 'exists:employees,id'],
            'useful_life_months' => ['nullable', 'integer', 'min:1'],
            'depreciation_method' => ['nullable', 'in:straight_line,declining_balance,none'],
            'asset_gl_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'depreciation_gl_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'accumulated_gl_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($payload['project_id'])) {
            $project = Project::query()
                ->withoutGlobalScope('fiscal_year_context')
                ->with(['grantAgreement' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context')])
                ->findOrFail($payload['project_id']);
            $payload['program_id'] = $project->program_id ?: ($payload['program_id'] ?? null);
            $payload['donor_id'] = $project->grantAgreement?->donor_id ?: ($payload['donor_id'] ?? null);
        }

        return $payload;
    }

    private function journalDimensions(FixedAsset $asset): array
    {
        $asset->loadMissing('project.grantAgreementUnscoped');
        $project = $asset->project;
        $grant = $project?->grantAgreementUnscoped;

        return [
            'project_id' => $asset->project_id,
            'donor_id' => $grant?->donor_id ?: $asset->donor_id,
            'program_id' => $project?->program_id ?: $asset->program_id,
            'grant_agreement_id' => $project?->grant_agreement_id,
            'relation_no' => $project?->relation_no ?: $grant?->relation_no,
        ];
    }

    private function nextAssetCode(): string
    {
        return 'FA-'.now()->format('YmdHis').'-'.random_int(100, 999);
    }

    private function ensureAssetScope(Request $request, FixedAsset $asset): void
    {
        if (app(DataScopeService::class)->canAccessAll($request->user())) {
            return;
        }

        if ((int) $asset->created_by !== (int) $request->user()->id) {
            throw new AuthorizationException('Tidak boleh mengakses aset milik pengguna lain.');
        }
    }

    private function format(FixedAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'asset_name' => $asset->asset_name,
            'description' => $asset->description,
            'category' => $asset->category ? ['id' => $asset->category->id, 'code' => $asset->category->code, 'name' => $asset->category->name, 'useful_life_months' => $asset->category->useful_life_months, 'depreciation_method' => $asset->category->depreciation_method, 'is_non_depreciable' => (bool) $asset->category->is_non_depreciable, 'asset_gl_account_id' => $asset->category->asset_gl_account_id, 'depreciation_gl_account_id' => $asset->category->depreciation_gl_account_id, 'accumulated_gl_account_id' => $asset->category->accumulated_gl_account_id, 'asset_gl_account' => $asset->category->assetGlAccount ? ['id' => $asset->category->assetGlAccount->id, 'code' => $asset->category->assetGlAccount->code, 'name' => $asset->category->assetGlAccount->name] : null] : null,
            'asset_category_id' => $asset->asset_category_id,
            'is_non_depreciable' => (bool) $asset->is_non_depreciable,
            'acquisition_date' => $asset->acquisition_date?->toDateString(),
            'in_service_date' => $asset->in_service_date?->toDateString(),
            'acquisition_cost' => $asset->acquisition_cost,
            'residual_value' => $asset->residual_value,
            'accumulated_depreciation' => $asset->accumulated_depreciation,
            'impairment_amount' => $asset->impairment_amount,
            'net_book_value' => $asset->net_book_value,
            'depreciation_method' => $asset->depreciation_method,
            'useful_life_months' => $asset->useful_life_months,
            'asset_gl_account_id' => $asset->asset_gl_account_id,
            'depreciation_gl_account_id' => $asset->depreciation_gl_account_id,
            'accumulated_gl_account_id' => $asset->accumulated_gl_account_id,
            'asset_account' => $asset->assetGlAccount ? ['id' => $asset->assetGlAccount->id, 'code' => $asset->assetGlAccount->code, 'name' => $asset->assetGlAccount->name] : null,
            'depreciation_account' => $asset->depreciationGlAccount ? ['id' => $asset->depreciationGlAccount->id, 'code' => $asset->depreciationGlAccount->code, 'name' => $asset->depreciationGlAccount->name] : null,
            'accumulated_account' => $asset->accumulatedGlAccount ? ['id' => $asset->accumulatedGlAccount->id, 'code' => $asset->accumulatedGlAccount->code, 'name' => $asset->accumulatedGlAccount->name] : null,
            'location' => $asset->location,
            'custodian' => $asset->custodian ? ['id' => $asset->custodian->id, 'name' => $asset->custodian->name] : null,
            'custodian_id' => $asset->custodian_id,
            'donor' => $asset->donor ? ['id' => $asset->donor->id, 'code' => $asset->donor->code, 'name' => $asset->donor->name] : null,
            'program' => $asset->program ? ['id' => $asset->program->id, 'code' => $asset->program->code, 'name' => $asset->program->name] : null,
            'project' => $asset->project ? ['id' => $asset->project->id, 'code' => $asset->project->code, 'name' => $asset->project->name, 'relation_no' => $asset->project->relation_no, 'bank_account_id' => $asset->project->bank_account_id] : null,
            'relation_no' => $asset->project?->relation_no ?: $asset->project?->grantAgreementUnscoped?->relation_no,
            'grant_agreement_id' => $asset->project?->grant_agreement_id,
            'grant_agreement' => $asset->project?->grantAgreementUnscoped ? ['id' => $asset->project->grantAgreementUnscoped->id, 'grant_no' => $asset->project->grantAgreementUnscoped->grant_no, 'agreement_name' => $asset->project->grantAgreementUnscoped->agreement_name, 'relation_no' => $asset->project->grantAgreementUnscoped->relation_no] : null,
            'project_bank_account' => $asset->project?->bankAccount ? ['id' => $asset->project->bankAccount->id, 'bank_name' => $asset->project->bankAccount->bank_name, 'account_number' => $asset->project->bankAccount->account_number] : null,
            'vendor' => $asset->vendor ? ['id' => $asset->vendor->id, 'code' => $asset->vendor->code, 'name' => $asset->vendor->name] : null,
            'purchase_order' => $asset->purchaseOrder ? ['id' => $asset->purchaseOrder->id, 'po_number' => $asset->purchaseOrder->po_number] : null,
            'goods_receipt' => $asset->goodsReceipt ? ['id' => $asset->goodsReceipt->id, 'grn_number' => $asset->goodsReceipt->grn_number] : null,
            'supplier_invoice' => $asset->supplierInvoice ? ['id' => $asset->supplierInvoice->id, 'invoice_number' => $asset->supplierInvoice->invoice_number] : null,
            'journal_id' => $asset->journal_id,
            'capitalization_journal_id' => $asset->capitalization_journal_id,
            'disposal_journal_id' => $asset->disposal_journal_id,
            'impairment_journal_id' => $asset->impairment_journal_id,
            'disposal_type' => $asset->disposal_type,
            'disposal_proceeds' => $asset->disposal_proceeds,
            'disposal_bank_account_id' => $asset->disposal_bank_account_id,
            'disposal_bank_account' => $asset->disposalBankAccount ? ['id' => $asset->disposalBankAccount->id, 'bank_name' => $asset->disposalBankAccount->bank_name, 'account_number' => $asset->disposalBankAccount->account_number] : null,
            'disposed_date' => $asset->disposed_date?->toDateString(),
            'disposal_reason' => $asset->disposal_reason,
            'capitalized_at' => $asset->capitalized_at?->toISOString(),
            'status' => $asset->status,
            'notes' => $asset->notes,
            'depreciations' => $asset->depreciations->map(fn ($row) => ['id' => $row->id, 'depreciation_date' => $row->depreciation_date?->toDateString(), 'amount' => $row->amount, 'accumulated_depreciation' => $row->accumulated_depreciation, 'net_book_value' => $row->net_book_value])->values(),
        ];
    }
}
