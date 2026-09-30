<?php

namespace App\Services\Tax;

use App\Models\Master\Tax;
use Illuminate\Validation\ValidationException;

class TaxCalculationService
{
    public function calculate(Tax $tax, float $amount, bool $inclusive = false, string $direction = 'purchase'): array
    {
        $rate = (float) $tax->rate_percent;
        $factor = $rate / 100;
        $taxable = $inclusive && $factor > 0 ? round($amount / (1 + $factor), 2) : round($amount, 2);
        $method = $tax->applicable_rule ?: 'flat_rate';
        $taxAmount = round($taxable * $factor, 2);
        if (str_contains(strtolower($method), 'progress') && strtoupper((string) $tax->tax_type) === 'PPH21') {
            $tiers = [[60000000, .05], [250000000, .15], [500000000, .25], [5000000000, .30], [PHP_FLOAT_MAX, .35]];
            $remaining = $taxable; $previous = 0; $taxAmount = 0;
            foreach ($tiers as [$maximum, $tierRate]) { $portion = max(0, min($remaining, $maximum - $previous)); $taxAmount += $portion * $tierRate; $remaining -= $portion; $previous = $maximum; if ($remaining <= 0) break; }
            $rate = $taxable > 0 ? round(($taxAmount / $taxable) * 100, 4) : 0;
            $factor = $rate / 100;
            $method = 'pasal_17_progressive';
        }
        return [
            'tax_rule' => $tax->applicable_rule ?: $tax->code,
            'tax_id' => $tax->id, 'tax_type' => $tax->tax_type, 'rate' => $rate,
            'taxable_base' => $taxable, 'taxable_amount' => $taxable, 'tax_amount' => $taxAmount,
            'gross_amount' => $inclusive ? round($amount, 2) : round($taxable + $taxAmount, 2),
            'effective_date' => $tax->effective_start_date?->toDateString(),
            'calculation_method' => $method,
            'direction' => $direction,
        ];
    }

    public function resolve(array $data): array
    {
        $date = $data['tax_date'] ?? now()->toDateString();
        $tax = isset($data['tax_id']) ? Tax::find($data['tax_id']) : Tax::query()->whereRaw('upper(tax_type) = ?', [strtoupper($data['tax_type'])])->where('is_active', true)->first();
        if (! $tax || ! $tax->is_active || ($tax->effective_start_date && $date < $tax->effective_start_date->toDateString()) || ($tax->effective_end_date && $date > $tax->effective_end_date->toDateString())) {
            throw ValidationException::withMessages(['tax' => 'Tax rule tidak aktif atau tidak berlaku pada tanggal tersebut.']);
        }
        return $this->calculate($tax, (float) $data['gross_amount'], (bool) ($data['is_inclusive'] ?? false), $data['direction'] ?? 'purchase');
    }
}
