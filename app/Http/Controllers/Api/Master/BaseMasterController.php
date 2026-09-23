<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Services\Master\MasterExportService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseMasterController extends Controller
{
    use ApiResponseTrait;

    protected string $modelClass;
    protected string $resourceClass;
    protected string $storeRequestClass;
    protected string $updateRequestClass;
    protected array $searchableColumns = ['code', 'name'];
    protected array $defaultWith = [];
    protected array $defaultWithCount = [];

    public function index(Request $request): JsonResponse
    {
        $query = $this->modelClass::query()->with($this->defaultWith)->withCount($this->defaultWithCount);

        // Lightweight dropdown/options mode for Frontend Select components
        if ($request->boolean('options') || $request->query('paginate') === 'false') {
            $options = $query->applyFilters($request, $this->searchableColumns)->get();
            return $this->successResponse($this->resourceClass::collection($options), 'Daftar opsi berhasil dimuat.');
        }

        $perPage = (int) $request->query('per_page', 10);
        if ($perPage > 100 || $perPage < 1) {
            $perPage = 10;
        }

        $paginated = $query->applyFilters($request, $this->searchableColumns)->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dimuat.',
            'data' => $this->resourceClass::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = app($this->storeRequestClass)->validated();
        $record = $this->modelClass::create($validated);
        $record->loadMissing($this->defaultWith);
        $record->loadCount($this->defaultWithCount);

        return $this->successResponse(
            new $this->resourceClass($record),
            'Data berhasil ditambahkan.',
            Response::HTTP_CREATED
        );
    }

    public function show($id): JsonResponse
    {
        $record = $this->modelClass::with($this->defaultWith)->withCount($this->defaultWithCount)->findOrFail($id);

        return $this->successResponse(
            new $this->resourceClass($record),
            'Detail data berhasil dimuat.'
        );
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = $this->modelClass::findOrFail($id);
        $validated = app($this->updateRequestClass)->validated();
        $record->update($validated);
        $record->loadMissing($this->defaultWith);
        $record->loadCount($this->defaultWithCount);

        return $this->successResponse(
            new $this->resourceClass($record),
            'Data berhasil diperbarui.'
        );
    }

    public function destroy($id): JsonResponse
    {
        $record = $this->modelClass::findOrFail($id);

        // Check if model defines related records check or has common relations
        $relatedCount = 0;
        if (method_exists($record, 'getRelatedRecordsCountAttribute')) {
            $relatedCount = $record->related_records_count;
        }

        if ($relatedCount > 0 && !request()->boolean('force')) {
            $name = $record->name ?? $record->title ?? $record->code ?? "#{$record->id}";
            $code = $record->code ?? $record->year ?? '';
            $title = $code ? "“{$code} - {$name}”" : "“{$name}”";

            return response()->json([
                'success' => false,
                'cannot_delete' => true,
                'related_count' => $relatedCount,
                'message' => "{$title} cannot be deleted because it is still used in {$relatedCount} records.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $record->delete();

        return $this->successResponse(
            null,
            'Data berhasil dihapus.'
        );
    }

    public function toggleStatus($id): JsonResponse
    {
        $record = $this->modelClass::findOrFail($id);
        $record->is_active = ! $record->is_active;
        $record->save();

        return $this->successResponse(
            new $this->resourceClass($record),
            'Status data berhasil diubah.'
        );
    }

    public function export(Request $request, MasterExportService $exportService)
    {
        $format = strtolower($request->query('format', 'csv'));
        $query = $this->modelClass::query()->with($this->defaultWith)->applyFilters($request, $this->searchableColumns);
        $data = $query->get();

        return $exportService->export(class_basename($this->modelClass), $data, $format);
    }

    public function template()
    {
        $model = new $this->modelClass;
        $columns = array_values(array_filter($model->getFillable(), fn ($column) => ! in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'])));
        return response(implode(',', $columns)."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.strtolower(class_basename($this->modelClass)).'-template.csv"',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $headers = array_map(fn ($header) => trim((string) $header), fgetcsv($handle) ?: []);
        $fillable = array_flip((new $this->modelClass)->getFillable());
        $created = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $payload = [];
            foreach ($headers as $index => $header) if ($header !== '' && isset($fillable[$header])) $payload[$header] = $row[$index] ?? null;
            if ($payload) { ($this->modelClass)::create($payload); $created++; }
        }
        fclose($handle);
        return response()->json(['success' => true, 'message' => "{$created} data berhasil diimport.", 'data' => ['created' => $created]]);
    }
}
