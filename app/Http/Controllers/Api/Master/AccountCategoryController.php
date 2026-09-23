<?php
namespace App\Http\Controllers\Api\Master;
use App\Http\Requests\Master\StoreAccountCategoryRequest; use App\Http\Requests\Master\UpdateAccountCategoryRequest; use App\Http\Resources\Master\ProcurementMasterResource; use App\Models\Master\AccountCategory;
class AccountCategoryController extends BaseMasterController { protected string $modelClass=AccountCategory::class; protected string $resourceClass=ProcurementMasterResource::class; protected string $storeRequestClass=StoreAccountCategoryRequest::class; protected string $updateRequestClass=UpdateAccountCategoryRequest::class; protected array $searchableColumns=['code','name']; }
