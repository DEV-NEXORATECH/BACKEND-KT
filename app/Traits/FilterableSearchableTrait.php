<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FilterableSearchableTrait
{
    public function scopeApplyFilters(Builder $query, Request $request, array $searchable = []): Builder
    {
        if ($request->filled('search') && ! empty($searchable)) {
            $search = $request->query('search');
            $query->where(function (Builder $sub) use ($searchable, $search) {
                foreach ($searchable as $index => $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $relCol] = explode('.', $column, 2);
                        $sub->orWhereHas($relation, function (Builder $relQuery) use ($relCol, $search) {
                            $relQuery->where($relCol, 'like', "%{$search}%");
                        });
                    } else {
                        if ($index === 0) {
                            $sub->where($column, 'like', "%{$search}%");
                        } else {
                            $sub->orWhere($column, 'like', "%{$search}%");
                        }
                    }
                }
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '' && $request->input('is_active') !== null) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('parent_id')) {
            $val = $request->query('parent_id');
            if ($val === 'null' || $val === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $val);
            }
        }

        // Relational filter helpers
        $filterRelations = [
            'organization_id', 'department_id', 'donor_id', 'funding_source_id',
            'grant_agreement_id', 'program_id', 'project_id', 'budget_category_id',
            'currency_id', 'gl_account_id', 'office_location_id', 'role_id'
        ];

        foreach ($filterRelations as $filterCol) {
            if ($request->filled($filterCol)) {
                $query->where($filterCol, $request->query($filterCol));
            }
        }

        $sortBy = $request->query('sort_by', 'created_at');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (\Schema::hasColumn($this->getTable(), $sortBy)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy($this->getKeyName(), 'desc');
        }

        return $query;
    }
}