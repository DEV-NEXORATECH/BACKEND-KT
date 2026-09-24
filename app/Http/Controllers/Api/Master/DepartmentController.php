<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Department;
use App\Http\Requests\Master\StoreDepartmentRequest;
use App\Http\Requests\Master\UpdateDepartmentRequest;
use App\Http\Resources\Master\DepartmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class DepartmentController extends BaseMasterController
{
    protected string $modelClass = Department::class;
    protected string $resourceClass = DepartmentResource::class;
    protected string $storeRequestClass = StoreDepartmentRequest::class;
    protected string $updateRequestClass = UpdateDepartmentRequest::class;
    protected array $searchableColumns = ['code', 'name', 'manager_name'];
    protected array $defaultWith = ['organization', 'parent'];
    protected array $defaultWithCount = [];

    public function index(Request $request): JsonResponse
    {
        try {
            if ($request->boolean('options') || $request->query('paginate') === 'false') {
                $optionsQuery = Department::query();
                if (! $request->has('is_active') && Schema::hasColumn('departments', 'is_active')) {
                    $optionsQuery->where('is_active', true);
                }
                $filteredOptions = $optionsQuery->applyFilters($request, $this->searchableColumns);
                return $this->successResponse(DepartmentResource::collection($filteredOptions->get()), 'Daftar opsi berhasil dimuat.');
            }

            $query = Department::query()->with($this->defaultWith);
            $countRelations = [
                'children' => 'departments',
                'costCenters' => 'cost_centers',
                'employees' => 'employees',
                'approvalMatrices' => 'approval_matrices',
                'journalLines' => 'journal_lines',
                'purchaseRequests' => 'purchase_requests',
                'expenseRequests' => 'expense_requests',
                'timesheetEntries' => 'timesheet_entries',
            ];
            foreach ($countRelations as $relation => $table) {
                if (Schema::hasTable($table)) {
                    $query->withCount($relation);
                }
            }

            $filtered = $query->applyFilters($request, $this->searchableColumns);
            $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
            $paginated = $filtered->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dimuat.',
                'data' => DepartmentResource::collection($paginated->items()),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Department index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data departemen: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
