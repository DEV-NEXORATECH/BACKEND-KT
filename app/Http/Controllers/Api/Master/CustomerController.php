<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Requests\Master\StoreCustomerRequest;
use App\Http\Requests\Master\UpdateCustomerRequest;
use App\Http\Resources\Master\CustomerResource;
use App\Models\Master\Customer;

class CustomerController extends BaseMasterController
{
    protected string $modelClass = Customer::class;
    protected string $resourceClass = CustomerResource::class;
    protected string $storeRequestClass = StoreCustomerRequest::class;
    protected string $updateRequestClass = UpdateCustomerRequest::class;
    protected array $searchableColumns = ['code', 'name', 'email', 'phone'];
}
