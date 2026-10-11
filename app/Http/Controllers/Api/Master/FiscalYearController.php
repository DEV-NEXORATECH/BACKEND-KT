<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\FiscalYear;
use App\Http\Requests\Master\StoreFiscalYearRequest;
use App\Http\Requests\Master\UpdateFiscalYearRequest;
use App\Http\Resources\Master\FiscalYearResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FiscalYearController extends BaseMasterController
{
    protected string $modelClass = FiscalYear::class;
    protected string $resourceClass = FiscalYearResource::class;
    protected string $storeRequestClass = StoreFiscalYearRequest::class;
    protected string $updateRequestClass = UpdateFiscalYearRequest::class;
    protected array $searchableColumns = ['year', 'name'];
    protected array $defaultWith = [];

    public function toggleStatus($id): JsonResponse
    {
        $record = FiscalYear::findOrFail($id);

        // Fiscal Year has one global default. Activating a year automatically
        // deactivates the previous one; the currently active year cannot be
        // switched off without selecting another year first.
        if (! $record->is_active) {
            DB::transaction(function () use ($record) {
                FiscalYear::query()->whereKeyNot($record->id)->update(['is_active' => false]);
                $record->update(['is_active' => true]);
            });
        }

        return $this->successResponse(
            new FiscalYearResource($record->fresh()),
            $record->is_active ? 'Fiscal Year aktif berhasil diubah.' : 'Fiscal Year tetap aktif.'
        );
    }
}
