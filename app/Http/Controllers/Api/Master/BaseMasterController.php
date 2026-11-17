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

    protected function defaultRelations(): array
    {
        return $this->defaultWith;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $isOptions = $request->boolean('options') || $request->query('paginate') === 'false';
            // Administration tables must not silently hide records when the
            // active fiscal year changes. Apply year filtering explicitly.
            $query = $this->modelClass::query()->withoutGlobalScope('fiscal_year_context');

            // AI alert rows are monitoring output only. They must never be
            // exposed as operational Budget Line master data or dropdowns.
            if ($this->modelClass === \App\Models\Master\BudgetLine::class) {
                $query->where('line_code', 'not like', 'AI-ALERT-%');
            }

            // An explicitly selected fiscal year must isolate period-specific
            // master records instead of falling back to the global NULL rows.
            if ($request->filled('fiscal_year_id')) {
                $table = (new $this->modelClass)->getTable();
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'fiscal_year_id')) {
                    if ($this->modelClass === \App\Models\Master\GrantAgreement::class) {
                        $fiscalYear = \App\Models\Master\FiscalYear::query()
                            ->withoutGlobalScope('fiscal_year_context')
                            ->find($request->integer('fiscal_year_id'));
                        $query->where(function ($scope) use ($request, $fiscalYear) {
                            $scope->where('fiscal_year_id', $request->integer('fiscal_year_id'));
                            if ($fiscalYear) {
                                $scope->orWhere(function ($legacyGrant) use ($fiscalYear) {
                                    $legacyGrant->whereNull('fiscal_year_id')
                                        ->whereDate('start_date', '<=', $fiscalYear->end_date)
                                        ->whereDate('end_date', '>=', $fiscalYear->start_date);
                                });
                            }
                        });
                    } elseif ($this->modelClass === \App\Models\Master\BudgetLine::class) {
                        $query->where(function ($scope) use ($request) {
                            $scope->where('fiscal_year_id', $request->integer('fiscal_year_id'))
                                ->orWhereNull('fiscal_year_id');
                        });
                    } else {
                        $query->where('fiscal_year_id', $request->integer('fiscal_year_id'));
                    }
                } elseif (in_array($this->modelClass, [
                    \App\Models\Master\HolidayCalendar::class,
                    \App\Models\Master\ExchangeRate::class,
                ], true) && \Illuminate\Support\Facades\Schema::hasColumn($table, 'date')) {
                    // These date-based masters do not store a fiscal_year_id;
                    // their fiscal year is derived from the effective date.
                    $year = \App\Models\Master\FiscalYear::query()->whereKey($request->integer('fiscal_year_id'))->first();
                    if ($year) $query->whereBetween('date', [$year->start_date, $year->end_date]);
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
                    // Soft-deleted masters must never be offered in relation
                    // dropdowns, even when a custom/global scope is removed.
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted_at')) {
                        $query->whereNull($table.'.deleted_at');
                    }
                    // Without an explicit year, options include all active
                    // master records so dropdowns do not become empty merely
                    // because the seed data belongs to another fiscal year.
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

            if (! empty($this->defaultRelations())) {
                $query->with($this->defaultRelations());
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
                'message' => 'Failed to load data: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = app($this->storeRequestClass)->validated();
        $record = $this->modelClass::create($validated);
        $record->loadMissing($this->defaultRelations());
        $record->loadCount($this->defaultWithCount);

        return $this->successResponse(
            new $this->resourceClass($record),
            'Data berhasil ditambahkan.',
            Response::HTTP_CREATED
        );
    }

    public function show($id): JsonResponse
    {
        $record = $this->modelClass::query()
            ->withoutGlobalScope('fiscal_year_context')
            ->with($this->defaultRelations())
            ->withCount($this->defaultWithCount)
            ->findOrFail($id);

        return $this->successResponse(
            new $this->resourceClass($record),
            'Detail data berhasil dimuat.'
        );
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = $this->modelClass::query()
            ->withoutGlobalScope('fiscal_year_context')
            ->findOrFail($id);
        $validated = app($this->updateRequestClass)->validated();
        $record->update($validated);
        $record->loadMissing($this->defaultRelations());
        $record->loadCount($this->defaultWithCount);

        return $this->successResponse(
            new $this->resourceClass($record),
            'Data berhasil diperbarui.'
        );
    }

    public function destroy($id): JsonResponse
    {
        // The list endpoint intentionally shows master records across fiscal
        // years. Deleting by an ID from that list must not be blocked by the
        // request's active fiscal-year global scope.
        $record = $this->modelClass::query()
            ->withoutGlobalScope('fiscal_year_context')
            ->findOrFail($id);

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
        $record = $this->modelClass::query()
            ->withoutGlobalScope('fiscal_year_context')
            ->findOrFail($id);
        $record->is_active = ! $record->is_active;
        $record->save();

        return $this->successResponse(
            new $this->resourceClass($record),
            'Data status updated successfully.'
        );
    }

    public function export(Request $request, MasterExportService $exportService)
    {
        $format = strtolower($request->query('format', 'csv'));
        // Export should use the same explicit filters as the screen. Do not
        // let the implicit fiscal-year global scope hide global bank/master
        // rows when the export request has no selected year.
        $query = $this->modelClass::query()->withoutGlobalScope('fiscal_year_context')->with($this->defaultWith)->applyFilters($request, $this->searchableColumns);
        $data = $query->get();

        // Keep COA exports round-trippable: the database accepts canonical
        // account types only, so never export legacy labels such as `income`
        // that would fail on a subsequent import.
        if ($this->modelClass === \App\Models\Master\ChartOfAccount::class) {
            $data->each(function ($account) {
                $type = strtolower(trim((string) $account->account_type));
                $aliases = [
                    'income' => str_starts_with((string) $account->code, '6') ? 'other_income' : 'revenue',
                    'revenues' => 'revenue', 'sales' => 'revenue',
                    'assets' => 'asset', 'bank' => 'asset', 'cash' => 'asset',
                    'expenses' => 'expense', 'cost' => 'expense', 'cogs' => 'expense',
                ];
                if (isset($aliases[$type])) $account->setAttribute('account_type', $aliases[$type]);
            });
        }

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
        $query = $this->modelClass::query()->withoutGlobalScope('fiscal_year_context')->with($this->defaultWith)->applyFilters($request, $this->searchableColumns);
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
        if ($this->modelClass === \App\Models\Master\BudgetLine::class) {
            $columns = array_map(static fn ($column) => $column === 'gl_account_id' ? 'coa_code' : $column, $columns);
        }
        $headerOverrides = [];
        if ($this->modelClass === \App\Models\Master\ChartOfAccount::class) {
            $columns = ['code', 'name', 'account_type', 'detail_type'];
            $headerOverrides = [
                'code' => 'Number',
                'name' => 'Account Name',
                'account_type' => 'Type',
                'detail_type' => 'Detail type',
            ];
        }
        if ($this->modelClass === \App\Models\Master\BankAccount::class) {
            $columns = ['bank_name', 'account_number', 'account_name', 'project_code', 'gl_account_id', 'currency_id', 'swift_code', 'opening_balance', 'opening_balance_date', 'organization_id', 'fiscal_year_id', 'is_active'];
            $headerOverrides = [
                'project_code' => 'Project Code / Relation No',
                'gl_account_id' => 'COA Code',
                'currency_id' => 'Currency Code',
                'organization_id' => 'Organization Code',
                'fiscal_year_id' => 'Fiscal Year',
                'is_active' => 'Active Status',
            ];
        }
        if ($this->modelClass === \App\Models\Master\Vendor::class) {
            // Vendor Code is automatically generated by the backend and not required in template
            $columns = ['name', 'contact_person', 'contact_person_title', 'additional_contacts', 'phone', 'address', 'email', 'npwp', 'type', 'bank_name', 'bank_account_number', 'bank_account_holder', 'tax_id', 'fiscal_year_id', 'is_active'];
            $headerOverrides = [
                'name' => 'Vendor Name',
                'contact_person' => 'Contact Person',
                'contact_person_title' => 'Contact Person Title',
                'additional_contacts' => 'Additional Sales / Contacts',
                'phone' => 'Phone',
                'address' => 'Address',
                'email' => 'Email',
                'npwp' => 'NPWP',
                'type' => 'Entity Type',
                'bank_name' => 'Bank Name',
                'bank_account_number' => 'Bank Account Number',
                'bank_account_holder' => 'Bank Account Holder',
                'tax_id' => 'Tax',
                'fiscal_year_id' => 'Effective Year',
                'is_active' => 'Active Status',
            ];
        }
        if (in_array(strtolower((string) $request->query('format')), ['xlsx', 'excel'], true)) {
            return $exportService->template(class_basename($this->modelClass), $columns, $headerOverrides);
        }

        // CSV templates use the same readable labels as the Excel template.
        // The importer maps these labels back to the model field names.
        $headers = $exportService->templateHeaders($columns, $headerOverrides);
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
        $headerKeywords = ['number', 'code', 'account_number', 'account_name', 'account name', 'name', 'type', 'type / category', 'type/category', 'account_type', 'bank_name', 'vendor_name'];
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $sheetRows = \PhpOffice\PhpSpreadsheet\IOFactory::load($uploadedFile->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
            $headers = [];
            while (! empty($sheetRows)) {
                $candidate = array_shift($sheetRows) ?: [];
                $clean = array_filter(array_map(fn ($h) => strtolower(trim((string) $h)), $candidate));
                if (array_intersect($clean, $headerKeywords)) {
                    $headers = array_map(fn ($h) => trim((string) $h), $candidate);
                    break;
                }
            }
            if (! $headers && ! empty($candidate)) {
                $headers = array_map(fn ($h) => trim((string) $h), $candidate);
            }
            $rows = $sheetRows;
        } else {
            $handle = fopen($uploadedFile->getRealPath(), 'r');
            $headers = [];
            while (! feof($handle)) {
                $candidate = fgetcsv($handle);
                if ($candidate === false) break;
                $clean = array_filter(array_map(fn ($h) => strtolower(ltrim(trim((string) $h), "\xEF\xBB\xBF")), $candidate));
                if (array_intersect($clean, $headerKeywords)) {
                    $headers = array_map(fn ($h) => ltrim(trim((string) $h), "\xEF\xBB\xBF"), $candidate);
                    break;
                }
            }
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
            } elseif ($this->modelClass === \App\Models\Master\ChartOfAccount::class && in_array($normalized, ['account_number', 'account_no', 'number', 'no', 'account_num'], true)) {
                $mapped = 'code';
            } elseif ($this->modelClass === \App\Models\Master\ChartOfAccount::class && in_array($normalized, ['account_name', 'account_name_type', 'name', 'account'], true)) {
                $mapped = 'name';
            } elseif ($this->modelClass === \App\Models\Master\ChartOfAccount::class && in_array($normalized, ['account_type', 'type', 'type_category', 'type_category_account'], true)) {
                $mapped = 'account_type';
            } elseif ($this->modelClass === \App\Models\Master\ChartOfAccount::class && in_array($normalized, ['detail_type', 'detail_type_name'], true)) {
                $mapped = 'detail_type';
            } elseif ($this->modelClass === \App\Models\Master\ChartOfAccount::class && $normalized === 'parent_account') {
                $mapped = 'parent_id';
            } elseif ($this->modelClass === \App\Models\Master\ChartOfAccount::class && $normalized === 'active_status') {
                $mapped = 'is_active';
            } elseif ($this->modelClass === \App\Models\Master\BankAccount::class && $normalized === 'currency_code') {
                $mapped = 'currency';
            } elseif ($this->modelClass === \App\Models\Master\BankAccount::class && $normalized === 'organization_code') {
                $mapped = 'organization_code';
            } elseif ($this->modelClass === \App\Models\Master\BankAccount::class && in_array($normalized, ['coa_code', 'co_a_code'], true)) {
                $mapped = 'coa_code';
            } elseif ($this->modelClass === \App\Models\Master\BankAccount::class && in_array($normalized, ['project_code', 'project', 'project_code_relation_no', 'relation_no'], true)) {
                $mapped = 'project_code';
            } elseif ($this->modelClass === \App\Models\Master\BankAccount::class && $normalized === 'fiscal_year') {
                $mapped = 'fiscal_year';
            } elseif ($this->modelClass === \App\Models\Master\BankAccount::class && $normalized === 'active_status') {
                $mapped = 'is_active';
            } elseif ($this->modelClass === \App\Models\Master\BudgetLine::class && in_array($normalized, ['coa_code', 'co_a_code'], true)) {
                $mapped = 'coa_code';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['vendor_name', 'nama_vendor', 'supplier_name', 'nama_supplier', 'vendor', 'supplier'], true)) {
                $mapped = 'name';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['vendor_code', 'kode_vendor'], true)) {
                $mapped = 'code';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['pic', 'pic_name', 'nama_pic', 'contact_name', 'kontak'], true)) {
                $mapped = 'contact_person';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['jabatan_pic', 'pic_title', 'title_pic', 'position_pic', 'jabatan'], true)) {
                $mapped = 'contact_person_title';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['alamat'], true)) {
                $mapped = 'address';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['telepon', 'no_telp', 'no_hp', 'no_telepon', 'nomor_telepon', 'telp'], true)) {
                $mapped = 'phone';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['surel'], true)) {
                $mapped = 'email';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['nomor_npwp', 'no_npwp'], true)) {
                $mapped = 'npwp';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['entity_type', 'tipe', 'jenis_vendor'], true)) {
                $mapped = 'type';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['vendor_category', 'kategori_vendor', 'category'], true)) {
                $mapped = 'vendor_category_id';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['nama_bank'], true)) {
                $mapped = 'bank_name';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['nomor_rekening', 'no_rekening', 'account_number'], true)) {
                $mapped = 'bank_account_number';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['atas_nama', 'nama_rekening', 'account_holder'], true)) {
                $mapped = 'bank_account_holder';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['pajak', 'tax'], true)) {
                $mapped = 'tax_id';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['tahun_buku', 'fiscal_year', 'effective_year'], true)) {
                $mapped = 'fiscal_year_id';
            } elseif ($this->modelClass === \App\Models\Master\Vendor::class && in_array($normalized, ['active_status', 'status'], true)) {
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
        $duplicates = 0;
        $merged = 0;
        $invalid = 0;
        $systemFailed = 0;
        $total = 0;
        $errors = [];
        $importedCoaIds = [];
        $importedBankAccounts = [];
        $activeFiscalYearId = null;
        if ($this->modelClass === \App\Models\Master\ChartOfAccount::class) {
            $activeFiscalYearId = $request->input('fiscal_year_id') ?: $request->header('X-Fiscal-Year-Id');
            $activeFiscalYearId = $activeFiscalYearId ?: \App\Models\Master\FiscalYear::query()
                ->where('is_active', true)
                ->orderByDesc('year')
                ->value('id');
        }
        while (true) {
            $row = $rows ? array_shift($rows) : ($handle ? fgetcsv($handle) : false);
            if ($row === false || $row === null) break;

            $hasContent = false;
            foreach ($row as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (! $hasContent) {
                continue;
            }

            $total++;
            $rowNumber = $total + 1;
            $payload = [];
            foreach ($headerMap as $index => $header) {
                if ($header !== null) {
                    $value = $row[$index] ?? null;
                    $payload[$header] = is_string($value) ? trim($value) : $value;
                }
            }
            $payload = array_filter($payload, static fn ($value) => $value !== null && $value !== '');
            if (! $payload) {
                $skipped++;
                $invalid++;
                $errors[] = ['row' => $rowNumber, 'message' => 'Baris kosong atau tidak memiliki kolom yang dikenali.'];
                continue;
            }

            if ($this->modelClass === \App\Models\Master\Vendor::class) {
                $vendorName = trim((string) ($payload['name'] ?? ''));
                if ($vendorName === '') {
                    $invalid++;
                    $errors[] = ['row' => $rowNumber, 'message' => 'Nama vendor wajib diisi.'];
                    continue;
                }

                $rawType = mb_strtolower(trim((string) ($payload['type'] ?? 'company')));
                $typeAliases = [
                    'company' => 'company', 'perusahaan' => 'company', 'hotel' => 'company', 'supplier' => 'company',
                    'vendor' => 'company', 'pt' => 'company', 'cv' => 'company', 'badan usaha' => 'company',
                    'organization' => 'company', 'organisation' => 'company',
                    'individual' => 'individual', 'individu' => 'individual', 'perorangan' => 'individual', 'personal' => 'individual',
                    'consultant' => 'consultant', 'konsultan' => 'consultant',
                ];
                $payload['type'] = $typeAliases[$rawType] ?? 'company';

                $cleanName = mb_strtolower($vendorName);
                $vendorNpwp = trim((string) ($payload['npwp'] ?? ''));
                $cleanNpwp = $vendorNpwp !== '' ? preg_replace('/[^0-9]/', '', $vendorNpwp) : null;

                // One company may have several sales/PIC contacts. Keep a
                // single vendor master and merge subsequent contact rows.
                $nameKey = 'vendor_name|' . $cleanName;
                $npwpKey = $cleanNpwp ? 'vendor_npwp|' . $cleanNpwp : null;
                $existingVendor = \App\Models\Master\Vendor::withoutGlobalScopes()
                    ->where(function ($query) use ($vendorName, $vendorNpwp) {
                        $query->where('name', $vendorName);
                        if ($vendorNpwp !== '') {
                            $query->orWhere('npwp', $vendorNpwp);
                        }
                    })
                    ->first();

                if ($existingVendor) {
                    $newContactName = trim((string) ($payload['contact_person'] ?? ''));
                    $contactLine = implode(' | ', array_filter([
                        $newContactName,
                        trim((string) ($payload['contact_person_title'] ?? '')),
                        trim((string) ($payload['phone'] ?? '')),
                        trim((string) ($payload['email'] ?? '')),
                    ], static fn ($value) => $value !== ''));
                    $samePrimaryContact = $newContactName !== ''
                        && mb_strtolower($newContactName) === mb_strtolower(trim((string) $existingVendor->contact_person));
                    $alreadyAdditional = $contactLine !== ''
                        && str_contains(mb_strtolower((string) $existingVendor->additional_contacts), mb_strtolower($contactLine));

                    if ($contactLine !== '' && ! $samePrimaryContact && ! $alreadyAdditional) {
                        $existingLines = trim((string) $existingVendor->additional_contacts);
                        $existingVendor->update([
                            'additional_contacts' => $existingLines !== '' ? $existingLines."\n".$contactLine : $contactLine,
                            'type' => $payload['type'],
                            'updated_by' => auth()->id(),
                        ]);
                        $merged++;
                        $seen[$nameKey] = true;
                        if ($npwpKey) $seen[$npwpKey] = true;
                        continue;
                    }

                    $duplicates++;
                    $seen[$nameKey] = true;
                    if ($npwpKey) $seen[$npwpKey] = true;
                    $errors[] = ['row' => $rowNumber, 'message' => "Duplikat vendor/kontak: '{$vendorName}' sudah terdaftar."];
                    continue;
                }

                // Do not use vendor code from Excel; auto-generate centrally via sequence service
                unset($payload['code']);

                if (! empty($payload['vendor_category_id']) && ! is_numeric($payload['vendor_category_id'])) {
                    $catName = (string) $payload['vendor_category_id'];
                    $catId = \App\Models\Master\VendorCategory::query()
                        ->where('name', $catName)
                        ->orWhere('code', $catName)
                        ->value('id');
                    $payload['vendor_category_id'] = $catId;
                }

                if (! empty($payload['tax_id']) && ! is_numeric($payload['tax_id'])) {
                    $taxVal = (string) $payload['tax_id'];
                    $taxId = \App\Models\Master\Tax::query()
                        ->where('code', $taxVal)
                        ->orWhere('name', $taxVal)
                        ->value('id');
                    $payload['tax_id'] = $taxId;
                }

                if (! empty($payload['fiscal_year_id']) && ! is_numeric($payload['fiscal_year_id'])) {
                    $fyVal = (int) $payload['fiscal_year_id'];
                    $fyId = \App\Models\Master\FiscalYear::query()
                        ->where(function ($query) use ($fyVal) {
                            $query->where('year', $fyVal)
                                ->orWhere('id', $fyVal);
                        })
                        ->value('id');
                    $payload['fiscal_year_id'] = $fyId;
                }

                if (array_key_exists('is_active', $payload)) {
                    $val = strtolower(trim((string) $payload['is_active']));
                    $payload['is_active'] = in_array($val, ['1', 'true', 'yes', 'y', 'aktif', 'active'], true) ? 1 : 0;
                }

                try {
                    // Generate code right before database insert to guarantee atomicity and avoid wasting codes
                    $payload['code'] = app(\App\Services\Master\VendorCodeGeneratorService::class)->generate();
                    \App\Models\Master\Vendor::create($payload);

                    $seen[$nameKey] = true;
                    if ($npwpKey) $seen[$npwpKey] = true;
                    $created++;
                } catch (\Throwable $exception) {
                    $systemFailed++;
                    $errors[] = ['row' => $rowNumber, 'message' => 'Gagal sistem: ' . $exception->getMessage()];
                }
                continue;
            }

            if ($this->modelClass === \App\Models\Master\ChartOfAccount::class) {
                $accountCode = trim((string) ($payload['code'] ?? ''));
                $accountName = trim((string) ($payload['name'] ?? ''));

                // Skip summary/footer lines such as "TOTAL" or export timestamps like "Friday, November..."
                if (strcasecmp($accountName, 'TOTAL') === 0 || strcasecmp($accountCode, 'TOTAL') === 0) {
                    continue;
                }
                if (str_contains(strtolower($accountName), 'gmt+') || str_contains(strtolower($accountCode), 'gmt+')) {
                    continue;
                }
                if ($accountCode === '' && $accountName === '') {
                    continue;
                }

                // If Number is empty (common for built-in accounts in QuickBooks, e.g. "Inventory Asset"),
                // generate a clean deterministic code based on name
                if ($accountCode === '') {
                    $cleanSnippet = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $accountName));
                    $accountCode = 'QB-' . substr($cleanSnippet ?: 'ACC', 0, 10);
                    $payload['code'] = $accountCode;
                }

                $rawAccountType = strtolower(trim((string) ($payload['account_type'] ?? '')));
                $cleanType = str_replace(['(', ')', '/', '\\', '-'], '_', $rawAccountType);
                $cleanType = preg_replace('/_+/', '_', trim($cleanType, '_'));

                $accountTypeAliases = [
                    'asset' => 'asset',
                    'assets' => 'asset',
                    'bank' => 'asset',
                    'cash' => 'asset',
                    'accounts_receivable' => 'asset',
                    'accounts_receivable_a_r' => 'asset',
                    'accounts_receivable_ar' => 'asset',
                    'ar' => 'asset',
                    'other_current_assets' => 'asset',
                    'other_current_asset' => 'asset',
                    'current_assets' => 'asset',
                    'fixed_assets' => 'asset',
                    'fixed_asset' => 'asset',
                    'other_assets' => 'asset',

                    'liability' => 'liability',
                    'liabilities' => 'liability',
                    'accounts_payable' => 'liability',
                    'accounts_payable_a_p' => 'liability',
                    'accounts_payable_ap' => 'liability',
                    'ap' => 'liability',
                    'other_current_liabilities' => 'liability',
                    'long_term_liabilities' => 'liability',
                    'other_liabilities' => 'liability',

                    'equity' => 'equity',
                    'capital' => 'equity',
                    'net_assets' => 'equity',
                    'opening_balance_equity' => 'equity',
                    'retained_earnings' => 'equity',

                    'revenue' => 'revenue',
                    'revenues' => 'revenue',
                    'income' => 'revenue',
                    'sales' => 'revenue',

                    'expense' => 'expense',
                    'expenses' => 'expense',
                    'cost' => 'expense',
                    'cost_of_sales' => 'expense',
                    'cost_of_goods_sold' => 'expense',
                    'cogs' => 'expense',

                    'other_income' => 'other_income',
                    'other_revenue' => 'other_income',
                    'other_revenues' => 'other_income',
                    'misc_income' => 'other_income',

                    'other_expense' => 'other_expense',
                    'other_expenses' => 'other_expense',
                ];

                if ($cleanType === 'income') {
                    $cleanType = str_starts_with($accountCode, '6') ? 'other_income' : 'revenue';
                }

                $payload['account_type'] = $accountTypeAliases[$cleanType] ?? ($accountTypeAliases[$rawAccountType] ?? $rawAccountType);


                if (empty($payload['detail_type'])) {
                    $payload['detail_type'] = $payload['account_type'] === 'asset' ? 'Current Asset' : 'General';
                }

                $required = ['code', 'name', 'account_type'];
                $missing = array_values(array_filter($required, fn ($field) => ! isset($payload[$field]) || trim((string) $payload[$field]) === ''));
                if ($missing) {
                    $skipped++;
                    $errors[] = ['row' => $rowNumber, 'message' => 'Kolom wajib kosong: '.implode(', ', $missing).'.'];
                    continue;
                }

                $allowedAccountTypes = ['asset', 'liability', 'equity', 'revenue', 'expense', 'other_income', 'other_expense'];
                if (! in_array($payload['account_type'], $allowedAccountTypes, true)) {
                    $skipped++;
                    $errors[] = ['row' => $rowNumber, 'message' => "Account Type '{$rawAccountType}' tidak dikenali. Gunakan: ".implode(', ', $allowedAccountTypes).'.'];
                    continue;
                }
            }

            if ($this->modelClass === \App\Models\Master\BankAccount::class) {
                $bankProjectCode = trim((string) ($payload['project_code']
                    ?? $payload['project_code_relation_no']
                    ?? $payload['relation_no']
                    ?? $payload['project']
                    ?? ''));
                // Depending on the spreadsheet header normalizer, COA Code
                // may arrive as `coa_code` or as the model field
                // `gl_account_id`. Treat both as an external COA code and
                // resolve it to the real chart_of_accounts.id below.
                $bankCoaCode = trim((string) ($payload['coa_code']
                    ?? $payload['co_a_code']
                    ?? $payload['gl_account_id']
                    ?? ''));
                $mappingAccountName = trim((string) ($payload['account_name'] ?? ''));
                $mappingStartDate = ! empty($payload['start_date']) ? $payload['start_date'] : null;
                $mappingEndDate = ! empty($payload['end_date']) ? $payload['end_date'] : null;
                $mappingOpeningBalance = isset($payload['opening_balance']) ? (float) $payload['opening_balance'] : 0;
                unset($payload['project_code'], $payload['start_date'], $payload['end_date']);

                // A row that declares Project + COA must be imported as a
                // complete mapping. Do not create a standalone bank account
                // and silently lose the requested relationship.
                $mappingProject = null;
                $mappingCoa = null;
                if ($bankProjectCode !== '' || $bankCoaCode !== '') {
                    if ($bankProjectCode === '' || $bankCoaCode === '') {
                        $errors[] = ['row' => $rowNumber, 'message' => 'Project Code / Relation No dan COA Code harus diisi bersama untuk mapping rekening.'];
                        $skipped++;
                        continue;
                    }
                    $mappingProject = \App\Models\Master\Project::withoutGlobalScope('fiscal_year_context')
                        ->where(function ($query) use ($bankProjectCode) {
                            $query->where('code', $bankProjectCode)
                                ->orWhere('relation_no', $bankProjectCode)
                                ->orWhere('name', $bankProjectCode);
                        })->first();
                    $mappingCoa = \App\Models\Master\ChartOfAccount::withoutGlobalScope('fiscal_year_context')
                        ->where('code', $bankCoaCode)->first();
                    if (! $mappingProject || ! $mappingCoa) {
                        $missing = [];
                        if (! $mappingProject) $missing[] = "Project '{$bankProjectCode}'";
                        if (! $mappingCoa) $missing[] = "COA '{$bankCoaCode}'";
                        $errors[] = ['row' => $rowNumber, 'message' => implode(' dan ', $missing).' tidak ditemukan untuk mapping rekening. Import Integrated dan COA terlebih dahulu.'];
                        $skipped++;
                        continue;
                    }
                }

                // Bank accounts require a currency at database level. If the
                // import file omits Currency (ID), use the configured base
                // currency (normally IDR), never a fabricated currency row.
                if (empty($payload['currency_id']) && ! empty($payload['currency'])) {
                    $currencyValue = trim((string) $payload['currency']);
                    $payload['currency_id'] = \App\Models\Master\Currency::query()
                        ->where('code', strtoupper($currencyValue))
                        ->value('id');
                }
                if (empty($payload['organization_id']) && ! empty($payload['organization_code'])) {
                    $organizationValue = trim((string) $payload['organization_code']);
                    $payload['organization_id'] = \App\Models\Master\Organization::query()
                        ->where('code', $organizationValue)
                        ->orWhere('name', $organizationValue)
                        ->value('id');
                }
                if ($bankCoaCode !== '') {
                    $payload['gl_account_id'] = \App\Models\Master\ChartOfAccount::withoutGlobalScope('fiscal_year_context')
                        ->where(function ($query) use ($bankCoaCode) {
                            $query->where('code', $bankCoaCode);
                            if (is_numeric($bankCoaCode)) {
                                $query->orWhere('id', (int) $bankCoaCode);
                            }
                        })
                        ->value('id');
                }
                if (empty($payload['fiscal_year_id']) && ! empty($payload['fiscal_year'])) {
                    $fiscalYearValue = (int) $payload['fiscal_year'];
                    $payload['fiscal_year_id'] = \App\Models\Master\FiscalYear::query()
                        ->where(function ($query) use ($fiscalYearValue) {
                            $query->where('year', $fiscalYearValue)
                                ->orWhere('id', $fiscalYearValue);
                        })
                        ->value('id');
                }
                // Templates commonly contain the calendar year directly in
                // `fiscal_year_id` (e.g. 2026), while the FK requires the
                // fiscal_years.id (e.g. 3). Resolve year values before insert.
                if (! empty($payload['fiscal_year_id'])) {
                    $fiscalYearValue = (int) $payload['fiscal_year_id'];
                    $resolvedFiscalYearId = \App\Models\Master\FiscalYear::query()
                        ->where(function ($query) use ($fiscalYearValue) {
                            $query->where('year', $fiscalYearValue)
                                ->orWhere('id', $fiscalYearValue);
                        })
                        ->value('id');
                    if ($resolvedFiscalYearId) {
                        $payload['fiscal_year_id'] = $resolvedFiscalYearId;
                    }
                }
                unset($payload['organization_code'], $payload['coa_code'], $payload['fiscal_year']);
                if (empty($payload['currency_id'])) {
                    $payload['currency_id'] = \App\Models\Master\Currency::query()
                        ->where('is_base_currency', true)
                        ->value('id')
                        ?? \App\Models\Master\Currency::query()->where('code', 'IDR')->value('id');
                }
                unset($payload['currency']);
                if (empty($payload['currency_id'])) {
                    $skipped++;
                    $errors[] = ['row' => $rowNumber, 'message' => 'Currency ID tidak ditemukan. Buat mata uang dasar terlebih dahulu.'];
                    continue;
                }

                $accNumber = trim((string) ($payload['account_number'] ?? ''));
                $existingBank = $importedBankAccounts[$accNumber]
                    ?? \App\Models\Master\BankAccount::withoutGlobalScope('fiscal_year_context')
                        ->where('account_number', $accNumber)
                        ->first();

                // Satu nomor rekening bank fisik dapat digunakan oleh beberapa project dengan COA berbeda.
                // Jika baris menyertakan project_code & coa_code, baris ini dipetakan ke relasi BankAccountProjectCoa.
                // Jika tanpa project_code (misal format QuickBooks yang memisahkan baris akun bank per project/COA),
                // alur dilanjutkan ke pembuatan record BankAccount tersendiri selama nama akun atau COA-nya berbeda.
                if ($existingBank && $bankProjectCode !== '' && $bankCoaCode !== '') {
                    $project = $mappingProject;
                    $coa = $mappingCoa;
                    if ($project && $coa) {
                        \App\Models\Master\BankAccountProjectCoa::updateOrCreate(
                            [
                                'bank_account_id' => $existingBank->id,
                                'project_id' => $project->id,
                            ],
                            [
                                'gl_account_id' => $coa->id,
                                'fiscal_year_id' => $payload['fiscal_year_id'] ?? $existingBank->fiscal_year_id,
                                'relation_no' => $payload['relation_no'] ?? ($project->relation_no ?: $project->grantAgreement?->relation_no),
                                'account_name' => $mappingAccountName ?: ($project->name ? "{$existingBank->bank_name} - {$project->name}" : null),
                                'start_date' => $mappingStartDate ?: $project->start_date,
                                'end_date' => $mappingEndDate ?: $project->end_date,
                                'opening_balance' => $mappingOpeningBalance,
                                'is_active' => true,
                            ]
                        );
                        $created++;
                        continue;
                    } else {
                        $missingEntity = ! $project ? "Project '{$bankProjectCode}'" : "COA '{$bankCoaCode}'";
                        $errors[] = ['row' => $rowNumber, 'message' => "{$missingEntity} tidak ditemukan untuk mapping rekening {$accNumber}."];
                        $skipped++;
                        continue;
                    }
                }
            }

            if ($this->modelClass === \App\Models\Master\ChartOfAccount::class) {
                // Fiscal year is required for usable scoped CoA imports. If the
                // template leaves it blank, use the current active fiscal year.
                if (empty($payload['fiscal_year_id']) && $activeFiscalYearId) {
                    $payload['fiscal_year_id'] = $activeFiscalYearId;
                } elseif (! empty($payload['fiscal_year_id'])) {
                    // Accept either the database ID or the human fiscal year
                    // (for example 2026) in spreadsheet imports.
                    $fiscalYearValue = (int) $payload['fiscal_year_id'];
                    $fiscalYearId = \App\Models\Master\FiscalYear::query()->whereKey($fiscalYearValue)->value('id')
                        ?? \App\Models\Master\FiscalYear::query()->where('year', $fiscalYearValue)->value('id');
                    if ($fiscalYearId) $payload['fiscal_year_id'] = $fiscalYearId;
                }

                // Parent IDs in a spreadsheet are often not database IDs. Resolve
                // them from the imported hierarchy (A:B:C -> A:B) instead of
                // rejecting every child row because of a foreign-key error.
                $name = trim((string) ($payload['name'] ?? ''));
                $parts = $name === '' ? [] : explode(':', $name);
                $parentName = count($parts) > 1 ? implode(':', array_slice($parts, 0, -1)) : null;
                if ($parentName) {
                    $parentId = $importedCoaIds[$parentName]
                        ?? \App\Models\Master\ChartOfAccount::query()->where('name', $parentName)->value('id');
                    if ($parentId) $payload['parent_id'] = $parentId;
                } elseif (! empty($payload['parent_id']) && ! \App\Models\Master\ChartOfAccount::query()->whereKey($payload['parent_id'])->exists()) {
                    unset($payload['parent_id']);
                }
                $payload['level'] = ! empty($payload['parent_id']) ? 2 : 1;
                $payload['is_header'] = empty($payload['parent_id']);
                $payload['normal_balance'] = in_array($payload['account_type'] ?? null, ['asset', 'expense', 'other_expense'], true) ? 'debit' : 'credit';

                foreach (['is_active', 'is_header'] as $booleanField) {
                    if (array_key_exists($booleanField, $payload)) {
                        $value = strtolower(trim((string) $payload[$booleanField]));
                        $payload[$booleanField] = in_array($value, ['1', 'true', 'yes', 'y', 'aktif', 'active'], true) ? 1 : 0;
                    }
                }
                if (isset($payload['parent_id']) && ! is_numeric($payload['parent_id'])) {
                    $parentId = $importedCoaIds[(string) $payload['parent_id']]
                        ?? \App\Models\Master\ChartOfAccount::query()
                            ->where(function ($query) use ($payload) {
                                $query->where('code', (string) $payload['parent_id'])
                                    ->orWhere('name', (string) $payload['parent_id']);
                            })->value('id');
                    if ($parentId) $payload['parent_id'] = $parentId;
                    else unset($payload['parent_id']);
                }
                // Legacy COA spreadsheets often contain only the account
                // number. Infer the common hierarchy: 10000 -> 10100 ->
                // 10110 (and the equivalent four-digit pattern).
                if (empty($payload['parent_id']) && preg_match('/^\d{4,}$/', (string) ($payload['code'] ?? ''))) {
                    $numericCode = (int) $payload['code'];
                    $codeLength = strlen((string) $payload['code']);
                    $rootScale = 10 ** ($codeLength - 1);
                    $parentCode = null;
                    if ($numericCode % $rootScale !== 0) {
                        $parentCode = $numericCode % 100 === 0
                            ? (string) (intdiv($numericCode, $rootScale) * $rootScale)
                            : (string) (intdiv($numericCode, 100) * 100);
                    }
                    if ($parentCode && $parentCode !== (string) $payload['code']) {
                        $payload['parent_id'] = \App\Models\Master\ChartOfAccount::query()
                            ->where('code', $parentCode)
                            ->value('id');
                    }
                }
                if (! empty($payload['parent_id'])) {
                    $parentAccount = \App\Models\Master\ChartOfAccount::query()->find($payload['parent_id']);
                    $payload['level'] = min(3, ((int) ($parentAccount?->level ?: 1)) + 1);
                    $payload['is_header'] = false;
                    if ($parentAccount && ! $parentAccount->is_header) {
                        $parentAccount->forceFill(['is_header' => true])->save();
                    }
                } else {
                    $payload['level'] = 1;
                    $payload['is_header'] = true;
                }
            }

            if ($this->modelClass === \App\Models\Master\BudgetLine::class) {
                $project = ! empty($payload['project_id'])
                    ? \App\Models\Master\Project::query()->with('grantAgreement:id,relation_no')->find($payload['project_id'])
                    : null;
                $grant = ! empty($payload['grant_agreement_id'])
                    ? \App\Models\Master\GrantAgreement::query()->find($payload['grant_agreement_id'])
                    : $project?->grantAgreement;

                if ($project && $grant && $project->grant_agreement_id && (int) $project->grant_agreement_id !== (int) $grant->id) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Grant Agreement tidak sesuai dengan Project.'];
                    $skipped++;
                    continue;
                }

                $expectedRelationNo = $project?->relation_no ?: $project?->grantAgreement?->relation_no ?: $grant?->relation_no;
                $importedRelationNo = trim((string) ($payload['relation_no'] ?? ''));
                if ($importedRelationNo !== '' && $expectedRelationNo && strcasecmp($expectedRelationNo, $importedRelationNo) !== 0) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Nomor Relasi tidak sesuai dengan Project atau Grant Agreement.'];
                    $skipped++;
                    continue;
                }
                if ($expectedRelationNo) {
                    $payload['relation_no'] = $expectedRelationNo;
                }

                $coaCode = trim((string) ($payload['coa_code'] ?? $payload['co_a_code'] ?? ''));
                if ($coaCode === '') {
                    $errors[] = ['row' => $created + $skipped + 2, 'message' => 'CoA Code is required for every Budget Line.'];
                    $skipped++;
                    continue;
                }
                $coa = \App\Models\Master\ChartOfAccount::query()
                    ->where('code', $coaCode)
                    ->where('is_active', true)
                    ->where('is_header', false)
                    ->where('account_type', 'expense')
                    ->first();
                if (! $coa) {
                    $errors[] = ['row' => $created + $skipped + 2, 'message' => "CoA Code {$coaCode} does not exist or is not an active expense account."];
                    $skipped++;
                    continue;
                }
                $payload['gl_account_id'] = $coa->id;
                unset($payload['coa_code'], $payload['co_a_code']);
            }

            if ($this->modelClass === \App\Models\Master\BankAccount::class) {
                $accNumber = trim((string) ($payload['account_number'] ?? ''));
                $accName = trim((string) ($payload['account_name'] ?? ''));
                $bankGlId = $payload['gl_account_id'] ?? null;
                $fingerprint = 'bank_account|'.mb_strtolower($accNumber).'|'.mb_strtolower($accName).($bankGlId ? '|'.$bankGlId : '');

                // For BankAccount, allow the same account_number if account_name or COA is different (QuickBooks compatibility).
                // Only treat as duplicate if both account_number AND account_name (or account_number AND same COA) already exist.
                $isExactDuplicate = \App\Models\Master\BankAccount::withoutGlobalScope('fiscal_year_context')
                    ->where('account_number', $accNumber)
                    ->where(function ($q) use ($accName, $bankGlId) {
                        $q->where('account_name', $accName);
                        if ($bankGlId) {
                            $q->orWhere('gl_account_id', $bankGlId);
                        }
                    })->exists();

                if (isset($seen[$fingerprint]) || $isExactDuplicate) {
                    // Re-imports must repair an existing incomplete bank row.
                    // Older imports could create the account before COA/project
                    // resolution, leaving gl_account_id or its mapping empty.
                    $existingMapping = $mappingProject && $mappingCoa
                        ? $existingBank?->projectCoaMappings()->where('project_id', $mappingProject->id)->first()
                        : null;
                    $mappingNeedsRepair = $mappingProject && $mappingCoa
                        ? ! $existingMapping || (int) $existingMapping->gl_account_id !== (int) $mappingCoa->id
                        : false;
                    $bankCoaNeedsRepair = $bankGlId && (int) ($existingBank?->gl_account_id ?? 0) !== (int) $bankGlId;
                    if ($existingBank && ($bankCoaNeedsRepair || $mappingNeedsRepair)) {
                        $existingBank->update($payload);
                        if ($mappingProject && $mappingCoa) {
                            \App\Models\Master\BankAccountProjectCoa::updateOrCreate(
                                ['bank_account_id' => $existingBank->id, 'project_id' => $mappingProject->id],
                                [
                                    'gl_account_id' => $mappingCoa->id,
                                    'fiscal_year_id' => $payload['fiscal_year_id'] ?? $existingBank->fiscal_year_id,
                                    'relation_no' => $payload['relation_no'] ?? ($mappingProject->relation_no ?: $mappingProject->grantAgreement?->relation_no),
                                    'account_name' => $mappingAccountName ?: ($mappingProject->name ? "{$existingBank->bank_name} - {$mappingProject->name}" : null),
                                    'start_date' => $mappingStartDate ?: $mappingProject->start_date,
                                    'end_date' => $mappingEndDate ?: $mappingProject->end_date,
                                    'opening_balance' => $mappingOpeningBalance,
                                    'is_active' => true,
                                ]
                            );
                        }
                        $seen[$fingerprint] = true;
                        $created++;
                        continue;
                    }
                    $skipped++;
                    $seen[$fingerprint] = true;
                    $errors[] = ['row' => $rowNumber, 'message' => "Duplikat rekening: {$accNumber} - {$accName} sudah terdaftar."];
                    continue;
                }
            } else {
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
            $existingIdentity = false;
            if ($identityColumn) {
                $identityQuery = ($this->modelClass)::query()->where($identityColumn, $identityValue);
                if ($this->modelClass === \App\Models\Master\ChartOfAccount::class && ! empty($payload['fiscal_year_id'])) {
                    $identityQuery->where('fiscal_year_id', $payload['fiscal_year_id']);
                }
                $existingIdentity = $identityQuery->exists();
            }
            if (isset($seen[$fingerprint]) || $existingIdentity) {
                    $skipped++;
                    $seen[$fingerprint] = true;
                    $errors[] = ['row' => $rowNumber, 'message' => $identityColumn ? "Duplikat {$identityColumn}: {$identityValue}." : 'Data duplikat.'];
                    continue;
                }
            }

            try {
                $createdRecord = ($this->modelClass)::create($payload);
                if ($this->modelClass === \App\Models\Master\ChartOfAccount::class && ! empty($payload['name'])) {
                    $importedCoaIds[trim((string) $payload['name'])] = (int) ($this->modelClass)::query()->where('code', $payload['code'])->value('id');
                }
                if ($this->modelClass === \App\Models\Master\BankAccount::class) {
                    $importedBankAccounts[$createdRecord->account_number] = $createdRecord;
                    if ($mappingProject && $mappingCoa) {
                        $project = $mappingProject;
                        $coa = $mappingCoa;
                        if ($project && $coa) {
                            \App\Models\Master\BankAccountProjectCoa::updateOrCreate(
                                [
                                    'bank_account_id' => $createdRecord->id,
                                    'project_id' => $project->id,
                                ],
                                [
                                    'gl_account_id' => $coa->id,
                                    'fiscal_year_id' => $createdRecord->fiscal_year_id,
                                    'relation_no' => $payload['relation_no'] ?? ($project->relation_no ?: $project->grantAgreement?->relation_no),
                                    'account_name' => $mappingAccountName ?: ($project->name ? "{$createdRecord->bank_name} - {$project->name}" : null),
                                    'start_date' => $mappingStartDate ?: $project->start_date,
                                    'end_date' => $mappingEndDate ?: $project->end_date,
                                    'opening_balance' => $mappingOpeningBalance,
                                    'is_active' => true,
                                ]
                            );
                        }
                    }
                }
                $seen[$fingerprint] = true;
                $created++;
            } catch (Throwable $exception) {
                $skipped++;
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
        }
        if ($handle) fclose($handle);

        $skipped = $duplicates + $invalid + $systemFailed + ($skipped > ($duplicates + $invalid + $systemFailed) ? ($skipped - ($duplicates + $invalid + $systemFailed)) : 0);

        if ($this->modelClass === \App\Models\Master\Vendor::class) {
            $message = "Import Vendor Selesai\n\nTotal Data      : {$total}\nVendor Baru     : {$created}\nKontak Digabung : {$merged}\nDuplikat        : {$duplicates}\nTidak Valid     : {$invalid}\nGagal Sistem    : {$systemFailed}";
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'total' => $total,
                    'created' => $created,
                    'merged_contacts' => $merged,
                    'duplicates' => $duplicates,
                    'invalid' => $invalid,
                    'system_failed' => $systemFailed,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ],
            ]);
        }

        $message = "{$created} data berhasil diimport, {$skipped} data duplikat/tidak valid dilewati.";
        return response()->json(['success' => true, 'message' => $message, 'data' => [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]]);
    }
}
