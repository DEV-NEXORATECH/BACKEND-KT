<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Department;
use App\Http\Requests\Master\StoreDepartmentRequest;
use App\Http\Requests\Master\UpdateDepartmentRequest;
use App\Http\Resources\Master\DepartmentResource;

class DepartmentController extends BaseMasterController
{
    protected string $modelClass = Department::class;
    protected string $resourceClass = DepartmentResource::class;
    protected string $storeRequestClass = StoreDepartmentRequest::class;
    protected string $updateRequestClass = UpdateDepartmentRequest::class;
    protected array $searchableColumns = ['code', 'name', 'manager_name'];
    protected array $defaultWith = ['organization', 'parent'];
}