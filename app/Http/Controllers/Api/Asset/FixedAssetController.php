<?php

namespace App\Http\Controllers\Api\Asset;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Asset\FixedAsset;
use App\Models\Master\AssetCategory;
use App\Models\Master\ChartOfAccount;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Rbac\DataScopeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class FixedAssetController extends Controller
{
    private array $with = ['category:id,code,name,useful_life_months,depreciation_method,asset_gl_account_id,depreciation_gl_account_id,accumulated_gl_account_id', 'vendor:id,code,name', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'supplierInvoice:id,invoice_number', 'donor:id,code,name', 'program:id,code,name', 'project:id,code,name', 'custodian:id,employee_id_number,name', 'depreciations'];

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
            ->latest('id')
            ->get();

        $totals = [
            'acquisition_cost' => round((float) $assets->sum('acquisition_cost'), 2),
            'accumulated_depreciation' => round((float) $assets->sum('accumulated_depreciation'), 2),
            'net_book_value' => round((float) $assets->sum('net_book_value'), 2),
            'assets' => $assets->count(),
        ];

        return response()->json(['success' => true, 'totals' => $totals, 'data' => $assets->map(fn (FixedAsset $asset) => $this->format($asset))]);
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
            'depreciation_method' => $payload['depreciation_method'] ?? $category?->depreciation_method ?? 'straight_line',
            'accumulated_depreciation' => 0,
            'net_book_value' => $cost,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ])->load($this->with);

        return response()->json(['success' => true, 'message' => 'Fixed asset berhasil dibuat.', 'data' => $this->format($asset)], Response::HTTP_CREATED);
    }

    public function capitalize(FixedAsset $fixedAsset): JsonResponse
    {
        $this->ensureAssetScope(request(), $fixedAsset);
        app(AccountingPeriodService::class)->ensureOpen($fixedAsset->acquisition_date->toDateString(), 'acquisition_date');
        if ($fixedAsset->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Asset hanya dapat dikapitalisasi dari draft.']);
        }

        $category = $fixedAsset->category;
        $assetAccount = $category?->assetGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('is_header', false)->first();
        $clearingAccount = ChartOfAccount::query()->where('account_type', 'liability')->where('is_header', false)->first()
            ?: ChartOfAccount::query()->where('account_type', 'equity')->where('is_header', false)->first();
        if (! $assetAccount || ! $clearingAccount) {
            throw ValidationException::withMessages(['account' => 'COA asset dan clearing/liability harus tersedia.']);
        }

        $asset = DB::transaction(function () use ($fixedAsset, $assetAccount, $clearingAccount) {
            $journal = Journal::create([
                'journal_number' => 'FA-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $fixedAsset->acquisition_date,
                'journal_type' => 'manual',
                'reference' => $fixedAsset->asset_code,
                'description' => 'Asset capitalization '.$fixedAsset->asset_name,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);
            $journal->lines()->create(['account_id' => $assetAccount->id, 'project_id' => $fixedAsset->project_id, 'donor_id' => $fixedAsset->donor_id, 'program_id' => $fixedAsset->program_id, 'line_description' => 'Capitalized asset', 'debit' => $fixedAsset->acquisition_cost, 'credit' => 0, 'line_order' => 1]);
            $journal->lines()->create(['account_id' => $clearingAccount->id, 'line_description' => 'Asset clearing/source', 'debit' => 0, 'credit' => $fixedAsset->acquisition_cost, 'line_order' => 2]);

            $fixedAsset->update(['status' => 'active', 'journal_id' => $journal->id, 'capitalized_by' => request()->user()->id, 'capitalized_at' => now()]);

            return $fixedAsset->fresh($this->with);
        });

        return response()->json(['success' => true, 'message' => 'Asset berhasil dikapitalisasi.', 'data' => $this->format($asset)]);
    }

    public function depreciate(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        if (! in_array($fixedAsset->status, ['active', 'transferred'], true)) {
            throw ValidationException::withMessages(['status' => 'Asset harus active/transferred untuk depresiasi.']);
        }
        if ($fixedAsset->depreciation_method === 'none') {
            throw ValidationException::withMessages(['depreciation_method' => 'Asset category tidak menggunakan depresiasi.']);
        }
        $data = $request->validate(['depreciation_date' => ['required', 'date'], 'amount' => ['nullable', 'numeric', 'min:0.01']]);
        app(AccountingPeriodService::class)->ensureOpen($data['depreciation_date'], 'depreciation_date');
        $category = $fixedAsset->category;
        $depreciationAccount = $category?->depreciationGlAccount ?: ChartOfAccount::query()->where('account_type', 'expense')->where('is_header', false)->first();
        $accumulatedAccount = $category?->accumulatedGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('normal_balance', 'credit')->where('is_header', false)->first();
        if (! $depreciationAccount || ! $accumulatedAccount) {
            throw ValidationException::withMessages(['account' => 'COA depreciation dan accumulated depreciation harus tersedia.']);
        }

        $remaining = round((float) $fixedAsset->net_book_value, 2);
        $lifeMonths = max(1, (int) $fixedAsset->useful_life_months);
        $monthly = $data['amount'] ?? ($fixedAsset->depreciation_method === 'declining_balance'
            ? round($remaining * (2 / $lifeMonths), 2)
            : round((float) $fixedAsset->acquisition_cost / $lifeMonths, 2));
        $amount = min($monthly, $remaining);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Net book value sudah habis.']);
        }

        $asset = DB::transaction(function () use ($fixedAsset, $data, $amount, $depreciationAccount, $accumulatedAccount) {
            $journal = Journal::create([
                'journal_number' => 'DEP-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $data['depreciation_date'],
                'journal_type' => 'adjustment',
                'reference' => $fixedAsset->asset_code,
                'description' => 'Depreciation '.$fixedAsset->asset_name,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);
            $journal->lines()->create(['account_id' => $depreciationAccount->id, 'project_id' => $fixedAsset->project_id, 'donor_id' => $fixedAsset->donor_id, 'program_id' => $fixedAsset->program_id, 'line_description' => 'Depreciation expense', 'debit' => $amount, 'credit' => 0, 'line_order' => 1]);
            $journal->lines()->create(['account_id' => $accumulatedAccount->id, 'line_description' => 'Accumulated depreciation', 'debit' => 0, 'credit' => $amount, 'line_order' => 2]);

            $accumulated = round((float) $fixedAsset->accumulated_depreciation + $amount, 2);
            $nbv = max(0, round((float) $fixedAsset->acquisition_cost - $accumulated, 2));
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

    public function dispose(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $this->ensureAssetScope($request, $fixedAsset);
        if ($fixedAsset->status === 'disposed') {
            throw ValidationException::withMessages(['status' => 'Asset sudah disposed.']);
        }
        $data = $request->validate(['disposed_date' => ['required', 'date'], 'disposal_reason' => ['required', 'string']]);
        app(AccountingPeriodService::class)->ensureOpen($data['disposed_date'], 'disposed_date');
        $category = $fixedAsset->category;
        $assetAccount = $category?->assetGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('is_header', false)->first();
        $accumulatedAccount = $category?->accumulatedGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('normal_balance', 'credit')->where('is_header', false)->first();
        $resultAccount = ChartOfAccount::query()->where('account_type', 'expense')->where('is_header', false)->first();
        if (! $assetAccount || ! $accumulatedAccount || ! $resultAccount) throw ValidationException::withMessages(['account' => 'COA asset, accumulated depreciation, dan disposal result harus tersedia.']);
        $asset = DB::transaction(function () use ($fixedAsset, $data, $assetAccount, $accumulatedAccount, $resultAccount) {
            $netBookValue = round((float) $fixedAsset->net_book_value, 2);
            $journal = Journal::create(['journal_number' => 'DISP-'.now()->format('YmdHis').'-'.random_int(100, 999), 'journal_date' => $data['disposed_date'], 'journal_type' => 'adjustment', 'reference' => $fixedAsset->asset_code, 'description' => 'Disposal '.$fixedAsset->asset_name, 'status' => 'posted', 'posted_by' => request()->user()->id, 'posted_at' => now()]);
            if ((float) $fixedAsset->accumulated_depreciation > 0) $journal->lines()->create(['account_id' => $accumulatedAccount->id, 'line_description' => 'Remove accumulated depreciation', 'debit' => $fixedAsset->accumulated_depreciation, 'credit' => 0, 'line_order' => 1]);
            if ($netBookValue > 0) $journal->lines()->create(['account_id' => $resultAccount->id, 'line_description' => 'Disposal loss', 'debit' => $netBookValue, 'credit' => 0, 'line_order' => 2]);
            $journal->lines()->create(['account_id' => $assetAccount->id, 'line_description' => 'Remove asset cost', 'debit' => 0, 'credit' => $fixedAsset->acquisition_cost, 'line_order' => 3]);
            $fixedAsset->update([...$data, 'status' => 'disposed', 'journal_id' => $journal->id, 'net_book_value' => 0]);
            return $fixedAsset->fresh($this->with);
        });
        return response()->json(['success' => true, 'message' => 'Asset berhasil disposed dan jurnal pelepasan diposting.', 'data' => $this->format($asset)]);
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

        foreach ($activeAssets as $asset) {
            try {
                $category = $asset->category;
                $depreciationAccount = $category?->depreciationGlAccount ?: ChartOfAccount::query()->where('account_type', 'expense')->where('is_header', false)->first();
                $accumulatedAccount = $category?->accumulatedGlAccount ?: ChartOfAccount::query()->where('account_type', 'asset')->where('normal_balance', 'credit')->where('is_header', false)->first();

                if (! $depreciationAccount || ! $accumulatedAccount) {
                    continue;
                }

                $remaining = round((float) $asset->net_book_value, 2);
                $lifeMonths = max(1, (int) $asset->useful_life_months);
                $monthly = $asset->depreciation_method === 'declining_balance'
                    ? round($remaining * (2 / $lifeMonths), 2)
                    : round((float) $asset->acquisition_cost / $lifeMonths, 2);
                $amount = min($monthly, $remaining);

                if ($amount <= 0) {
                    continue;
                }

                DB::transaction(function () use ($asset, $depreciationDate, $amount, $depreciationAccount, $accumulatedAccount) {
                    $journal = Journal::create([
                        'journal_number' => 'BULKDEP-'.now()->format('YmdHis').'-'.random_int(100, 999),
                        'journal_date' => $depreciationDate,
                        'journal_type' => 'adjustment',
                        'reference' => $asset->asset_code,
                        'description' => 'Bulk Depreciation '.$asset->asset_name,
                        'status' => 'posted',
                        'posted_by' => request()->user()->id,
                        'posted_at' => now(),
                    ]);
                    $journal->lines()->create(['account_id' => $depreciationAccount->id, 'project_id' => $asset->project_id, 'donor_id' => $asset->donor_id, 'program_id' => $asset->program_id, 'line_description' => 'Bulk Depreciation expense', 'debit' => $amount, 'credit' => 0, 'line_order' => 1]);
                    $journal->lines()->create(['account_id' => $accumulatedAccount->id, 'line_description' => 'Accumulated depreciation', 'debit' => 0, 'credit' => $amount, 'line_order' => 2]);

                    $accumulated = round((float) $asset->accumulated_depreciation + $amount, 2);
                    $nbv = max(0, round((float) $asset->acquisition_cost - $accumulated, 2));
                    $asset->depreciations()->create(['depreciation_date' => $depreciationDate, 'amount' => $amount, 'accumulated_depreciation' => $accumulated, 'net_book_value' => $nbv, 'journal_id' => $journal->id, 'created_by' => request()->user()->id]);
                    $asset->update(['accumulated_depreciation' => $accumulated, 'net_book_value' => $nbv]);
                });

                $processedCount++;
                $totalDepreciationAmount += $amount;
            } catch (\Throwable) {
                // Continue
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk penyusutan selesai untuk {$processedCount} aset.",
            'processed_count' => $processedCount,
            'total_amount' => round($totalDepreciationAmount, 2),
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
                $notes = "Opname {$data['opname_date']} [Status Fisik: {$item['physical_status']}]: ".($item['notes'] ?? 'Tidak ada catatan');
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

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'asset_code' => ['nullable', 'string', 'max:50', 'unique:fixed_assets,asset_code'],
            'asset_name' => ['required', 'string', 'max:160'],
            'asset_category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
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
            'notes' => ['nullable', 'string'],
        ]);
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
            'category' => $asset->category ? ['id' => $asset->category->id, 'code' => $asset->category->code, 'name' => $asset->category->name] : null,
            'acquisition_date' => $asset->acquisition_date?->toDateString(),
            'acquisition_cost' => $asset->acquisition_cost,
            'accumulated_depreciation' => $asset->accumulated_depreciation,
            'net_book_value' => $asset->net_book_value,
            'depreciation_method' => $asset->depreciation_method,
            'useful_life_months' => $asset->useful_life_months,
            'location' => $asset->location,
            'custodian' => $asset->custodian ? ['id' => $asset->custodian->id, 'name' => $asset->custodian->name] : null,
            'project' => $asset->project ? ['id' => $asset->project->id, 'code' => $asset->project->code, 'name' => $asset->project->name] : null,
            'vendor' => $asset->vendor ? ['id' => $asset->vendor->id, 'code' => $asset->vendor->code, 'name' => $asset->vendor->name] : null,
            'status' => $asset->status,
            'notes' => $asset->notes,
            'depreciations' => $asset->depreciations->map(fn ($row) => ['id' => $row->id, 'depreciation_date' => $row->depreciation_date?->toDateString(), 'amount' => $row->amount, 'accumulated_depreciation' => $row->accumulated_depreciation, 'net_book_value' => $row->net_book_value])->values(),
        ];
    }
}
