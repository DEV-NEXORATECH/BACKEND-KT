<?php

namespace App\Services\Accounting;

use App\Models\ApplicationSetting;
use App\Models\Master\AccountingPeriod;
use Illuminate\Validation\ValidationException;

/** Prevent financial postings from being created in a closed accounting period. */
class AccountingPeriodService
{
    public function ensureOpen(string $date, string $field = 'transaction_date'): void
    {
        $policy = ApplicationSetting::query()->where('key', 'approval_budget_policy')->first();
        $settings = $policy?->value ?? [];
        if (($settings['hard_lock_closed_periods'] ?? true) === false) {
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
