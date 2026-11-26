<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Tax;
use App\Http\Requests\Master\StoreTaxRequest;
use App\Http\Requests\Master\UpdateTaxRequest;
use App\Http\Resources\Master\TaxResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends BaseMasterController
{
    protected string $modelClass = Tax::class;
    protected string $resourceClass = TaxResource::class;
    protected string $storeRequestClass = StoreTaxRequest::class;
    protected string $updateRequestClass = UpdateTaxRequest::class;
    protected array $searchableColumns = ['code', 'name', 'tax_type'];
    protected array $defaultWith = ['salesGlAccount', 'purchaseGlAccount'];

    public function index(Request $request): JsonResponse
    {
        $query = Tax::query()->withoutGlobalScope('fiscal_year_context')->with($this->defaultWith);
        $query->when($request->filled('search'), function ($builder) use ($request) {
            $term = $request->query('search');
            $builder->where(function ($scope) use ($term) {
                $scope->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('tax_type', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        });
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $patterns = match ($request->query('tax_tab', 'all')) {
            'pph21' => ['pph21', 'pph 21', 'staff / employee', 'non-employee / professional'],
            'pph23' => ['pph23', 'pph 23', 'corporate / service income'],
            'pph42' => ['pph_final', 'pph final', 'rental of building/land', '4(2)'],
            'vat' => ['ppn', 'vat'],
            default => [],
        };
        if ($patterns) {
            $query->where(function ($scope) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $scope->orWhere('tax_type', 'like', "%{$pattern}%")
                        ->orWhere('code', 'like', "%{$pattern}%")
                        ->orWhere('name', 'like', "%{$pattern}%");
                }
            });
        } elseif ($request->query('tax_tab') === 'other') {
            foreach (['pph 21', 'pph 23', 'ppn', 'vat', '4(2)', 'final', 'staff'] as $pattern) {
                $query->where(function ($scope) use ($pattern) {
                    $scope->where('tax_type', 'not like', "%{$pattern}%")
                        ->where('code', 'not like', "%{$pattern}%");
                });
            }
        }

        if ($request->boolean('options')) {
            return response()->json([
                'success' => true,
                'data' => TaxResource::collection($query->where('is_active', true)->get()),
            ]);
        }

        $sort = $request->query('sort', 'newest');
        $query->orderBy(match ($sort) {
            'name_asc', 'name_desc' => 'name',
            'rate_desc', 'rate_asc' => 'rate_percent',
            default => 'created_at',
        }, in_array($sort, ['name_desc', 'rate_desc', 'newest'], true) ? 'desc' : 'asc');

        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));
        $paginated = $query->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => TaxResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(), 'total' => $paginated->total(),
            ],
        ]);
    }
}
