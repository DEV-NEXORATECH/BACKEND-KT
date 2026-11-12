<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
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
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage < 1 || $perPage > 100 ? 10 : $perPage;

        $query = Journal::withoutGlobalScope('fiscal_year_context')
            ->with(['currency:id,code,name', 'lines'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('fiscal_year_id'), fn ($query) => $query->where('fiscal_year_id', $request->integer('fiscal_year_id')))
            ->when($request->filled('project_id'), fn ($query) => $query->whereHas('lines', fn ($line) => $line->where('project_id', $request->integer('project_id'))))
            ->when($request->filled('grant_agreement_id'), fn ($query) => $query->whereHas('lines', fn ($line) => $line->where('grant_agreement_id', $request->integer('grant_agreement_id'))))
            ->when($request->filled('budget_line_id'), fn ($query) => $query->whereHas('lines', fn ($line) => $line->where('budget_line_id', $request->integer('budget_line_id'))))
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

        $journals = $query->latest('journal_date')
            ->latest('id')
            ->paginate($perPage);

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
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']]);
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
            $grouped[$number]['journal_number'] = $number;
            $grouped[$number]['journal_date'] ??= $value($row, $index, ['journal_date', 'date']) ?: now()->toDateString();
            $grouped[$number]['reference'] ??= $value($row, $index, ['reference', 'ref']);
            $grouped[$number]['description'] ??= $value($row, $index, ['description', 'journal_description']) ?: 'Imported journal';
            $grouped[$number]['lines'][] = [
                'account_id' => self::resolveImportId($value($row, $index, ['account_id', 'coa_id', 'coa', 'gl_account_id']), 'chart_of_accounts', ['code', 'name']),
                // Donor/program/project dimensions are resolved from the project
                // during validation. Empty CSV cells must stay NULL, not become 0.
                'grant_agreement_id' => self::nullableInteger($value($row, $index, ['grant_agreement_id'])),
                'relation_no' => $value($row, $index, ['relation_no']) ?: null,
                'donor_id' => self::nullableInteger($value($row, $index, ['donor_id', 'sof'])),
                'program_id' => self::nullableInteger($value($row, $index, ['program_id'])),
                'project_id' => self::nullableInteger($value($row, $index, ['project_id'])),
                'budget_line_id' => self::nullableInteger($value($row, $index, ['budget_line_id', 'budget_code'])),
                'department_id' => $value($row, $index, ['department_id']) ?: null,
                'line_description' => $value($row, $index, ['line_description', 'line_note']) ?: 'Imported journal line',
                'debit' => (float) ($value($row, $index, ['debit', 'debits']) ?: 0),
                'credit' => (float) ($value($row, $index, ['credit', 'credits']) ?: 0),
            ];
        }
        $created = 0; $errors = []; $alreadyExists = [];
        foreach ($grouped as $number => $data) {
            try {
                if (Journal::withoutGlobalScope('fiscal_year_context')->withTrashed()->where('journal_number', $number)->exists()) {
                    $alreadyExists[] = $number;
                    continue;
                }
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
            'journal_number' => ['nullable', 'string', 'max:40', 'unique:journals,journal_number'],
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
        return app(\App\Services\Accounting\JournalFiscalYearService::class)->payload($payload, $request, $journal ?? null);
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

        if ($journal->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Journal hanya dapat diubah saat status draft.',
            ]);
        }

        $payload = $this->validatePayload($request, $journal);
        $this->guardOpenPeriod($payload['journal_date']);

        $journal = DB::transaction(function () use ($journal, $payload) {
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
            if ($journal->status !== 'draft') throw ValidationException::withMessages(['status' => 'Journal harus draft sebelum submit.']);
            $workflow->start('journal', $journal, (float)$journal->lines()->sum('debit'), $request->user()->id);
            return $this->transition($journal, 'draft', 'submitted', ['submitted_by' => $request->user()->id, 'submitted_at' => now()], 'Journal berhasil disubmit.');
        });
    }
    public function review(Journal $journal): JsonResponse
    {
        return $this->transition($journal, 'submitted', 'reviewed', [
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ], 'Journal berhasil direview.');
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
            'journal_number' => ['nullable', 'string', 'max:40', 'unique:journals,journal_number,'.($journal?->id ?? 'NULL').',id'],
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

        return app(\App\Services\Accounting\JournalFiscalYearService::class)->payload($payload, $request, $journal ?? null);
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
