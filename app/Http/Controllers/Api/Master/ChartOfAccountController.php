<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\ChartOfAccount;
use App\Http\Requests\Master\StoreChartOfAccountRequest;
use App\Http\Requests\Master\UpdateChartOfAccountRequest;
use App\Http\Resources\Master\ChartOfAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChartOfAccountController extends BaseMasterController
{
    protected string $modelClass = ChartOfAccount::class;
    protected string $resourceClass = ChartOfAccountResource::class;
    protected string $storeRequestClass = StoreChartOfAccountRequest::class;
    protected string $updateRequestClass = UpdateChartOfAccountRequest::class;
    protected array $searchableColumns = ['code', 'name'];
    protected array $defaultWith = ['parent', 'category', 'fiscalYear'];

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('options') || $request->query('paginate') === 'false') {
            return parent::index($request);
        }

        $query = ChartOfAccount::with($this->defaultWith)
            ->withSum(['journalLines as posted_debit_total' => function ($query) {
                $query->whereHas('journal', fn ($journal) => $journal->where('status', 'posted'));
            }], 'debit')
            ->withSum(['journalLines as posted_credit_total' => function ($query) {
                $query->whereHas('journal', fn ($journal) => $journal->where('status', 'posted'));
            }], 'credit');

        $query->applyFilters($request, $this->searchableColumns);
        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dimuat.',
            'data' => ChartOfAccountResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ], Response::HTTP_OK);
    }

    public function transactions(Request $request, int $id): JsonResponse
    {
        $account = ChartOfAccount::findOrFail($id);
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $rows = $account->journalLines()
            ->with(['journal:id,journal_number,journal_date,reference,description,status', 'project:id,name'])
            ->whereHas('journal', function ($query) use ($filters) {
                $query->where('status', 'posted')
                    ->when($filters['start_date'] ?? null, fn ($q, $date) => $q->whereDate('journal_date', '>=', $date))
                    ->when($filters['end_date'] ?? null, fn ($q, $date) => $q->whereDate('journal_date', '<=', $date));
            })
            ->orderBy('journal_id')->orderBy('line_order')->get();

        $running = 0;
        $transactions = $rows->map(function ($line) use (&$running, $account) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $running += $account->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;
            return [
                'id' => $line->id,
                'date' => $line->journal->journal_date?->toDateString(),
                'journal_number' => $line->journal->journal_number,
                'reference' => $line->journal->reference,
                'description' => $line->line_description ?: $line->journal->description,
                'project' => $line->project?->name,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => round($running, 2),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'normal_balance' => $account->normal_balance],
                'totals' => [
                    'debit' => round((float) $rows->sum('debit'), 2),
                    'credit' => round((float) $rows->sum('credit'), 2),
                    'balance' => round($running, 2),
                ],
                'transactions' => $transactions,
            ],
        ]);
    }
}
