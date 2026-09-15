<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Program;
use App\Http\Requests\Master\StoreProgramRequest;
use App\Http\Requests\Master\UpdateProgramRequest;
use App\Http\Resources\Master\ProgramResource;

class ProgramController extends BaseMasterController
{
    protected string $modelClass = Program::class;
    protected string $resourceClass = ProgramResource::class;
    protected string $storeRequestClass = StoreProgramRequest::class;
    protected string $updateRequestClass = UpdateProgramRequest::class;
    protected array $searchableColumns = ['code', 'name', 'manager_name'];
    protected array $defaultWith = [];
}