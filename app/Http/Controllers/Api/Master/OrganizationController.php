<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Organization;
use App\Http\Requests\Master\StoreOrganizationRequest;
use App\Http\Requests\Master\UpdateOrganizationRequest;
use App\Http\Resources\Master\OrganizationResource;

class OrganizationController extends BaseMasterController
{
    protected string $modelClass = Organization::class;
    protected string $resourceClass = OrganizationResource::class;
    protected string $storeRequestClass = StoreOrganizationRequest::class;
    protected string $updateRequestClass = UpdateOrganizationRequest::class;
    protected array $searchableColumns = ['code', 'name', 'legal_name', 'npwp'];
    protected array $defaultWith = ['officeLocations', 'departments', 'baseCurrency', 'updatedByUser'];

    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $record = $this->modelClass::findOrFail($id);

        $relatedCount = $record->related_records_count;
        if ($relatedCount > 0 && !request()->boolean('force')) {
            return response()->json([
                'success' => false,
                'cannot_delete' => true,
                'related_count' => $relatedCount,
                'message' => "“{$record->code} - {$record->name}” cannot be deleted because it is still used in {$relatedCount} records.",
            ], \Symfony\Component\HttpFoundation\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $record->delete();

        return $this->successResponse(
            null,
            'Data berhasil dihapus.'
        );
    }
}