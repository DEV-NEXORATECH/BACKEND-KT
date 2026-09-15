<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\BankAccount;
use App\Http\Requests\Master\StoreBankAccountRequest;
use App\Http\Requests\Master\UpdateBankAccountRequest;
use App\Http\Resources\Master\BankAccountResource;

class BankAccountController extends BaseMasterController
{
    protected string $modelClass = BankAccount::class;
    protected string $resourceClass = BankAccountResource::class;
    protected string $storeRequestClass = StoreBankAccountRequest::class;
    protected string $updateRequestClass = UpdateBankAccountRequest::class;
    protected array $searchableColumns = ['bank_name', 'account_number', 'account_name'];
    protected array $defaultWith = ['organization', 'currency', 'glAccount'];
}