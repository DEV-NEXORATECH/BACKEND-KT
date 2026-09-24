<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\AccountingPeriod;
use App\Models\Accounting\Journal;
use App\Http\Requests\Master\StoreAccountingPeriodRequest;
use App\Http\Requests\Master\UpdateAccountingPeriodRequest;
use App\Http\Resources\Master\AccountingPeriodResource;
use Illuminate\Http\Request;

class AccountingPeriodController extends BaseMasterController
{
    protected string $modelClass = AccountingPeriod::class;
    protected string $resourceClass = AccountingPeriodResource::class;
    protected string $storeRequestClass = StoreAccountingPeriodRequest::class;
    protected string $updateRequestClass = UpdateAccountingPeriodRequest::class;
    protected array $searchableColumns = ['name'];
    protected array $defaultWith = ['fiscalYear'];

    public function close(Request $request, $id)
    {
        $period = AccountingPeriod::findOrFail($id);
        $hasUnposted = Journal::query()->whereBetween('journal_date', [$period->start_date, $period->end_date])->whereIn('status', ['draft', 'submitted', 'reviewed'])->exists();
        if ($hasUnposted) {
            return response()->json(['success' => false, 'message' => 'Periode tidak dapat ditutup karena masih ada jurnal draft, submitted, atau reviewed.'], 422);
        }
        $period->update(['status' => 'closed', 'is_active' => false]);
        return response()->json(['success' => true, 'message' => 'Accounting period berhasil ditutup.', 'data' => $period->fresh()]);
    }

    public function reopen(Request $request, $id)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $period = AccountingPeriod::findOrFail($id);
        $period->update(['status' => 'open', 'is_active' => true]);
        return response()->json(['success' => true, 'message' => 'Accounting period dibuka kembali.', 'data' => [...$period->fresh()->toArray(), 'reopen_reason' => $data['reason']]]);
    }
}
