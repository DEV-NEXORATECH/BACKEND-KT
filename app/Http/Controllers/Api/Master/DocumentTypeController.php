<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\DocumentType;
use App\Http\Requests\Master\StoreDocumentTypeRequest;
use App\Http\Requests\Master\UpdateDocumentTypeRequest;
use App\Http\Resources\Master\DocumentTypeResource;

class DocumentTypeController extends BaseMasterController
{
    protected string $modelClass = DocumentType::class;
    protected string $resourceClass = DocumentTypeResource::class;
    protected string $storeRequestClass = StoreDocumentTypeRequest::class;
    protected string $updateRequestClass = UpdateDocumentTypeRequest::class;
    protected array $searchableColumns = ['code', 'name'];
    protected array $defaultWith = [];
}