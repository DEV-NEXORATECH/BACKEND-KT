<?php

namespace App\Services\Accounting;

use App\Models\Master\AccountingPeriod;
use App\Services\Settings\SystemPolicyService;
use Illuminate\Validation\ValidationException;

/** Prevent financial postings from being created in a closed accounting period or fiscal year. */
class AccountingPeriodService
{
    public function ensureOpen(string $date, string $field = 'transaction_date'): void
    {
        if (! app(SystemPolicyService::class)->locksClosedPeriods()) {
            return;
        }

        $period = AccountingPeriod::query()
            ->where('status', 'closed')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if ($period) {
            throw ValidationException::withMessages([
                $field => 'Tanggal transaksi berada pada accounting period yang sudah closed.',
            ]);
        }

        $closedFiscalYear = \App\Models\Master\FiscalYear::query()
            ->where('status', 'closed')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($closedFiscalYear) {
            throw ValidationException::withMessages([
                $field => 'Tanggal transaksi berada pada fiscal year yang sudah closed.',
            ]);
        }
    }
}
