<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\TaxTransaction;
use App\Models\Master\Tax;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaxTransactionController extends Controller
{
    public function taxes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Tax::query()
                ->where('is_active', true)
                ->orderBy('tax_type')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'tax_type', 'rate_percent']),
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_id' => ['required', 'integer', 'exists:taxes,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'is_inclusive' => ['nullable', 'boolean'],
            'direction' => ['nullable', 'in:sales,purchase,withholding_in,withholding_out'],
        ]);

        $tax = Tax::findOrFail($data['tax_id']);

        return response()->json([
            'success' => true,
            'data' => $this->calculateTax($tax, (float) $data['amount'], (bool) ($data['is_inclusive'] ?? false), $data['direction'] ?? 'purchase'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $items = TaxTransaction::query()
            ->with('tax:id,code,name,tax_type,rate_percent')
            ->when($request->filled('start_date'), fn (Builder $query) => $query->whereDate('transaction_date', '>=', $request->string('start_date')))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->whereDate('transaction_date', '<=', $request->string('end_date')))
            ->when($request->filled('tax_id'), fn (Builder $query) => $query->where('tax_id', $request->integer('tax_id')))
            ->when($request->filled('direction'), fn (Builder $query) => $query->where('direction', $request->string('direction')))
            ->latest('transaction_date')
            ->latest('id')
            ->get();

        return response()->json(['success' => true, 'data' => $items->map(fn (TaxTransaction $item) => $this->format($item))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_id' => ['required', 'integer', 'exists:taxes,id'],
            'transaction_type' => ['nullable', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['required', 'date'],
            'direction' => ['required', 'in:sales,purchase,withholding_in,withholding_out'],
            'amount' => ['required', 'numeric', 'min:0'],
            'is_inclusive' => ['nullable', 'boolean'],
            'e_faktur_reference' => ['nullable', 'string', 'max:100'],
            'e_bupot_reference' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,reported,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $tax = Tax::findOrFail($data['tax_id']);
        $calculation = $this->calculateTax($tax, (float) $data['amount'], (bool) ($data['is_inclusive'] ?? false), $data['direction']);

        $item = TaxTransaction::create([
            'tax_id' => $tax->id,
            'transaction_type' => $data['transaction_type'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'direction' => $data['direction'],
            'taxable_amount' => $calculation['taxable_amount'],
            'tax_rate' => $calculation['rate_percent'],
            'tax_amount' => $calculation['tax_amount'],
            'net_amount' => $calculation['net_amount'],
            'gross_amount' => $calculation['gross_amount'],
            'e_faktur_reference' => $data['e_faktur_reference'] ?? null,
            'e_bupot_reference' => $data['e_bupot_reference'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'notes' => $data['notes'] ?? null,
        ])->load('tax:id,code,name,tax_type,rate_percent');

        return response()->json(['success' => true, 'message' => 'Tax transaction berhasil dicatat.', 'data' => $this->format($item)], Response::HTTP_CREATED);
    }

    public function report(Request $request): JsonResponse
    {
        $items = TaxTransaction::query()
            ->with('tax:id,code,name,tax_type,rate_percent')
            ->when($request->filled('start_date'), fn (Builder $query) => $query->whereDate('transaction_date', '>=', $request->string('start_date')))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->whereDate('transaction_date', '<=', $request->string('end_date')))
            ->where('status', '!=', 'cancelled')
            ->get();

        $byType = $items->groupBy(fn (TaxTransaction $item) => $item->tax?->tax_type ?? 'unknown')
            ->map(fn ($rows, string $type) => [
                'tax_type' => $type,
                'taxable_amount' => round((float) $rows->sum('taxable_amount'), 2),
                'tax_amount' => round((float) $rows->sum('tax_amount'), 2),
                'transactions' => $rows->count(),
            ])
            ->values();

        $byDirection = $items->groupBy('direction')
            ->map(fn ($rows, string $direction) => [
                'direction' => $direction,
                'taxable_amount' => round((float) $rows->sum('taxable_amount'), 2),
                'tax_amount' => round((float) $rows->sum('tax_amount'), 2),
                'transactions' => $rows->count(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'totals' => [
                'taxable_amount' => round((float) $items->sum('taxable_amount'), 2),
                'tax_amount' => round((float) $items->sum('tax_amount'), 2),
                'transactions' => $items->count(),
            ],
            'by_type' => $byType,
            'by_direction' => $byDirection,
            'data' => $items->sortByDesc('transaction_date')->values()->map(fn (TaxTransaction $item) => $this->format($item))->all(),
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $currentMonth = sprintf('%04d-%02d', $year, $month);

        $deadlines = [
            [
                'tax_type' => 'pph_21',
                'title' => 'Setor PPh 21/26 Masa ' . sprintf('%02d/%04d', $month, $year),
                'due_date' => sprintf('%04d-%02d-10', $year, $month),
                'category' => 'Payment',
                'status' => 'upcoming',
                'description' => 'Batas akhir penyetoran PPh Pasal 21 Masa ke kas negara via e-Billing.'
            ],
            [
                'tax_type' => 'pph_23',
                'title' => 'Setor PPh 23/26 Masa ' . sprintf('%02d/%04d', $month, $year),
                'due_date' => sprintf('%04d-%02d-10', $year, $month),
                'category' => 'Payment',
                'status' => 'upcoming',
                'description' => 'Batas akhir penyetoran PPh Pasal 23/26 terpotong ke kas negara.'
            ],
            [
                'tax_type' => 'pph_final',
                'title' => 'Setor PPh Final (4 ayat 2)',
                'due_date' => sprintf('%04d-%02d-10', $year, $month),
                'category' => 'Payment',
                'status' => 'upcoming',
                'description' => 'Batas akhir penyetoran PPh Final 4(2) atas sewa & jasa.'
            ],
            [
                'tax_type' => 'ebupot_unifikasi',
                'title' => 'Lapor e-Bupot Unifikasi PPh',
                'due_date' => sprintf('%04d-%02d-20', $year, $month),
                'category' => 'Filing',
                'status' => 'upcoming',
                'description' => 'Batas akhir pelaporan SPT Masa PPh Unifikasi via DJP Online.'
            ],
            [
                'tax_type' => 'ppn',
                'title' => 'Setor & Lapor PPN e-Faktur Masa',
                'due_date' => sprintf('%04d-%02d-%02d', $year, $month, date('t', strtotime("{$year}-{$month}-01"))),
                'category' => 'Filing & Payment',
                'status' => 'upcoming',
                'description' => 'Batas akhir pelaporan SPT Masa PPN dan penyetoran Kurang Bayar PPN.'
            ]
        ];

        return response()->json([
            'success' => true,
            'year' => $year,
            'month' => $month,
            'deadlines' => $deadlines,
        ]);
    }

    private function calculateTax(Tax $tax, float $amount, bool $isInclusive, string $direction): array
    {
        $rate = round((float) $tax->rate_percent, 4);
        $rateFactor = $rate / 100;
        $taxable = $isInclusive && $rateFactor > 0 ? round($amount / (1 + $rateFactor), 2) : round($amount, 2);
        $taxAmount = round($taxable * $rateFactor, 2);
        $gross = $isInclusive ? round($amount, 2) : round($taxable + $taxAmount, 2);

        $isWithholding = str_starts_with($direction, 'withholding');

        return [
            'tax_id' => $tax->id,
            'tax_code' => $tax->code,
            'tax_name' => $tax->name,
            'tax_type' => $tax->tax_type,
            'rate_percent' => $rate,
            'direction' => $direction,
            'is_inclusive' => $isInclusive,
            'taxable_amount' => $taxable,
            'tax_amount' => $taxAmount,
            'gross_amount' => $gross,
            'net_amount' => $isWithholding ? round($taxable - $taxAmount, 2) : $gross,
        ];
    }

    private function format(TaxTransaction $item): array
    {
        return [
            'id' => $item->id,
            'tax' => $item->tax ? ['id' => $item->tax->id, 'code' => $item->tax->code, 'name' => $item->tax->name, 'tax_type' => $item->tax->tax_type, 'rate_percent' => $item->tax->rate_percent] : null,
            'transaction_type' => $item->transaction_type,
            'reference' => $item->reference,
            'transaction_date' => $item->transaction_date?->toDateString(),
            'direction' => $item->direction,
            'taxable_amount' => $item->taxable_amount,
            'tax_rate' => $item->tax_rate,
            'tax_amount' => $item->tax_amount,
            'net_amount' => $item->net_amount,
            'gross_amount' => $item->gross_amount,
            'e_faktur_reference' => $item->e_faktur_reference,
            'e_bupot_reference' => $item->e_bupot_reference,
            'status' => $item->status,
            'notes' => $item->notes,
        ];
    }
}
