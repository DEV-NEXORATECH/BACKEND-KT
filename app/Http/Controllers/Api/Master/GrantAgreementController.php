<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\GrantAgreement;
use App\Http\Requests\Master\StoreGrantAgreementRequest;
use App\Http\Requests\Master\UpdateGrantAgreementRequest;
use App\Http\Resources\Master\GrantAgreementResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GrantAgreementController extends BaseMasterController
{
    protected string $modelClass = GrantAgreement::class;
    protected string $resourceClass = GrantAgreementResource::class;
    protected string $storeRequestClass = StoreGrantAgreementRequest::class;
    protected string $updateRequestClass = UpdateGrantAgreementRequest::class;
    protected array $searchableColumns = ['grant_no', 'agreement_name'];
    protected array $defaultWith = ['donor', 'fundingSource', 'currency', 'bankAccount'];

    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = app($this->storeRequestClass)->validated();
        if ($request->hasFile('agreement_document')) {
            $validated['agreement_document_path'] = $request->file('agreement_document')->store('grant-agreements', 'public');
        }
        unset($validated['agreement_document']);
        $record = $this->modelClass::create($validated);
        $record->loadMissing($this->defaultWith);
        return $this->successResponse(new $this->resourceClass($record), 'Data berhasil ditambahkan.', Response::HTTP_CREATED);
    }

    public function update(\Illuminate\Http\Request $request, $id): JsonResponse
    {
        $record = $this->modelClass::findOrFail($id);
        $validated = app($this->updateRequestClass)->validated();
        if ($request->hasFile('agreement_document')) {
            $validated['agreement_document_path'] = $request->file('agreement_document')->store('grant-agreements', 'public');
        }
        unset($validated['agreement_document']);
        $record->update($validated);
        $record->loadMissing($this->defaultWith);
        return $this->successResponse(new $this->resourceClass($record), 'Data berhasil diperbarui.');
    }
}
