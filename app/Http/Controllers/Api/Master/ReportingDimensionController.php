<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\ReportingDimension;
use App\Http\Requests\Master\StoreReportingDimensionRequest;
use App\Http\Requests\Master\UpdateReportingDimensionRequest;
use App\Http\Resources\Master\ReportingDimensionResource;

class ReportingDimensionController extends BaseMasterController
{
    protected string $modelClass = ReportingDimension::class;
    protected string $resourceClass = ReportingDimensionResource::class;
    protected string $storeRequestClass = StoreReportingDimensionRequest::class;
    protected string $updateRequestClass = UpdateReportingDimensionRequest::class;
    protected array $searchableColumns = ['dimension_type', 'code', 'name'];
    protected array $defaultWith = [];
}