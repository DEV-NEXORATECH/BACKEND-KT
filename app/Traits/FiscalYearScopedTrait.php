<?php

namespace App\Traits;

use App\Models\Master\FiscalYear;

trait FiscalYearScopedTrait
{
    protected static function bootFiscalYearScopedTrait(): void
    {
        static::creating(function ($model) {
            if (! $model->getAttribute('fiscal_year_id') && function_exists('request')) {
                $fiscalYearId = request()->header('X-Fiscal-Year-Id') ?: request()->input('fiscal_year_id');
                if ($fiscalYearId && FiscalYear::query()->whereKey($fiscalYearId)->exists()) {
                    $model->setAttribute('fiscal_year_id', (int) $fiscalYearId);
                }
            }
        });
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }
}
