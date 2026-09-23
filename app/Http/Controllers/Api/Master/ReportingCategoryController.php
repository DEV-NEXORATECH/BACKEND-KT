<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Requests\Master\StoreReportingCategoryRequest;
use App\Http\Requests\Master\UpdateReportingCategoryRequest;
use App\Http\Resources\Master\ReportingCategoryResource;
use App\Models\Master\ReportingCategory;

class ReportingCategoryController extends BaseMasterController
{
    protected string $modelClass = ReportingCategory::class;
    protected string $resourceClass = ReportingCategoryResource::class;
    protected string $storeRequestClass = StoreReportingCategoryRequest::class;
    protected string $updateRequestClass = UpdateReportingCategoryRequest::class;
    protected array $searchableColumns = ['code', 'name', 'category_type'];
}
