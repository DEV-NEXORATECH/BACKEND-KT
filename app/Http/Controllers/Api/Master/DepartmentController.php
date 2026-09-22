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
        if ($request->boolean('options') || $request->query('paginate') === 'false') {
            return $this->successResponse(DepartmentResource::collection($filtered->get()), 'Daftar opsi berhasil dimuat.');
        }

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
    }
}
