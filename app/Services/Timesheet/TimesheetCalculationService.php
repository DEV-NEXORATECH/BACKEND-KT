<?php

namespace App\Services\Timesheet;

use App\Models\Master\Employee;

class TimesheetCalculationService
{
    /**
     * Calculate billable hours and amount for a timesheet entry.
     *
     * @param  Employee|null  $employee
     * @param  float  $hours
     * @param  string|null  $rateScheme  ('daily_capped_8h' | 'hourly_unlimited')
     * @param  float|null  $overrideHourlyRate
     * @return array{
     *     rate_scheme: string,
     *     applied_rate: float,
     *     billable_hours: float,
     *     calculated_amount: float
     * }
     */
    public function calculate(?Employee $employee, float $hours, ?string $rateScheme = null, ?float $overrideHourlyRate = null): array
    {
        $scheme = $rateScheme ?: ($employee?->default_rate_scheme ?: 'daily_capped_8h');
        
        $hourlyRate = $overrideHourlyRate;
        if ($hourlyRate === null) {
            $hourlyRate = (float) ($employee?->hourly_cost_rate ?? 0);
            if ($hourlyRate <= 0 && (float) ($employee?->daily_cost_rate ?? 0) > 0) {
                $hourlyRate = round((float) $employee->daily_cost_rate / 8, 2);
            }
        }

        $hours = max(0, $hours);
        
        if ($scheme === 'daily_capped_8h') {
            $billableHours = min($hours, 8.0);
        } else {
            $billableHours = $hours;
        }

        $billableHours = round($billableHours, 2);
        $calculatedAmount = round($billableHours * $hourlyRate, 2);

        return [
            'rate_scheme' => $scheme,
            'applied_rate' => round($hourlyRate, 2),
            'billable_hours' => $billableHours,
            'calculated_amount' => $calculatedAmount,
        ];
    }
}
