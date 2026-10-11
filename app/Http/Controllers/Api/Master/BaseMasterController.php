<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Services\Master\MasterExportService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        try {
            $isOptions = $request->boolean('options') || $request->query('paginate') === 'false';
            $query = $this->modelClass::query();

            // An explicitly selected fiscal year must isolate period-specific
            // master records instead of falling back to the global NULL rows.
            if ($request->filled('fiscal_year_id')) {
                $table = (new $this->modelClass)->getTable();
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'fiscal_year_id')) {
                    $query->where('fiscal_year_id', $request->integer('fiscal_year_id'));
                }
            }

            // Lightweight dropdown/options mode for Frontend Select components
            if ($isOptions) {
                try {
                    $table = (new $this->modelClass)->getTable();
                    $isFiscalYearOptions = is_a($this->modelClass, \App\Models\Master\FiscalYear::class, true);
                    if (! $isFiscalYearOptions && ! $request->has('is_active') && \Illuminate\Support\Facades\Schema::hasColumn($table, 'is_active')) {
                        $query->where('is_active', true);
                    }
                    // Dropdowns should show global masters plus the active
                    // fiscal-year version by default. A caller can request a
                    // different year explicitly with fiscal_year_id, while
                    // regular list pages remain unscoped for administration.
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'fiscal_year_id') && ! $request->filled('fiscal_year_id')) {
                        $activeFiscalYearId = \App\Models\Master\FiscalYear::query()
                            ->where('is_active', true)
                            ->orderByDesc('year')
                            ->value('id');
                        if ($activeFiscalYearId) {
                            $query->where(function ($scope) use ($activeFiscalYearId) {
                                $scope->whereNull('fiscal_year_id')->orWhere('fiscal_year_id', $activeFiscalYearId);
                            });
                        }
                    }
                } catch (\Throwable $ignored) {
                    // Safe fallback if schema check is not accessible
                }

                if (method_exists($this->modelClass, 'scopeApplyFilters')) {
                    try {
                        $query->applyFilters($request, $this->searchableColumns);
                    } catch (\Throwable $ignored) {
                        // Safe fallback
                    }
                }

                $options = $query->get();

                if (! empty($this->resourceClass) && class_exists($this->resourceClass)) {
                    return $this->successResponse($this->resourceClass::collection($options), 'Daftar opsi berhasil dimuat.');
                }

                return $this->successResponse($options, 'Daftar opsi berhasil dimuat.');
            }

            if (! empty($this->defaultWith)) {
                $query->with($this->defaultWith);
            }
            if (! empty($this->defaultWithCount)) {
                $query->withCount($this->defaultWithCount);
            }

            if (method_exists($this->modelClass, 'scopeApplyFilters')) {
                $query->applyFilters($request, $this->searchableColumns);
            }

            $perPage = (int) $request->query('per_page', 10);
            if ($perPage > 100 || $perPage < 1) {
                $perPage = 10;
            }

            $paginated = $query->paginate($perPage);

            $items = (! empty($this->resourceClass) && class_exists($this->resourceClass))
                ? $this->resourceClass::collection($paginated->items())
                : $paginated->items();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dimuat.',
                'data' => $items,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Master index error on ' . static::class . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

    public function emailExport(Request $request, MasterExportService $exportService)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:2000'],
            'cc' => ['nullable', 'string', 'max:2000'],
            'format' => ['required', 'in:csv,xlsx,pdf,google_sheets'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:10000'],
            'filename' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._ -]+$/'],
        ]);
        $parseRecipients = static function (?string $value): array {
            $recipients = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) $value))));
            foreach ($recipients as $recipient) {
                if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['email' => "Alamat email tidak valid: {$recipient}"]);
                }
            }
            return $recipients;
        };
        $to = $parseRecipients($validated['email']);
        $cc = $parseRecipients($validated['cc'] ?? '');
        $query = $this->modelClass::query()->with($this->defaultWith)->applyFilters($request, $this->searchableColumns);
        $data = $query->get();
        [$path, $filename, $mime] = $exportService->attachment(class_basename($this->modelClass), $data, $validated['format']);
        if (! empty($validated['filename'])) {
            $extension = $validated['format'] === 'xlsx' ? 'xlsx' : ($validated['format'] === 'google_sheets' ? 'csv' : $validated['format']);
            $baseFilename = preg_replace('/\.(csv|xlsx|pdf)$/i', '', $validated['filename']);
            $filename = rtrim($baseFilename, '. ').'.'.$extension;
        }

        try {
            \Illuminate\Support\Facades\Mail::raw($validated['message'] ?: 'Berikut lampiran export data '.class_basename($this->modelClass).'.', function ($message) use ($validated, $to, $cc, $path, $filename, $mime) {
                $message->to($to);
                if (! empty($cc)) {
                    $message->cc($cc);
                }
                $message->subject($validated['subject'] ?: 'Export Data '.class_basename($this->modelClass))
                    ->attach($path, ['as' => $filename, 'mime' => $mime]);
            });
        } finally {
            @unlink($path);
        }

        return $this->successResponse(null, 'File export berhasil dikirim ke email.');
    }

    public function template(Request $request, MasterExportService $exportService)
    {
        $model = new $this->modelClass;
        $columns = array_values(array_filter($model->getFillable(), fn ($column) => ! in_array($column, [
            'id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by',
        ], true)));
        if (in_array(strtolower((string) $request->query('format')), ['xlsx', 'excel'], true)) {
            return $exportService->template(class_basename($this->modelClass), $columns);
        }

        // CSV templates use the same readable labels as the Excel template.
        // The importer maps these labels back to the model field names.
        $headers = $exportService->templateHeaders($columns);
        $handle = fopen('php://temp', 'w+');
        fprintf($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.strtolower(class_basename($this->modelClass)).'-template.csv"',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240']]);
        $uploadedFile = $request->file('file');
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $rows = [];
        $handle = null;
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $sheetRows = \PhpOffice\PhpSpreadsheet\IOFactory::load($uploadedFile->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
            $headers = array_map(fn ($header) => trim((string) $header), array_shift($sheetRows) ?: []);
            $rows = $sheetRows;
        } else {
            $handle = fopen($uploadedFile->getRealPath(), 'r');
            $headers = array_map(fn ($header) => ltrim(trim((string) $header), "\xEF\xBB\xBF"), fgetcsv($handle) ?: []);
        }
        $model = new $this->modelClass;
        $fillableColumns = array_values(array_filter($model->getFillable(), fn ($column) => ! in_array($column, [
            'id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by',
        ], true)));
        $fillable = array_flip($fillableColumns);
        // Accept both the API field names and the readable headers used by
        // the Excel template (for example "Fiscal Year (ID)").
        $headerMap = [];
        foreach ($headers as $header) {
            $normalized = Str::snake(str_replace(['(', ')'], '', $header));
            $mapped = null;
            if (isset($fillable[$header])) {
                $mapped = $header;
            } elseif (isset($fillable[$normalized])) {
                $mapped = $normalized;
            } elseif (isset($fillable[$normalized.'_id'])) {
                $mapped = $normalized.'_id';
            } elseif ($normalized === 'active' && isset($fillable['is_active'])) {
                $mapped = 'is_active';
            }
            $headerMap[] = $mapped;
        }
        $identityColumns = array_values(array_filter([
            'code', 'slug', 'email', 'nik', 'nip', 'account_number', 'number', 'name',
        ], fn ($column) => isset($fillable[$column])));
        $seen = [];
        $created = 0;
        $skipped = 0;
        $errors = [];
        while (true) {
            $row = $rows ? array_shift($rows) : ($handle ? fgetcsv($handle) : false);
            if ($row === false || $row === null) break;
            $payload = [];
            foreach ($headerMap as $index => $header) {
                if ($header !== null) {
                    $value = $row[$index] ?? null;
                    $payload[$header] = is_string($value) ? trim($value) : $value;
                }
            }
            $payload = array_filter($payload, static fn ($value) => $value !== null && $value !== '');
            if (! $payload) continue;

            $identityColumn = null;
            $identityValue = null;
            foreach ($identityColumns as $column) {
                if (isset($payload[$column]) && $payload[$column] !== '') {
                    $identityColumn = $column;
                    $identityValue = (string) $payload[$column];
                    break;
                }
            }
            $fingerprint = $identityColumn
                ? $identityColumn.'|'.mb_strtolower($identityValue)
                : md5(json_encode($payload));
            if (isset($seen[$fingerprint]) || ($identityColumn && ($this->modelClass)::query()->where($identityColumn, $identityValue)->exists())) {
                $skipped++;
                $seen[$fingerprint] = true;
                continue;
            }

            try {
                ($this->modelClass)::create($payload);
                $seen[$fingerprint] = true;
                $created++;
            } catch (Throwable $exception) {
                $skipped++;
                if (count($errors) < 5) $errors[] = $exception->getMessage();
            }
        }
        if ($handle) fclose($handle);
        $message = "{$created} data berhasil diimport, {$skipped} data duplikat/tidak valid dilewati.";
        return response()->json(['success' => true, 'message' => $message, 'data' => [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]]);
    }
}
