<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Vendor;
use App\Http\Requests\Master\StoreVendorRequest;
use App\Http\Requests\Master\UpdateVendorRequest;
use App\Http\Resources\Master\VendorResource;

class VendorController extends BaseMasterController
{
    protected string $modelClass = Vendor::class;
    protected string $resourceClass = VendorResource::class;
    protected string $storeRequestClass = StoreVendorRequest::class;
    protected string $updateRequestClass = UpdateVendorRequest::class;
    protected array $searchableColumns = ['code', 'name', 'npwp', 'contact_person', 'email'];
    protected array $defaultWith = ['tax'];
}