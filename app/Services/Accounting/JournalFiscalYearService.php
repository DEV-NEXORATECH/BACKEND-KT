<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Journal;
use App\Models\Master\FiscalYear;
use Illuminate\Validation\ValidationException;

class JournalFiscalYearService
{
    public function resolve(string $date, ?int $selected = null): FiscalYear
    {
        if ($selected) {
            $year = FiscalYear::find($selected);
            if (!$year) throw ValidationException::withMessages(['fiscal_year_id' => 'Fiscal Year tidak tersedia.']);
        } else {
            $years = FiscalYear::whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->get();
            if ($years->count() !== 1) throw ValidationException::withMessages(['fiscal_year_id' => 'Pilih Fiscal Year secara eksplisit: periode tanggal tidak ditemukan atau tumpang tindih.']);
            $year = $years->first();
        }
        if ($date < $year->start_date->toDateString() || $date > $year->end_date->toDateString()) {
            throw ValidationException::withMessages(['journal_date' => 'Journal Date di luar periode Fiscal Year yang dipilih.']);
        }
        app(AccountingPeriodService::class)->ensureOpen($date, 'journal_date');
        return $year;
    }

    public function payload(array $payload, \Illuminate\Http\Request $request, ?Journal $journal = null): array
    {
        // Page query/header context is a filter, never a transaction assignment.
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        // Omission preserves the stored year (or resolves a unique year on create).
        // Explicitly clearing the form field must not silently restore another year.
        if (array_key_exists('fiscal_year_id', $body) && ($body['fiscal_year_id'] === null || $body['fiscal_year_id'] === '')) {
            throw ValidationException::withMessages(['fiscal_year_id' => 'Fiscal Year wajib dipilih dan terpisah dari Journal Date.']);
        }
        $selected = $body['fiscal_year_id'] ?? $journal?->fiscal_year_id;
        $payload['fiscal_year_id'] = $this->resolve(\Carbon\CarbonImmutable::parse($payload['journal_date'])->toDateString(), $selected ? (int)$selected : null)->id;
        return $payload;
    }
}
