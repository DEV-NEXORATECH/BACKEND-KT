<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Vendor;
use App\Models\Master\VendorCategory;
use App\Http\Requests\Master\StoreVendorRequest;
use App\Http\Requests\Master\UpdateVendorRequest;
use App\Http\Resources\Master\VendorResource;
use App\Services\Master\VendorCodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VendorController extends BaseMasterController
{
    protected string $modelClass = Vendor::class;
    protected string $resourceClass = VendorResource::class;
    protected string $storeRequestClass = StoreVendorRequest::class;
    protected string $updateRequestClass = UpdateVendorRequest::class;
    protected array $searchableColumns = ['code', 'name', 'npwp', 'contact_person', 'email'];
    protected array $defaultWith = ['tax', 'category', 'fiscalYear'];

    private function resolveTypedCategory(array &$validated): void
    {
        $name = trim((string) ($validated['vendor_category_name'] ?? ''));
        unset($validated['vendor_category_name']);
        if ($name === '') {
            return;
        }

        $category = VendorCategory::withTrashed()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if (! $category) {
            $baseCode = 'VNC-'.Str::upper(Str::slug($name, '-'));
            $baseCode = substr($baseCode ?: 'VNC-CATEGORY', 0, 30);
            $code = $baseCode;
            $suffix = 2;
            while (VendorCategory::withTrashed()->where('code', $code)->exists()) {
                $tail = '-'.$suffix++;
                $code = substr($baseCode, 0, 30 - strlen($tail)).$tail;
            }
            $category = VendorCategory::create([
                'code' => $code,
                'name' => $name,
                'description' => 'Dibuat otomatis dari data vendor.',
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);
        } elseif ($category->trashed()) {
            $category->restore();
            $category->update(['is_active' => true, 'updated_by' => auth()->id()]);
        }

        $validated['vendor_category_id'] = $category->id;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = app($this->storeRequestClass)->validated();
        $this->resolveTypedCategory($validated);

        // Vendor code is always generated server-side; client cannot specify it
        $validated['code'] = app(VendorCodeGeneratorService::class)->generate();

        if (empty($validated['type'])) {
            $validated['type'] = 'company';
        }

        $record = $this->modelClass::create($validated);
        $record->loadMissing($this->defaultRelations());
        $record->loadCount($this->defaultWithCount);

        return $this->successResponse(
            new $this->resourceClass($record),
            'Vendor berhasil ditambahkan dengan kode: ' . $record->code,
            Response::HTTP_CREATED
        );
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = $this->modelClass::query()
            ->withoutGlobalScope('fiscal_year_context')
            ->findOrFail($id);

        $validated = app($this->updateRequestClass)->validated();
        $this->resolveTypedCategory($validated);

        // Vendor code is immutable / read-only after creation
        unset($validated['code']);

        $record->update($validated);
        $record->loadMissing($this->defaultRelations());
        $record->loadCount($this->defaultWithCount);

        return $this->successResponse(
            new $this->resourceClass($record),
            'Data vendor berhasil diperbarui.'
        );
    }

    public function nextCode(VendorCodeGeneratorService $codeGenerator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => $codeGenerator->previewNext(),
        ]);
    }
}
