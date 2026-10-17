<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\ExchangeRate;
use App\Http\Requests\Master\StoreExchangeRateRequest;
use App\Http\Requests\Master\UpdateExchangeRateRequest;
use App\Http\Resources\Master\ExchangeRateResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExchangeRateController extends BaseMasterController
{
    protected string $modelClass = ExchangeRate::class;
    protected string $resourceClass = ExchangeRateResource::class;
    protected string $storeRequestClass = StoreExchangeRateRequest::class;
    protected string $updateRequestClass = UpdateExchangeRateRequest::class;
    protected array $searchableColumns = ['source'];
    protected array $defaultWith = ['fromCurrency', 'toCurrency'];

    /**
     * Resolve the effective rate for a transaction date. Rates are date-based,
     * so the latest active rate on or before the requested date is selected.
     */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'to_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'date' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:150'],
        ]);

        $rate = ExchangeRate::query()
            ->with(['fromCurrency', 'toCurrency'])
            ->where('from_currency_id', $data['from_currency_id'])
            ->where('to_currency_id', $data['to_currency_id'])
            ->where('is_active', true)
            ->whereDate('date', '<=', $data['date'])
            ->when(! empty($data['source']), fn ($query) => $query->where('source', $data['source']))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if (! $rate) {
            return response()->json([
                'success' => false,
                'message' => 'Kurs aktif belum tersedia untuk tanggal tersebut.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $rate->id,
                'date' => $rate->date?->toDateString(),
                'rate' => (float) $rate->rate,
                'rate_type' => $rate->rate_type,
                'source' => $rate->source,
                'from_currency' => $rate->fromCurrency?->only(['id', 'code', 'name']),
                'to_currency' => $rate->toCurrency?->only(['id', 'code', 'name']),
            ],
        ]);
    }
}
