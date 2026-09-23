<?php

namespace App\Services\Accounting;

use App\Models\Master\AccountingPeriod;
use App\Services\Settings\SystemPolicyService;
use Illuminate\Validation\ValidationException;

/** Prevent financial postings from being created in a closed accounting period. */
class AccountingPeriodService
{
    public function ensureOpen(string $date, string $field = 'transaction_date'): void
    {
        if (! app(SystemPolicyService::class)->locksClosedPeriods()) {
            return;
        }

        $isClosed = AccountingPeriod::query()
            ->where('status', 'closed')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($isClosed) {
            throw ValidationException::withMessages([
                $field => 'Tanggal transaksi berada pada accounting period yang sudah closed.',
            ]);
        }
    }
}
