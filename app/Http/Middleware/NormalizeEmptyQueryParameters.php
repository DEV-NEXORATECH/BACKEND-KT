<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeEmptyQueryParameters
{
    /**
     * Query parameters used as integer filters must be omitted when empty.
     * React's URLSearchParams otherwise serializes them as `fiscal_year_id=`,
     * which can reach validators and query scopes as an invalid value.
     */
    private const NULLABLE_INTEGER_FILTERS = [
        'fiscal_year_id',
        'project_id',
        'grant_agreement_id',
        'donor_id',
        'program_id',
        'activity_id',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $query = $request->query->all();

        foreach (self::NULLABLE_INTEGER_FILTERS as $filter) {
            if (array_key_exists($filter, $query) && trim((string) $query[$filter]) === '') {
                unset($query[$filter]);
            }
        }

        $request->query->replace($query);

        return $next($request);
    }
}
