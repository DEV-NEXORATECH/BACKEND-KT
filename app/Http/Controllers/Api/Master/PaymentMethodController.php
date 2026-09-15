<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\PaymentMethod;
use App\Http\Requests\Master\StorePaymentMethodRequest;
use App\Http\Requests\Master\UpdatePaymentMethodRequest;
use App\Http\Resources\Master\PaymentMethodResource;

class PaymentMethodController extends BaseMasterController
{
    protected string $modelClass = PaymentMethod::class;
    protected string $resourceClass = PaymentMethodResource::class;
    protected string $storeRequestClass = StorePaymentMethodRequest::class;
    protected string $updateRequestClass = UpdatePaymentMethodRequest::class;
    protected array $searchableColumns = ['code', 'name', 'type'];
    protected array $defaultWith = ['defaultGlAccount'];
}