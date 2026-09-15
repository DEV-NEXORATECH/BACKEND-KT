<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\AssetCategory;
use App\Http\Requests\Master\StoreAssetCategoryRequest;
use App\Http\Requests\Master\UpdateAssetCategoryRequest;
use App\Http\Resources\Master\AssetCategoryResource;

class AssetCategoryController extends BaseMasterController
{
    protected string $modelClass = AssetCategory::class;
    protected string $resourceClass = AssetCategoryResource::class;
    protected string $storeRequestClass = StoreAssetCategoryRequest::class;
    protected string $updateRequestClass = UpdateAssetCategoryRequest::class;
    protected array $searchableColumns = ['code', 'name'];
    protected array $defaultWith = ['assetGlAccount', 'depreciationGlAccount', 'accumulatedGlAccount'];
}