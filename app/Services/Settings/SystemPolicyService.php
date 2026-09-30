<?php

namespace App\Services\Settings;

use App\Models\ApplicationSetting;

/** Resolves business-policy switches stored by the System Settings screen. */
class SystemPolicyService
{
    public function requiresExpenseReceipt(): bool
    {
        return $this->policy('require_receipt_for_expense', true);
    }

    public function blocksOverBudget(): bool
    {
        return $this->policy('enable_over_budget_blocking', true);
    }

    public function locksClosedPeriods(): bool
    {
        return $this->policy('hard_lock_closed_periods', true);
    }

    /** Whether VAT/PPN is available for the current organisation. Missing setting
     * keeps the legacy behaviour for existing installations; seeded KT data sets
     * this explicitly to false. */
    public function vatEnabled(): bool
    {
        $setting = ApplicationSetting::query()->where('key', 'tax_policy')->first();
        if (! $setting) return true;

        $value = $setting->value ?? [];
        return array_key_exists('vat_enabled', $value) ? (bool) $value['vat_enabled'] : true;
    }

    private function policy(string $key, bool $default): bool
    {
        $settings = ApplicationSetting::query()->where('key', 'approval_budget_policy')->first()?->value ?? [];

        return array_key_exists($key, $settings) ? (bool) $settings[$key] : $default;
    }
}
