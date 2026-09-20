<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\ApprovalMatrix;
use App\Http\Requests\Master\StoreApprovalMatrixRequest;
use App\Http\Requests\Master\UpdateApprovalMatrixRequest;
use App\Http\Resources\Master\ApprovalMatrixResource;

class ApprovalMatrixController extends BaseMasterController
{
    protected string $modelClass = ApprovalMatrix::class;
    protected string $resourceClass = ApprovalMatrixResource::class;
    protected string $storeRequestClass = StoreApprovalMatrixRequest::class;
    protected string $updateRequestClass = UpdateApprovalMatrixRequest::class;
    protected array $searchableColumns = ['module', 'approver_title', 'description'];
    protected array $defaultWith = ['role', 'user', 'employee', 'project', 'donor', 'department'];
}