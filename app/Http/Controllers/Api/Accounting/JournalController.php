<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Master\BankAccount;
use App\Models\Master\BudgetLine;
use App\Models\Master\Project;
use App\Models\Procurement\SupplierInvoice;
use App\Models\Finance\CustomerInvoice;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class JournalController extends Controller
{
    public function postToAp(Request $request, Journal $journal): JsonResponse
    {
        if ($journal->status === 'posted') {
            throw ValidationException::withMessages(['journal' => 'Journal yang sudah posted tidak dapat dimasukkan ulang ke AP.']);
        }
        $total = (float) $journal->lines()->sum('debit');
        $line = $journal->lines()->where('debit', '>', 0)->first();
        if ($total <= 0 || ! $line) throw ValidationException::withMessages(['journal' => 'Journal harus memiliki nilai debit untuk dibuatkan AP.']);
        $invoice = SupplierInvoice::firstOrCreate(
            ['invoice_number' => $journal->journal_number],
            ['invoice_date' => $journal->journal_date, 'total_amount' => $total, 'paid_amount' => 0, 'status' => 'draft', 'match_status' => 'matched', 'coa_id' => $line->account_id, 'source_type' => 'journal', 'created_by' => $request->user()->id]
        );
        $journal->update(['source_type' => 'ap', 'source_id' => $invoice->id]);
        return response()->json(['success' => true, 'message' => 'Journal berhasil dimasukkan ke AP.', 'data' => $invoice], Response::HTTP_CREATED);
    }

    public function postToAr(Request $request, Journal $journal): JsonResponse
    {
        if ($journal->status === 'posted') {
            throw ValidationException::withMessages(['journal' => 'Journal yang sudah posted tidak dapat dimasukkan ulang ke AR.']);
        }
        $total = (float) $journal->lines()->sum('credit');
        $line = $journal->lines()->where('credit', '>', 0)->first();
        if ($total <= 0 || ! $line) throw ValidationException::withMessages(['journal' => 'Journal harus memiliki nilai credit untuk dibuatkan AR.']);
        $invoice = CustomerInvoice::firstOrCreate(
            ['invoice_number' => $journal->journal_number],
            ['invoice_date' => $journal->journal_date, 'description' => $journal->description, 'total_amount' => $total, 'received_amount' => 0, 'status' => 'draft', 'created_by' => $request->user()->id]
        );
        if ($invoice->lines()->count() === 0) {
            $invoice->lines()->create(['revenue_account_id' => $line->account_id, 'description' => $journal->description ?: 'AR invoice', 'quantity' => 1, 'unit_price' => $total, 'total_amount' => $total, 'line_order' => 1]);
        }
        $journal->update(['source_type' => 'ar', 'source_id' => $invoice->id]);
        return response()->json(['success' => true, 'message' => 'Journal berhasil dimasukkan ke AR.', 'data' => $invoice->fresh()], Response::HTTP_CREATED);
    }
    private array $with = [
        'currency:id,code,name',
        'fiscalYear:id,year,name,start_date,end_date',
        'lines.account:id,code,name',
        'lines.donor:id,code,name',
        'lines.grantAgreement:id,donor_id,grant_no,relation_no,agreement_name',
        'lines.partner:id,code,name',
        'lines.program:id,code,name',
        'lines.project:id,code,relation_no,name,grant_agreement_id,program_id',
        'lines.project.grantAgreement:id,donor_id,grant_no,relation_no,agreement_name',
        'lines.project.grantAgreement.donor:id,code,name',
        'lines.budgetLine:id,project_id,grant_agreement_id,relation_no,line_code,description',
        'lines.department:id,code,name',
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'relation_no' => ['nullable', 'string', 'max:100'],
            'grant_agreement_id' => ['nullable', 'integer', 'exists:grant_agreements,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'budget_line_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);
        $fetchAll = $request->boolean('all') || $request->query('per_page') === 'all';
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage < 1 || $perPage > 100 ? 10 : $perPage;

        $query = Journal::withoutGlobalScope('fiscal_year_context')
            ->with(['currency:id,code,name', 'lines'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('fiscal_year_id'), fn ($query) => $query->where('fiscal_year_id', $request->integer('fiscal_year_id')))
            ->when($request->filled('project_id'), fn ($query) => $query->whereHas('lines', fn ($line) => $line->where('project_id', $request->integer('project_id'))))
            ->when($request->filled('grant_agreement_id'), fn ($query) => $query->whereHas('lines', fn ($line) => $line->where('grant_agreement_id', $request->integer('grant_agreement_id'))))
            ->when($request->filled('budget_line_id'), fn ($query) => $query->whereHas('lines', fn ($line) => $line->where('budget_line_id', $request->integer('budget_line_id'))))
            ->when($request->filled('bank_account_id'), function ($query) use ($request) {
                $bankAccountId = $request->integer('bank_account_id');
                $glAccountId = BankAccount::withoutGlobalScope('fiscal_year_context')->whereKey($bankAccountId)->value('gl_account_id');
                $query->where(function ($scope) use ($bankAccountId, $glAccountId) {
                    $scope->where(function ($ob) use ($bankAccountId) {
                        $ob->where('source_type', 'bank_account_opening_balance')->where('source_id', $bankAccountId);
                    });
                    if ($glAccountId) {
                        $scope->orWhereHas('lines', fn ($line) => $line->where('account_id', $glAccountId));
                    }
                });
            })
            ->when($request->filled('relation_no'), function ($query) use ($request) {
                $relationNo = trim((string) $request->query('relation_no'));
                $query->whereHas('lines', function ($line) use ($relationNo) {
                    $line->where(function ($scope) use ($relationNo) {
                        $scope->where('relation_no', $relationNo)
                            ->orWhereHas('project', fn ($project) => $project->where('relation_no', $relationNo))
                            ->orWhereHas('grantAgreement', fn ($grant) => $grant->where('relation_no', $relationNo))
                            ->orWhereHas('budgetLine', fn ($budgetLine) => $budgetLine->where('relation_no', $relationNo));
                    });
                });
            })
            ->when($request->filled('donor_id'), fn ($query) => $query->whereHas('lines', fn ($line) => $line->where('donor_id', $request->integer('donor_id'))))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('journal_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        app(\App\Services\Rbac\DataScopeService::class)->applyScope(
            $query,
            $request->user(),
            'created_by',
            null,
            null,
            ['accounting.journal.view', 'accounting.journal.review', 'accounting.journal.post']
        );

        $ordered = $query->latest('journal_date')->latest('id');

        if ($fetchAll) {
            $collection = $ordered->get();
            $total = $collection->count();

            return response()->json([
                'success' => true,
                'message' => 'Daftar journal berhasil dimuat.',
                'data' => $collection->map(fn (Journal $journal) => $this->formatJournal($journal)),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $total,
                    'total' => $total,
                    'from' => $total > 0 ? 1 : null,
                    'to' => $total > 0 ? $total : null,
                ],
            ]);
        }

        $journals = $ordered->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar journal berhasil dimuat.',
            'data' => $journals->getCollection()->map(fn (Journal $journal) => $this->formatJournal($journal)),
            'meta' => [
                'current_page' => $journals->currentPage(),
                'last_page' => $journals->lastPage(),
                'per_page' => $journals->perPage(),
                'total' => $journals->total(),
                'from' => $journals->firstItem(),
                'to' => $journals->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $this->guardOpenPeriod($payload['journal_date']);

        $journal = DB::transaction(function () use ($payload) {
            $lines = $payload['lines'];
            unset($payload['lines']);

            $journal = Journal::withoutGlobalScope('fiscal_year_context')->create([
                ...$payload,
                'journal_number' => $payload['journal_number'] ?? $this->nextJournalNumber(),
                'status' => 'draft',
            ]);

            $this->syncLines($journal, $lines);

            return $journal->load($this->with);
        });
        $this->storeAttachment($request, $journal);

        return response()->json([
            'success' => true,
            'message' => 'Journal berhasil dibuat.',
            'data' => $this->formatJournal($journal),
        ], Response::HTTP_CREATED);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'grant_agreement_id' => ['nullable', 'integer', 'exists:grant_agreements,id'],
            'relation_no' => ['nullable', 'string', 'max:100'],
        ]);

        $defaultProjectId = $request->filled('project_id') ? $request->integer('project_id') : null;
        $defaultGrantId = $request->filled('grant_agreement_id') ? $request->integer('grant_agreement_id') : null;
        $defaultRelationNo = trim((string) $request->input('relation_no', '')) ?: null;

        if (! $defaultProjectId && $defaultRelationNo) {
            $defaultProjectId = DB::table('projects')->where('relation_no', $defaultRelationNo)->value('id');
            $defaultProjectId = $defaultProjectId ? (int) $defaultProjectId : null;
        }
        if (! $defaultGrantId && $defaultProjectId) {
            $defaultGrantId = DB::table('projects')->where('id', $defaultProjectId)->value('grant_agreement_id');
            $defaultGrantId = $defaultGrantId ? (int) $defaultGrantId : null;
        }
        if (! $defaultGrantId && $defaultRelationNo) {
            $defaultGrantId = DB::table('grant_agreements')->where('relation_no', $defaultRelationNo)->value('id');
            $defaultGrantId = $defaultGrantId ? (int) $defaultGrantId : null;
        }

        $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), array_shift($rows) ?: []);
        $index = [];
        foreach ($headers as $column => $header) $index[preg_replace('/[^a-z0-9]+/', '_', $header)] = $column;
        $value = static function (array $row, array $index, array $keys): mixed {
            foreach ($keys as $key) if (isset($index[$key])) return $row[$index[$key]] ?? null;
            return null;
        };
        $grouped = [];
        foreach ($rows as $rowNumber => $row) {
            if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) continue;
            $number = trim((string) ($value($row, $index, ['journal_number', 'journal_no', 'number']) ?: ''));
            if ($number === '') $number = 'IMPORT-'.now()->format('YmdHis').'-'.($rowNumber + 2);
            $journalDate = $value($row, $index, ['journal_date', 'date']) ?: now()->toDateString();
            $fiscalYearId = self::nullableInteger($value($row, $index, ['fiscal_year_id']));
            $fiscalYearId ??= DB::table('fiscal_years')
                ->whereDate('start_date', '<=', $journalDate)
                ->whereDate('end_date', '>=', $journalDate)
                ->value('id');

            $rowRelationNo = trim((string) ($value($row, $index, ['relation_no', 'nomor_relasi']) ?: '')) ?: $defaultRelationNo;
            $grantId = self::resolveImportId($value($row, $index, ['grant_agreement_id', 'grant_agreement', 'grant_no']), 'grant_agreements', ['grant_no', 'agreement_no', 'agreement_name', 'relation_no'])
                ?: $defaultGrantId;
            if (! $grantId && $rowRelationNo) {
                $matchedGrant = DB::table('grant_agreements')->where('relation_no', $rowRelationNo)->value('id');
                $grantId = $matchedGrant ? (int) $matchedGrant : null;
            }
            $projectId = self::resolveImportId($value($row, $index, ['project_id', 'project']), 'projects', ['code', 'name', 'relation_no'])
                ?: $defaultProjectId;
            if (! $projectId && $rowRelationNo) {
                $matchedProject = DB::table('projects')->where('relation_no', $rowRelationNo)->value('id');
                $projectId = $matchedProject ? (int) $matchedProject : null;
            }
            if (! $projectId && $grantId) {
                $matchedProject = DB::table('projects')->where('grant_agreement_id', $grantId)->value('id');
                $projectId = $matchedProject ? (int) $matchedProject : null;
            }
            if (! $grantId && $projectId) {
                $matchedGrant = DB::table('projects')->where('id', $projectId)->value('grant_agreement_id');
                $grantId = $matchedGrant ? (int) $matchedGrant : null;
            }

            $classValue = trim((string) ($value($row, $index, ['class']) ?: '')) ?: null;
            $budgetRaw = $value($row, $index, ['budget_line_id', 'budget_code']);
            $budgetLineId = self::resolveBudgetLineImportId($budgetRaw, $classValue, $projectId, $grantId, $rowRelationNo, $fiscalYearId);
            if ($budgetLineId) {
                $blRow = DB::table('budget_lines')->where('id', $budgetLineId)->first(['project_id', 'grant_agreement_id', 'relation_no']);
                if ($blRow) {
                    $projectId = $projectId ?: ($blRow->project_id ? (int) $blRow->project_id : null);
                    $grantId = $grantId ?: ($blRow->grant_agreement_id ? (int) $blRow->grant_agreement_id : null);
                    $rowRelationNo = $rowRelationNo ?: ($blRow->relation_no ?: null);
                }
            }

            $donorId = self::resolveImportId($value($row, $index, ['donor_id', 'sof', 'donor']), 'donors', ['code', 'name']);
            if (! $donorId && $grantId) {
                $matchedDonor = DB::table('grant_agreements')->where('id', $grantId)->value('donor_id');
                $donorId = $matchedDonor ? (int) $matchedDonor : null;
            }
            $programId = self::resolveImportId($value($row, $index, ['program_id', 'program']), 'programs', ['code', 'name']);
            if (! $programId && $projectId) {
                $matchedProgram = DB::table('projects')->where('id', $projectId)->value('program_id');
                $programId = $matchedProgram ? (int) $matchedProgram : null;
            }
            if (! $rowRelationNo && $grantId) {
                $rowRelationNo = DB::table('grant_agreements')->where('id', $grantId)->value('relation_no') ?: null;
            }

            $groupKey = json_encode([$number, $fiscalYearId ?: 'unknown']);
            $grouped[$groupKey]['journal_number'] = $number;
            $grouped[$groupKey]['journal_date'] ??= $journalDate;
            $grouped[$groupKey]['fiscal_year_id'] ??= $fiscalYearId;
            $grouped[$groupKey]['reference'] ??= $value($row, $index, ['reference', 'reference_', 'ref']);
            $grouped[$groupKey]['description'] ??= $value($row, $index, ['purpose', 'journal_description', 'description']) ?: 'Imported journal';
            $grouped[$groupKey]['lines'][] = [
                'account_id' => self::resolveImportId($value($row, $index, ['account_id', 'coa_id', 'coa', 'gl_account_id']), 'chart_of_accounts', ['code', 'name']),
                'grant_agreement_id' => $grantId,
                'relation_no' => $rowRelationNo,
                'donor_id' => $donorId,
                'program_id' => $programId,
                'project_id' => $projectId,
                'budget_line_id' => $budgetLineId,
                'department_id' => self::resolveImportId($value($row, $index, ['department_id', 'department']), 'departments', ['code', 'name']),
                'class' => $classValue,
                'line_description' => $value($row, $index, ['line_description', 'line_note', 'description']) ?: 'Imported journal line',
                'debit' => (float) ($value($row, $index, ['debit', 'debits']) ?: 0),
                'credit' => (float) ($value($row, $index, ['credit', 'credits']) ?: 0),
            ];
        }
        $created = 0; $errors = []; $alreadyExists = [];
        foreach ($grouped as $data) {
            $number = $data['journal_number'];
            try {
                $existingQuery = Journal::withoutGlobalScope('fiscal_year_context')
                    ->where('journal_number', $number);
                if (! empty($data['fiscal_year_id'])) {
                    $existingQuery->where('fiscal_year_id', $data['fiscal_year_id']);
                }
                if ($existingQuery->exists()) {
                    $alreadyExists[] = $number;
                    continue;
                }
                Journal::withoutGlobalScope('fiscal_year_context')
                    ->onlyTrashed()
                    ->where('journal_number', $number)
                    ->when(! empty($data['fiscal_year_id']), fn ($q) => $q->where('fiscal_year_id', $data['fiscal_year_id']))
                    ->get()
                    ->each(function (Journal $trashed) {
                        $trashed->lines()->delete();
                        $trashed->forceDelete();
                    });
                $payload = new Request($data);
                $isDraftImport = collect($data['lines'] ?? [])->contains(fn ($line) => empty($line['project_id']) || empty($line['budget_line_id']));
                $validated = $isDraftImport
                    ? $this->validateDraftImportPayload($payload)
                    : $this->validatePayload($payload);
                $this->guardOpenPeriod($validated['journal_date']);
                DB::transaction(function () use ($validated, &$created) {
                    $lines = $validated['lines']; unset($validated['lines']);
                    $journal = Journal::create([...$validated, 'status' => 'draft']);
                    $this->syncLines($journal, $lines); $created++;
                });
            } catch (\Throwable $error) {
                $errors[] = "{$number}: ".($error->getMessage() ?: 'Invalid journal data');
            }
        }
        $message = "{$created} journal berhasil diimport, ".count($errors).' gagal.';
        if ($alreadyExists) $message .= ' '.count($alreadyExists).' journal dilewati karena sudah ada.';
        return response()->json(['success' => $created > 0, 'message' => $message, 'created' => $created, 'skipped' => count($errors) + count($alreadyExists), 'already_exists' => $alreadyExists, 'errors' => $errors]);
    }

    private function validateDraftImportPayload(Request $request): array
    {
        $payload = $request->validate([
            'journal_number' => ['nullable', 'string', 'max:40'],
            'journal_date' => ['required', 'date'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.grant_agreement_id' => ['nullable', 'integer', 'exists:grant_agreements,id'],
            'lines.*.relation_no' => ['nullable', 'string', 'max:100'],
            'lines.*.donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'lines.*.program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'lines.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'lines.*.budget_line_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
            'lines.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'lines.*.class' => ['nullable', 'string', 'max:120'],
            'lines.*.line_description' => ['required', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $payload['journal_type'] = 'manual';
        foreach ($payload['lines'] as $index => $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                throw ValidationException::withMessages(["lines.{$index}" => 'Each journal line must have either a debit or a credit.']);
            }
            $payload['lines'][$index]['debit'] = $debit;
            $payload['lines'][$index]['credit'] = $credit;
            $payload['lines'][$index]['line_order'] = $index + 1;
        }
        $payload = app(\App\Services\Accounting\JournalFiscalYearService::class)->payload($payload, $request);
        $this->assertUniqueJournalNumber($payload['journal_number'] ?? null, $payload['fiscal_year_id']);
        return $payload;
    }

    private static function nullableInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    private static function resolveImportId(mixed $value, string $table, array $columns): ?int
    {
        $id = self::nullableInteger($value);
        if ($id !== null && DB::table($table)->where('id', $id)->exists()) return $id;
        $text = trim((string) $value);
        if ($text === '') return null;
        foreach ($columns as $column) {
            $match = DB::table($table)->whereRaw("LOWER(TRIM(`{$column}`)) = ?", [strtolower($text)])->value('id');
            if ($match !== null) return (int) $match;
        }
        return null;
    }

    private static function resolveBudgetLineImportId(
        mixed $budgetRaw,
        ?string $classValue,
        ?int $projectId,
        ?int $grantId,
        ?string $relationNo,
        ?int $fiscalYearId
    ): ?int {
        $id = self::nullableInteger($budgetRaw);
        if ($id !== null && DB::table('budget_lines')->where('id', $id)->exists()) {
            return $id;
        }

        $candidates = [];
        $budgetText = trim((string) ($budgetRaw ?? ''));
        if ($budgetText !== '') {
            $candidates[] = $budgetText;
            if (preg_match('/^(\d+(?:\.\d+)+)/', $budgetText, $m)) {
                $candidates[] = $m[1];
            }
        }
        if ($classValue !== null && $classValue !== '') {
            if (preg_match('/^(\d+(?:\.\d+)+)\s+(.+)$/', $classValue, $m)) {
                $candidates[] = trim($m[1]);
                $candidates[] = trim($m[2]);
            } elseif (preg_match('/^(\d+(?:\.\d+)+)$/', $classValue, $m)) {
                $candidates[] = trim($m[1]);
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates, fn ($v) => $v !== '')));
        if ($candidates === []) {
            return null;
        }

        $scopeFilter = function ($query) use ($projectId, $grantId, $relationNo) {
            if (! $projectId && ! $grantId && ! $relationNo) {
                return $query;
            }

            return $query->where(function ($scope) use ($projectId, $grantId, $relationNo) {
                if ($projectId) {
                    $scope->orWhere('project_id', $projectId);
                }
                if ($grantId) {
                    $scope->orWhere('grant_agreement_id', $grantId);
                }
                if ($relationNo) {
                    $scope->orWhere('relation_no', $relationNo);
                }
            });
        };

        $baseQuery = fn () => $scopeFilter(DB::table('budget_lines')->whereNull('deleted_at'))
            ->when($fiscalYearId, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId));

        foreach ($candidates as $candidate) {
            $lower = strtolower($candidate);
            $match = $baseQuery()->whereRaw('LOWER(TRIM(line_code)) = ?', [$lower])->value('id')
                ?: $baseQuery()->whereRaw('LOWER(TRIM(description)) = ?', [$lower])->value('id');
            if ($match !== null) {
                return (int) $match;
            }
        }

        // Fallback without fiscal_year filter in case budget line is in an adjacent fiscal year
        if ($fiscalYearId) {
            $unscopedQuery = fn () => $scopeFilter(DB::table('budget_lines')->whereNull('deleted_at'));
            foreach ($candidates as $candidate) {
                $lower = strtolower($candidate);
                $match = $unscopedQuery()->whereRaw('LOWER(TRIM(line_code)) = ?', [$lower])->value('id')
                    ?: $unscopedQuery()->whereRaw('LOWER(TRIM(description)) = ?', [$lower])->value('id');
                if ($match !== null) {
                    return (int) $match;
                }
            }
        }

        return null;
    }

    public function show(Request $request, Journal $journal): JsonResponse
    {
        $query = Journal::withoutGlobalScope('fiscal_year_context')->whereKey($journal->id);
        app(\App\Services\Rbac\DataScopeService::class)->applyScope(
            $query,
            $request->user(),
            'created_by',
            null,
            null,
            ['accounting.journal.view', 'accounting.journal.review', 'accounting.journal.post']
        );

        if (! $query->exists()) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mengakses journal ini.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail journal berhasil dimuat.',
            'data' => $this->formatJournal($journal->load($this->with)),
        ]);
    }

    public function update(Request $request, Journal $journal): JsonResponse
    {
        $query = Journal::withoutGlobalScope('fiscal_year_context')->whereKey($journal->id);
        app(\App\Services\Rbac\DataScopeService::class)->applyScope(
            $query,
            $request->user(),
            'created_by',
            null,
            null,
            ['accounting.journal.update']
        );

        if (! $query->exists()) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mengubah journal ini.');
        }

        if (! in_array($journal->status, ['draft', 'rejected', 'returned_for_revision'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Journal hanya dapat diubah saat status draft, rejected, atau returned for revision.',
            ]);
        }

        $payload = $this->validatePayload($request, $journal);
        $this->guardOpenPeriod($payload['journal_date']);

        $journal = DB::transaction(function () use ($journal, $payload) {
            if (in_array($journal->status, ['rejected', 'returned_for_revision'], true)) {
                $journal->forceFill(['status' => 'draft'])->save();
            }
            $lines = $payload['lines'];
            unset($payload['lines']);

            $journal->update($payload);
            $journal->lines()->get()->each->delete();
            $this->syncLines($journal, $lines);

            return $journal->fresh()->load($this->with);
        });
        $this->storeAttachment($request, $journal);

        return response()->json([
            'success' => true,
            'message' => 'Journal berhasil diperbarui.',
            'data' => $this->formatJournal($journal),
        ]);
    }

    public function destroy(Request $request, Journal $journal): JsonResponse
    {
        $query = Journal::withoutGlobalScope('fiscal_year_context')->whereKey($journal->id);
        app(\App\Services\Rbac\DataScopeService::class)->applyScope(
            $query,
            $request->user(),
            'created_by',
            null,
            null,
            ['accounting.journal.delete']
        );

        if (! $query->exists()) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh menghapus journal ini.');
        }

        if ($journal->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Journal hanya dapat dihapus saat status draft.',
            ]);
        }

        $journal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Journal berhasil dihapus.',
        ]);
    }

    public function submit(Request $request, Journal $journal, ApprovalWorkflowService $workflow): JsonResponse
    {
        return DB::transaction(function () use ($request, $journal, $workflow) {
            $journal = Journal::withoutGlobalScope('fiscal_year_context')->lockForUpdate()->findOrFail($journal->id);
            if (! in_array($journal->status, ['draft', 'rejected', 'returned_for_revision'], true)) {
                throw ValidationException::withMessages(['status' => 'Journal harus berstatus draft, rejected, atau returned_for_revision sebelum submit.']);
            }
            $workflow->start('journal', $journal, (float)$journal->lines()->sum('debit'), $request->user()->id);
            $currentStatus = $journal->fresh()->status;
            return $this->transition($journal->fresh(), $currentStatus, 'submitted', ['submitted_by' => $request->user()->id, 'submitted_at' => now()], 'Journal berhasil disubmit.');
        });
    }
    public function review(Journal $journal): JsonResponse
    {
        return $this->transition($journal, 'submitted', 'reviewed', [
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ], 'Journal berhasil direview.');
    }

    public function approverOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission([
            'accounting.journal.view',
            'accounting.journal.review',
            'accounting.journal.post',
            'master-data.view',
            'settings.manage',
        ]), Response::HTTP_FORBIDDEN, 'Tidak diizinkan melihat daftar approver journal.');

        $resolver = app(\App\Services\Approval\ApprovalIdentityResolver::class);
        $users = \App\Models\User::query()
            ->with(['role.permissions:id,slug', 'employee:id,employee_id_number,name,position'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'employee_id', 'is_active'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role' => $user->role?->name,
                'role_slug' => $user->role?->slug,
                'employee_id' => $resolver->employeeId($user),
                'can_approve_journal' => $user->hasPermission('accounting.journal.post'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function assignApprover(Request $request, Journal $journal, ApprovalWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission([
            'accounting.journal.post',
            'accounting.journal.review',
            'master-data.manage',
            'settings.manage',
        ]), Response::HTTP_FORBIDDEN, 'Anda tidak memiliki izin menetapkan approver journal.');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $journal, $workflow, $data) {
            $journal = Journal::withoutGlobalScope('fiscal_year_context')->lockForUpdate()->findOrFail($journal->id);
            if (! in_array($journal->status, ['submitted', 'reviewed'], true)) {
                throw ValidationException::withMessages(['status' => 'Approver hanya dapat ditetapkan saat journal berstatus submitted atau reviewed.']);
            }
            $targetUser = \App\Models\User::with('role.permissions')->findOrFail($data['user_id']);
            $result = $workflow->assignCurrentStageApprover('journal', $journal, $targetUser, $request->user(), $data['notes'] ?? null);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $this->formatJournal($journal->fresh($this->with)),
            ]);
        });
    }

    public function reject(Request $request, Journal $journal, ApprovalWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission([
            'accounting.journal.post',
            'accounting.journal.review',
        ]), Response::HTTP_FORBIDDEN, 'Anda tidak memiliki izin menolak journal.');

        $notes = trim((string) ($request->input('notes') ?? ''));
        if ($notes === '') {
            $notes = 'Journal ditolak untuk revisi.';
        }

        return DB::transaction(function () use ($request, $journal, $workflow, $notes) {
            $journal = Journal::withoutGlobalScope('fiscal_year_context')->lockForUpdate()->findOrFail($journal->id);
            if (! in_array($journal->status, ['submitted', 'reviewed'], true)) {
                throw ValidationException::withMessages(['status' => 'Journal hanya dapat ditolak saat status submitted atau reviewed.']);
            }

            $asReviewer = $journal->status === 'submitted' && $request->user()->hasPermission('accounting.journal.review');
            $workflow->reject('journal', $journal, $request->user(), $notes, $asReviewer);

            $from = $journal->status;
            return $this->transition($journal, $from, 'rejected', [], 'Journal ditolak.');
        });
    }

    public function post(Request $request, Journal $journal, ApprovalWorkflowService $workflow): JsonResponse
    {
        return DB::transaction(function () use ($request, $journal, $workflow) {
            $journal = Journal::withoutGlobalScope('fiscal_year_context')->lockForUpdate()->findOrFail($journal->id);
            $yearId = app(\App\Services\Accounting\JournalPostingGuard::class)->assertReady($journal, $request->user());
            $journal->fiscal_year_id = $yearId;
            $amount = (float) $journal->lines()->sum('debit');
            $approval = $workflow->approve('journal', $journal, $request->user(), $request->input('notes'));
            if (!$approval['managed'] && $workflow->requiresApproval('journal', $journal, $amount)) {
                throw ValidationException::withMessages(['approval' => 'The journal must be submitted to the Approval Matrix before posting.']);
            }
            if (!$approval['completed']) {
                return response()->json(['success' => true, 'message' => "Approval tahap selesai. Menunggu persetujuan tahap {$approval['next_level']}.", 'data' => $this->formatJournal($journal->fresh($this->with))]);
            }
            $journal->fiscal_year_id = $yearId;
            return $this->transition($journal, 'reviewed', 'posted', [
                'fiscal_year_id' => $yearId,
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
            ], 'Journal berhasil diposting.');
        });
    }
    public function reverse(Request $request, Journal $journal): JsonResponse
    {
        if ($journal->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => 'Hanya journal posted yang dapat direverse.',
            ]);
        }

        $query = Journal::withoutGlobalScope('fiscal_year_context')->whereKey($journal->id);
        app(\App\Services\Rbac\DataScopeService::class)->applyScope(
            $query,
            $request->user(),
            'created_by',
            null,
            null,
            ['accounting.journal.reverse']
        );

        if (! $query->exists()) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mereverse journal ini.');
        }

        $this->guardOpenPeriod(now()->toDateString());

        $reversal = DB::transaction(function () use ($journal) {
            $reversal = Journal::withoutGlobalScope('fiscal_year_context')->create([
                'journal_number' => $this->nextJournalNumber('RV'),
                'journal_date' => now()->toDateString(),
                'journal_type' => 'reversal',
                'reference' => $journal->journal_number,
                'description' => 'Reversal for '.$journal->journal_number,
                'currency_id' => $journal->currency_id,
                'exchange_rate' => $journal->exchange_rate,
                'status' => 'posted',
                'reversal_of_id' => $journal->id,
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);

            foreach ($journal->lines as $line) {
                $reversal->lines()->create([
                    'account_id' => $line->account_id,
                    'donor_id' => $line->donor_id,
                    'program_id' => $line->program_id,
                    'project_id' => $line->project_id,
                    'budget_line_id' => $line->budget_line_id,
                    'department_id' => $line->department_id,
                    'line_description' => 'Reverse: '.$line->line_description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'line_order' => $line->line_order,
                ]);
            }

            $journal->update([
                'status' => 'reversed',
                'reversed_by' => request()->user()->id,
                'reversed_at' => now(),
            ]);

            return $reversal->load($this->with);
        });

        return response()->json([
            'success' => true,
            'message' => 'Journal reversal berhasil dibuat.',
            'data' => $this->formatJournal($reversal),
        ], Response::HTTP_CREATED);
    }

    private function validatePayload(Request $request, ?Journal $journal = null): array
    {
        if (is_string($request->input('lines'))) {
            $request->merge(['lines' => json_decode($request->input('lines'), true) ?: []]);
        }
        $payload = $request->validate([
            'journal_number' => ['nullable', 'string', 'max:40'],
            'journal_date' => ['required', 'date'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'journal_type' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'source_type' => ['nullable', 'string', 'max:80'],
            'source_id' => ['nullable', 'integer'],
            'description' => ['required', 'string'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'functional_currency_code' => ['nullable', 'string', 'max:10'],
            'original_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_source' => ['nullable', 'string', 'max:50'],
            'rate_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            // These dimensions are resolved from the selected project. Values
            // sent by clients are never trusted as the source of truth.
            'lines.*.donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'lines.*.grant_agreement_id' => ['nullable', 'integer', 'exists:grant_agreements,id'],
            'lines.*.relation_no' => ['nullable', 'string', 'max:100'],
            'lines.*.program_id' => ['nullable', 'integer', 'exists:programs,id'],
            // Every journal line is booked to a project so donor reporting can
            // aggregate all projects under one donor without losing the detail.
            'lines.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'lines.*.partner_id' => ['nullable', 'integer', 'exists:beneficiary_partners,id'],
            'lines.*.budget_line_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
            'lines.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'lines.*.class' => ['nullable', 'string', 'max:120'],
            'lines.*.line_description' => ['required', 'string', 'max:255'],
            'lines.*.class' => ['nullable', 'string', 'max:120'],
            'lines.*.name' => ['nullable', 'string', 'max:150'],
            'lines.*.location' => ['nullable', 'string', 'max:150'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);
        unset($payload['attachment']);

        // Manual journal entry is the only type exposed by the journal form.
        $payload['journal_type'] = 'manual';

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($payload['lines'] as $index => $line) {
            $line = app(\App\Services\Accounting\JournalDimensionResolver::class)->resolve($line, $index);
            $payload['lines'][$index] = $line;
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => 'Each journal line must have either a debit or a credit, not both.',
                ]);
            }

            $payload['lines'][$index]['debit'] = $debit;
            $payload['lines'][$index]['credit'] = $credit;
            $payload['lines'][$index]['line_order'] = $index + 1;
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw ValidationException::withMessages([
                'lines' => 'Total debits must equal total credits.',
            ]);
        }

        $payload['exchange_rate'] = $payload['exchange_rate'] ?? 1;
        if (array_key_exists('original_amount', $payload)) {
            $payload['converted_amount'] = round((float) $payload['original_amount'] * (float) $payload['exchange_rate'], 2);
            $payload['rate_date'] = $payload['rate_date'] ?? $payload['journal_date'];
            $payload['rate_source'] = $payload['rate_source'] ?? 'transaction_snapshot';
        }

        $payload = app(\App\Services\Accounting\JournalFiscalYearService::class)->payload($payload, $request, $journal);
        $this->assertUniqueJournalNumber($payload['journal_number'] ?? null, $payload['fiscal_year_id'], $journal?->id);
        return $payload;
    }

    private function assertUniqueJournalNumber(?string $number, int $fiscalYearId, ?int $ignoreId = null): void
    {
        if (! $number) return;

        $query = Journal::withoutGlobalScope('fiscal_year_context')
            ->where('journal_number', $number)
            ->where('fiscal_year_id', $fiscalYearId);
        if ($ignoreId) $query->whereKeyNot($ignoreId);

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'journal_number' => 'Journal number sudah digunakan pada Fiscal Year yang dipilih.',
            ]);
        }
    }

    private function storeAttachment(Request $request, Journal $journal): void
    {
        if (! $request->hasFile('attachment')) return;
        $attachments = $journal->attachments ?: [];
        $attachments[] = ['name' => $request->file('attachment')->getClientOriginalName(), 'path' => $request->file('attachment')->store('journal-attachments', 'public')];
        $journal->update(['attachments' => $attachments]);
    }

    private function syncLines(Journal $journal, array $lines): void
    {
        foreach ($lines as $line) {
            $line = app(\App\Services\Accounting\JournalDimensionResolver::class)->resolve($line);
            $journal->lines()->create($line);
        }
    }

    public function transition(Journal $journal, string $from, string $to, array $extra, string $message, ?Request $request = null): JsonResponse
    {
        $request = $request ?? request();

        $query = Journal::withoutGlobalScope('fiscal_year_context')->whereKey($journal->id);
        app(\App\Services\Rbac\DataScopeService::class)->applyScope(
            $query,
            $request->user(),
            'created_by',
            null,
            null,
            ['accounting.journal.submit', 'accounting.journal.review', 'accounting.journal.post']
        );

        if (! $query->exists()) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mengubah journal ini.');
        }

        if ($journal->status !== $from) {
            throw ValidationException::withMessages([
                'status' => "The journal must have {$from} status for this action.",
            ]);
        }

        $journal->update([
            'status' => $to,
            ...$extra,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->formatJournal($journal->fresh()->load($this->with)),
        ]);
    }

    private function guardOpenPeriod(string $date): void
    {
        app(AccountingPeriodService::class)->ensureOpen($date, 'journal_date');
    }

    private function nextJournalNumber(string $prefix = 'JV'): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }

    private function formatJournal(Journal $journal): array
    {
        $journal->loadMissing($this->with);

        return [
            'id' => $journal->id,
            'journal_number' => $journal->journal_number,
            'journal_date' => $journal->journal_date?->toDateString(),
            'fiscal_year_id' => $journal->fiscal_year_id,
            'fiscal_year' => $journal->fiscalYear ? [
                'id' => $journal->fiscalYear->id, 'year' => $journal->fiscalYear->year, 'name' => $journal->fiscalYear->name,
                'start_date' => $journal->fiscalYear->start_date?->toDateString(), 'end_date' => $journal->fiscalYear->end_date?->toDateString(),
            ] : null,
            'journal_type' => $journal->journal_type,
            'source_type' => $journal->source_type,
            'reference' => $journal->reference,
            'description' => $journal->description,
            'currency_id' => $journal->currency_id,
            'currency_code' => $journal->currency?->code,
            'exchange_rate' => $journal->exchange_rate,
            'status' => $journal->status,
            'approval_progress' => app(\App\Services\Approval\ApprovalWorkflowService::class)->progressFor('journal', $journal, request()->user()),
            'total_debit' => $journal->total_debit,
            'total_credit' => $journal->total_credit,
            'lines' => $journal->lines->map(fn ($line) => [
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'donor_id' => $line->donor_id,
                'donor_code' => $line->donor?->code,
                'donor_name' => $line->donor?->name,
                'program_id' => $line->program_id,
                'program_code' => $line->program?->code,
                'program_name' => $line->program?->name,
                'grant_agreement_id' => $line->grant_agreement_id ?: $line->project?->grant_agreement_id ?: $line->budgetLine?->grant_agreement_id,
                'partner_id' => $line->partner_id,
                'partner_name' => $line->partner?->name,
                'project_id' => $line->project_id,
                'project_code' => $line->project?->code,
                'project_name' => $line->project?->name,
                'grant_id' => $line->project?->grantAgreement?->id,
                'grant_no' => $line->project?->grantAgreement?->grant_no,
                'grant_name' => $line->project?->grantAgreement?->agreement_name,
                'grant_donor_code' => $line->project?->grantAgreement?->donor?->code,
                'grant_donor_name' => $line->project?->grantAgreement?->donor?->name,
                'budget_line_id' => $line->budget_line_id,
                'budget_code' => $line->budgetLine?->line_code,
                'relation_no' => $line->relation_no ?: $line->project?->relation_no ?: $line->grantAgreement?->relation_no ?: $line->budgetLine?->relation_no,
                'department_id' => $line->department_id,
                'line_description' => $line->line_description,
                'class' => $line->class,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'line_order' => $line->line_order,
            ])->values(),
            'submitted_at' => $journal->submitted_at?->toISOString(),
            'reviewed_at' => $journal->reviewed_at?->toISOString(),
            'posted_at' => $journal->posted_at?->toISOString(),
            'reversed_at' => $journal->reversed_at?->toISOString(),
            'created_at' => $journal->created_at?->toISOString(),
        ];
    }
}
