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
            $requestedFiscalYearId = $request->query('fiscal_year_id')
                ?: $request->input('fiscal_year_id')
                ?: $request->header('X-Fiscal-Year-Id');

            $fiscalYearId = $requestedFiscalYearId;

            // The active fiscal year is the default context when a client
            // does not explicitly send one. Global records with NULL remain
            // available in every year.
            $fiscalYearId = $fiscalYearId ?: FiscalYear::query()
                ->where('is_active', true)
                ->orderByDesc('year')
                ->value('id');

            if (! $fiscalYearId || ! Schema::hasColumn($query->getModel()->getTable(), 'fiscal_year_id')) {
                return;
            }

            $qualified = $query->getModel()->qualifyColumn('fiscal_year_id');
            if ($requestedFiscalYearId) {
                // Global master records (NULL fiscal year) are reusable in
                // every selected year; year-specific records remain exact.
                $query->where(function (Builder $scope) use ($qualified, $fiscalYearId) {
                    $scope->whereNull($qualified)->orWhere($qualified, (int) $fiscalYearId);
                });
            } else {
                // Without an explicit selection, retain global rows while
                // defaulting assigned records to the active fiscal year.
                $query->where(function (Builder $scope) use ($qualified, $fiscalYearId) {
                    $scope->whereNull($qualified)->orWhere($qualified, (int) $fiscalYearId);
                });
            }
        });

        static::creating(function ($model) {
            if (! $model->getAttribute('fiscal_year_id') && function_exists('request')) {
                $fiscalYearId = request()->query('fiscal_year_id')
                    ?: request()->header('X-Fiscal-Year-Id')
                    ?: request()->input('fiscal_year_id');
                $fiscalYearId = $fiscalYearId ?: FiscalYear::query()
                    ->where('is_active', true)
                    ->orderByDesc('year')
                    ->value('id');
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
