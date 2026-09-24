<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Project;
use App\Http\Requests\Master\StoreProjectRequest;
use App\Http\Requests\Master\UpdateProjectRequest;
use App\Http\Resources\Master\ProjectResource;

class ProjectController extends BaseMasterController
{
    protected string $modelClass = Project::class;
    protected string $resourceClass = ProjectResource::class;
    protected string $storeRequestClass = StoreProjectRequest::class;
    protected string $updateRequestClass = UpdateProjectRequest::class;
    protected array $searchableColumns = ['code', 'name', 'manager_name'];
    protected array $defaultWith = ['program', 'grantAgreement', 'budgetCurrency', 'bankAccount'];
}
