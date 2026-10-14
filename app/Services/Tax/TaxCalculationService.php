<?php

namespace App\Services\Tax;

use App\Models\Master\Tax;
use Illuminate\Validation\ValidationException;

class TaxCalculationService
{
    public function calculate(Tax $tax, float $amount, bool $inclusive = false, string $direction = 'purchase', string $calculationMethod = 'gross', array $options = []): array
    {
        $type = strtoupper((string) $tax->tax_type);
        if ($type === 'PPH21' && ! empty($options['recipient_category'])) {
            return $this->calculatePph21($tax, $amount, $direction, $calculationMethod, $options);
        }

        $rate = (float) $tax->rate_percent;
        $factor = $rate / 100;
        $taxable = $inclusive && $factor > 0 ? round($amount / (1 + $factor), 2) : round($amount, 2);
        $method = $tax->applicable_rule ?: 'flat_rate';
        $isWithholdingGrossUpType = in_array(strtoupper((string) $tax->tax_type), ['PPH23', 'PPH_FINAL', 'PPH42'], true);
        $isGrossUp = $calculationMethod === 'gross_up' && $isWithholdingGrossUpType && $factor > 0 && $factor < 1;
        if ($isGrossUp) {
            // In gross-up mode the input is the net amount the recipient must receive.
            $taxable = round($taxable / (1 - $factor), 2);
        }
        $taxAmount = round($taxable * $factor, 2);
        $layers = [];
        if (str_contains(strtolower($method), 'progress') && $type === 'PPH21') {
            $tiers = $tax->calculation_config['pasal_17_tiers'] ?? [
                ['up_to' => 60000000, 'rate_percent' => 5], ['up_to' => 250000000, 'rate_percent' => 15],
                ['up_to' => 500000000, 'rate_percent' => 25], ['up_to' => 5000000000, 'rate_percent' => 30],
                ['up_to' => 999999999999999, 'rate_percent' => 35],
            ];
            $progressive = $this->progressiveTax($taxable, $tiers);
            $taxAmount = $progressive['tax_amount'];
            $rate = $taxable > 0 ? round(($taxAmount / $taxable) * 100, 4) : 0;
            $method = 'pasal_17_progressive';
            $layers = $progressive['layers'];
        }
        return [
            'tax_rule' => $tax->applicable_rule ?: $tax->code,
            'tax_id' => $tax->id, 'tax_type' => $tax->tax_type, 'rate' => $rate,
            'taxable_base' => $taxable, 'taxable_amount' => $taxable, 'tax_amount' => $taxAmount,
            'progressive_layers' => $layers,
            'gross_amount' => $isGrossUp ? $taxable : ($inclusive ? round($amount, 2) : round($taxable + $taxAmount, 2)),
            'effective_date' => $tax->effective_start_date?->toDateString(),
            'calculation_method' => $method,
            'withholding_method' => $isGrossUp ? 'gross_up' : 'gross',
            'tax_allowance' => $isGrossUp ? round($taxable - $amount, 2) : 0,
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
        return $this->calculate($tax, (float) $data['gross_amount'], (bool) ($data['is_inclusive'] ?? false), $data['direction'] ?? 'purchase', $data['calculation_method'] ?? 'gross', $data);
    }

    private function calculatePph21(Tax $tax, float $gross, string $direction, string $calculationMethod, array $options): array
    {
        $config = $tax->calculation_config ?: [];
        $category = (string) $options['recipient_category'];
        $date = $options['tax_date'] ?? now()->toDateString();
        $year = (int) substr($date, 0, 4);
        $dppPercent = (float) ($config['dpp_percent'][$category] ?? $config['dpp_percent']['default'] ?? 50);
        $dpp = round($gross * $dppPercent / 100, 2);
        if ($category === 'final') {
            $tiers = $config['final_tiers'] ?? [];
            if (! $tiers) {
                throw ValidationException::withMessages(['tax' => 'Konfigurasi PPh 21 Final belum tersedia di Tax Master.']);
            }
            $accumulatedGross = max(0, (float) ($options['accumulated_gross'] ?? 0));
            $priorTax = $this->progressiveTax($accumulatedGross, $tiers)['tax_amount'];
            $calculation = $this->progressiveTax($accumulatedGross + $gross, $tiers);
            $currentTax = round(max(0, $calculation['tax_amount'] - $priorTax), 2);
            return [
                'tax_rule' => $tax->applicable_rule ?: $tax->code, 'tax_id' => $tax->id, 'tax_type' => $tax->tax_type,
                'recipient_category' => $category, 'object_code' => $options['object_code'] ?? null, 'gross_amount' => round($gross, 2), 'dpp_percent' => 100,
                'taxable_base' => round($gross, 2), 'taxable_amount' => round($gross, 2), 'rate' => $gross > 0 ? round(($calculation['tax_amount'] / $gross) * 100, 4) : 0,
                'rate_basis' => 'Tarif PPh 21 Final berdasarkan Tax Master', 'progressive_layers' => $calculation['layers'],
                'accumulated_gross' => $accumulatedGross, 'tax_amount' => $currentTax, 'take_home_pay' => round($gross - $currentTax, 2),
                'effective_date' => $tax->effective_start_date?->toDateString(), 'tax_year' => $year,
                'calculation_method' => 'pph21_final', 'withholding_method' => $calculationMethod, 'tax_allowance' => 0, 'direction' => $direction,
            ];
        }
        if ($category === 'daily') {
            $days = max(1, (int) ($options['work_days'] ?? 1));
            $dailyGross = round($gross / $days, 2);
            foreach (($config['daily_brackets'] ?? []) as $bracket) {
                if ($dailyGross <= (float) ($bracket['up_to'] ?? PHP_FLOAT_MAX)) {
                    $rate = (float) ($bracket['rate_percent'] ?? 0);
                    $taxAmount = round($gross * $rate / 100, 2);
                    return [
                        'tax_rule' => $tax->applicable_rule ?: $tax->code, 'tax_id' => $tax->id, 'tax_type' => $tax->tax_type,
                        'recipient_category' => $category, 'gross_amount' => round($gross, 2), 'dpp_percent' => 100,
                        'taxable_base' => round($gross, 2), 'taxable_amount' => round($gross, 2), 'rate' => $rate,
                        'rate_basis' => 'Tarif harian berdasarkan Tax Master', 'progressive_layers' => [], 'tax_amount' => $taxAmount,
                        'take_home_pay' => round($gross - $taxAmount, 2), 'effective_date' => $tax->effective_start_date?->toDateString(),
                        'tax_year' => $year, 'calculation_method' => 'daily_bracket', 'withholding_method' => $calculationMethod,
                        'tax_allowance' => 0, 'direction' => $direction,
                    ];
                }
            }
        }
        $accumulatedGross = max(0, (float) ($options['accumulated_gross'] ?? 0));
        $accumulatedDpp = round($accumulatedGross * $dppPercent / 100, 2);
        $tiers = $config['pasal_17_tiers'] ?? [];
        if (! $tiers) {
            throw ValidationException::withMessages(['tax' => 'Konfigurasi tarif progresif PPh 21 belum tersedia di Tax Master.']);
        }

        $priorTax = $this->progressiveTax($accumulatedDpp, $tiers)['tax_amount'];
        $totalTax = $this->progressiveTax($accumulatedDpp + $dpp, $tiers);
        $taxAmount = round(max(0, $totalTax['tax_amount'] - $priorTax), 2);
        $npwpMultiplier = ! array_key_exists('has_npwp', $options) || (bool) $options['has_npwp'] ? 1 : (float) ($config['no_npwp_multiplier'] ?? 1.2);
        $taxAmount = round($taxAmount * $npwpMultiplier, 2);

        return [
            'tax_rule' => $tax->applicable_rule ?: $tax->code,
            'tax_id' => $tax->id,
            'tax_type' => $tax->tax_type,
            'recipient_category' => $category,
            'object_code' => $options['object_code'] ?? null,
            'gross_amount' => round($gross, 2),
            'dpp_percent' => $dppPercent,
            'taxable_base' => $dpp,
            'taxable_amount' => $dpp,
            'accumulated_gross' => $accumulatedGross,
            'accumulated_taxable_amount' => $accumulatedDpp,
            'rate' => $dpp > 0 ? round(($taxAmount / $dpp) * 100, 4) : 0,
            'rate_basis' => 'Pasal 17 progressive atas DPP',
            'progressive_layers' => $totalTax['layers'],
            'tax_amount' => $taxAmount,
            'take_home_pay' => round($gross - $taxAmount, 2),
            'effective_date' => $tax->effective_start_date?->toDateString(),
            'tax_year' => $year,
            'calculation_method' => 'pasal_17_progressive',
            'withholding_method' => $calculationMethod,
            'tax_allowance' => 0,
            'direction' => $direction,
        ];
    }

    private function progressiveTax(float $base, array $tiers): array
    {
        $remaining = max(0, $base);
        $previous = 0;
        $taxAmount = 0;
        $layers = [];
        foreach ($tiers as $tier) {
            $maximum = (float) ($tier['up_to'] ?? PHP_FLOAT_MAX);
            $rate = (float) ($tier['rate_percent'] ?? 0) / 100;
            $portion = max(0, min($remaining, $maximum - $previous));
            if ($portion > 0) {
                $layerTax = round($portion * $rate, 2);
                $taxAmount += $layerTax;
                $layers[] = ['from' => $previous, 'up_to' => $maximum, 'base' => round($portion, 2), 'rate_percent' => $rate * 100, 'tax_amount' => $layerTax];
            }
            $remaining -= $portion;
            $previous = $maximum;
            if ($remaining <= 0) break;
        }
        return ['tax_amount' => round($taxAmount, 2), 'layers' => $layers];
    }
}
