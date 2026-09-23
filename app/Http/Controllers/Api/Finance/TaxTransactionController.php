<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\TaxTransaction;
use App\Models\Master\Tax;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Rbac\DataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $query = TaxTransaction::query()
            ->with('tax:id,code,name,tax_type,rate_percent')
            ->when($request->filled('start_date'), fn (Builder $query) => $query->whereDate('transaction_date', '>=', $request->string('start_date')))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->whereDate('transaction_date', '<=', $request->string('end_date')))
            ->when($request->filled('tax_id'), fn (Builder $query) => $query->where('tax_id', $request->integer('tax_id')))
            ->when($request->filled('direction'), fn (Builder $query) => $query->where('direction', $request->string('direction')));

        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', null, null, []);

        $items = $query->latest('transaction_date')
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
            'npwp' => ['nullable', 'string', 'max:30'],
            'e_faktur_number' => ['nullable', 'string', 'max:30'],
            'e_bupot_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        app(AccountingPeriodService::class)->ensureOpen($data['transaction_date'], 'transaction_date');

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
            'npwp' => $data['npwp'] ?? null,
            'e_faktur_number' => $data['e_faktur_number'] ?? null,
            'e_bupot_number' => $data['e_bupot_number'] ?? null,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ])->load('tax:id,code,name,tax_type,rate_percent');

        return response()->json(['success' => true, 'message' => 'Tax transaction berhasil dicatat.', 'data' => $this->format($item)], Response::HTTP_CREATED);
    }

    /** Record the external tax filing reference before a transaction is reported/exported. */
    public function markReported(Request $request, TaxTransaction $taxTransaction): JsonResponse
    {
        $query = TaxTransaction::whereKey($taxTransaction->id);
        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', null, null, []);
        if (! $query->exists()) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh melaporkan transaksi pajak ini.');
        }
        if ($taxTransaction->status !== 'draft') {
            return response()->json(['success' => true, 'message' => 'Transaksi pajak sudah dilaporkan.', 'data' => $this->format($taxTransaction->load('tax:id,code,name,tax_type,rate_percent'))]);
        }

        $data = $request->validate([
            'e_faktur_reference' => ['nullable', 'string', 'max:100'],
            'e_bupot_reference' => ['nullable', 'string', 'max:100'],
            'e_faktur_number' => ['nullable', 'string', 'max:30'],
            'e_bupot_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);
        if (! array_filter([$data['e_faktur_reference'] ?? null, $data['e_bupot_reference'] ?? null, $data['e_faktur_number'] ?? null, $data['e_bupot_number'] ?? null])) {
            return response()->json(['success' => false, 'message' => 'Referensi e-Faktur atau e-Bupot wajib diisi sebelum pelaporan.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $previous = ['status' => $taxTransaction->status];
        $taxTransaction->update([...$data, 'status' => 'reported', 'reported_by' => $request->user()->id, 'reported_at' => now(), 'updated_by' => $request->user()->id]);
        \App\Models\AuditLog::create(['user_id' => $request->user()->id, 'module' => 'tax', 'platform' => strtolower($request->header('X-Client-Platform', 'web')), 'action' => 'REPORT', 'entity_type' => TaxTransaction::class, 'entity_id' => $taxTransaction->id, 'previous_values' => $previous, 'new_values' => ['status' => 'reported'], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return response()->json(['success' => true, 'message' => 'Transaksi pajak ditandai sudah dilaporkan.', 'data' => $this->format($taxTransaction->fresh('tax:id,code,name,tax_type,rate_percent'))]);
    }

    /**
     * Export tax transactions as CSV file formatted for DJP Online import.
     */
    public function exportDjp(Request $request): StreamedResponse
    {
        $query = TaxTransaction::query()
            ->with('tax:id,code,name,tax_type,rate_percent')
            ->when($request->filled('start_date'), fn (Builder $query) => $query->whereDate('transaction_date', '>=', $request->string('start_date')))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->whereDate('transaction_date', '<=', $request->string('end_date')))
            ->when($request->filled('direction'), fn (Builder $query) => $query->where('direction', $request->string('direction')))
            ->where('status', 'reported');

        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', null, null, []);

        $items = $query->orderBy('transaction_date')
            ->get();

        \App\Models\AuditLog::create(['user_id' => $request->user()->id, 'module' => 'tax', 'platform' => strtolower($request->header('X-Client-Platform', 'web')), 'action' => 'EXPORT', 'entity_type' => TaxTransaction::class, 'entity_id' => null, 'previous_values' => null, 'new_values' => ['format' => 'djp_csv', 'rows' => $items->count()], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        $filename = 'djp_export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');

            // BOM for UTF-8 Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row matching DJP Online CSV import format
            fputcsv($handle, [
                'Tanggal', 'Jenis Pajak', 'Kode Pajak', 'Arah',
                'NPWP Lawan', 'No Faktur/Bupot', 'Referensi',
                'DPP (Rp)', 'Tarif (%)', 'PPh/PPN (Rp)',
                'Bruto (Rp)', 'Neto (Rp)', 'Status', 'Catatan',
            ]);

            foreach ($items as $item) {
                $fakturOrBupot = $item->e_faktur_number
                    ?: ($item->e_bupot_number ?: ($item->e_faktur_reference ?: $item->e_bupot_reference));

                fputcsv($handle, [
                    $item->transaction_date?->format('d/m/Y'),
                    $item->tax?->tax_type ?? '',
                    $item->tax?->code ?? '',
                    $item->direction,
                    $item->npwp ?? '',
                    $fakturOrBupot ?? '',
                    $item->reference ?? '',
                    number_format((float) $item->taxable_amount, 2, '.', ''),
                    number_format((float) $item->tax_rate, 4, '.', ''),
                    number_format((float) $item->tax_amount, 2, '.', ''),
                    number_format((float) $item->gross_amount, 2, '.', ''),
                    number_format((float) $item->net_amount, 2, '.', ''),
                    $item->status,
                    $item->notes ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $query = TaxTransaction::query()
            ->with('tax:id,code,name,tax_type,rate_percent')
            ->when($request->filled('start_date'), fn (Builder $query) => $query->whereDate('transaction_date', '>=', $request->string('start_date')))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->whereDate('transaction_date', '<=', $request->string('end_date')))
            ->where('status', '!=', 'cancelled');

        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', null, null, []);

        $items = $query->get();

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
            'npwp' => $item->npwp,
            'e_faktur_number' => $item->e_faktur_number,
            'e_bupot_number' => $item->e_bupot_number,
            'status' => $item->status,
            'notes' => $item->notes,
        ];
    }
}
