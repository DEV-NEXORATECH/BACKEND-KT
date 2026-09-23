<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Requests\Master\StoreVendorCategoryRequest;
use App\Http\Requests\Master\UpdateVendorCategoryRequest;
use App\Http\Resources\Master\ProcurementMasterResource;
use App\Models\Master\VendorCategory;

class VendorCategoryController extends BaseMasterController
{
    protected string $modelClass = VendorCategory::class;
    protected string $resourceClass = ProcurementMasterResource::class;
    protected string $storeRequestClass = StoreVendorCategoryRequest::class;
    protected string $updateRequestClass = UpdateVendorCategoryRequest::class;
    protected array $searchableColumns = ['code', 'name'];
}
