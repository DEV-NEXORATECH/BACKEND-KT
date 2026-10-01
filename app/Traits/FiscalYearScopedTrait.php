<?php

namespace App\Traits;

use App\Models\Master\FiscalYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait FiscalYearScopedTrait
{
    protected static function bootFiscalYearScopedTrait(): void
    {
        static::addGlobalScope('fiscal_year_context', function (Builder $query) {
            if (! function_exists('request')) {
                return;
            }

            $request = request();
            $fiscalYearId = $request->query('fiscal_year_id')
                ?: $request->input('fiscal_year_id')
                ?: $request->header('X-Fiscal-Year-Id');

            if (! $fiscalYearId || ! Schema::hasColumn($query->getModel()->getTable(), 'fiscal_year_id')) {
                return;
            }

            // NULL remains a global record and is intentionally available in
            // every year. Records assigned to a year are isolated to that year.
            $qualified = $query->getModel()->qualifyColumn('fiscal_year_id');
            $query->where(function (Builder $scope) use ($qualified, $fiscalYearId) {
                $scope->whereNull($qualified)->orWhere($qualified, (int) $fiscalYearId);
            });
        });

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
