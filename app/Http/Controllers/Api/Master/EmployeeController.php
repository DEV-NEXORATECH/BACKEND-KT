<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Employee;
use App\Http\Requests\Master\StoreEmployeeRequest;
use App\Http\Requests\Master\UpdateEmployeeRequest;
use App\Http\Resources\Master\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeController extends BaseMasterController
{
    protected string $modelClass = Employee::class;
    protected string $resourceClass = EmployeeResource::class;
    protected string $storeRequestClass = StoreEmployeeRequest::class;
    protected string $updateRequestClass = UpdateEmployeeRequest::class;
    protected array $searchableColumns = ['employee_id_number', 'name', 'email', 'position'];
    protected array $defaultWith = ['department', 'officeLocation', 'positionMaster', 'fiscalYear'];

    public function quality(): JsonResponse
    {
        $employees = Employee::query();
        $total = (clone $employees)->count();
        $missingStaffId = (clone $employees)->where(function ($query) {
            $query->whereNull('employee_id_number')->orWhere('employee_id_number', '');
        })->count();
        $duplicates = (clone $employees)->whereNotNull('employee_id_number')->where('employee_id_number', '!=', '')
            ->select('employee_id_number')->groupBy('employee_id_number')->havingRaw('COUNT(*) > 1')->get()->count();
        $missingDepartment = (clone $employees)->whereNull('department_id')->count();
        $missingPosition = (clone $employees)->where(function ($query) {
            $query->whereNull('position_id')->where(function ($q) { $q->whereNull('position')->orWhere('position', ''); });
        })->count();
        $inactive = (clone $employees)->where('is_active', false)->count();
        $nonEmployeeMapping = 0;
        if (Schema::hasColumn('users', 'employee_id')) {
            $nonEmployeeMapping = DB::table('users')->whereNotNull('employee_id')->whereNotIn('employee_id', Employee::query()->pluck('id'))->count();
        } else {
            $nonEmployeeMapping = DB::table('users')->whereNotIn('email', Employee::query()->whereNotNull('email')->pluck('email'))->count();
        }

        return response()->json(['success' => true, 'data' => [
            'total_employee' => $total,
            'valid_employee' => max(0, $total - $missingStaffId - $duplicates - $missingDepartment - $missingPosition),
            'missing_staff_id' => $missingStaffId,
            'duplicate_staff_id' => $duplicates,
            'missing_department' => $missingDepartment,
            'missing_position' => $missingPosition,
            'inactive' => $inactive,
            'non_employee_mapping' => $nonEmployeeMapping,
        ]]);
    }
}
