<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Employee;
use App\Http\Requests\Master\StoreEmployeeRequest;
use App\Http\Requests\Master\UpdateEmployeeRequest;
use App\Http\Resources\Master\EmployeeResource;

class EmployeeController extends BaseMasterController
{
    protected string $modelClass = Employee::class;
    protected string $resourceClass = EmployeeResource::class;
    protected string $storeRequestClass = StoreEmployeeRequest::class;
    protected string $updateRequestClass = UpdateEmployeeRequest::class;
    protected array $searchableColumns = ['employee_id_number', 'name', 'email', 'position'];
    protected array $defaultWith = ['department', 'officeLocation'];
}