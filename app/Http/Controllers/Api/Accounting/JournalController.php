<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class JournalController extends Controller
{
    private array $with = [
        'currency:id,code,name',
        'lines.account:id,code,name',
        'lines.donor:id,code,name',
        'lines.program:id,code,name',
        'lines.project:id,code,name',
        'lines.budgetLine:id,line_code,description',
        'lines.department:id,code,name',
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage < 1 || $perPage > 100 ? 10 : $perPage;

        $query = Journal::query()
            ->with(['currency:id,code,name', 'lines'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
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

            $journal = Journal::query()->create([
                ...$payload,
                'journal_number' => $payload['journal_number'] ?? $this->nextJournalNumber(),
                'status' => 'draft',
            ]);

            $this->syncLines($journal, $lines);

            return $journal->load($this->with);
        });

        return response()->json([
            'success' => true,
            'message' => 'Journal berhasil dibuat.',
            'data' => $this->formatJournal($journal),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, Journal $journal): JsonResponse
    {
        $query = Journal::whereKey($journal->id);
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
        $query = Journal::whereKey($journal->id);
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
            $journal->lines()->delete();
            $this->syncLines($journal, $lines);

            return $journal->fresh()->load($this->with);
        });

        return response()->json([
            'success' => true,
            'message' => 'Journal berhasil diperbarui.',
            'data' => $this->formatJournal($journal),
        ]);
    }

    public function destroy(Request $request, Journal $journal): JsonResponse
    {
        $query = Journal::whereKey($journal->id);
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

    public function submit(Journal $journal): JsonResponse
    {
        return $this->transition($journal, 'draft', 'submitted', [
            'submitted_by' => request()->user()->id,
            'submitted_at' => now(),
        ], 'Journal berhasil disubmit.');
    }

    public function review(Journal $journal): JsonResponse
    {
        return $this->transition($journal, 'submitted', 'reviewed', [
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ], 'Journal berhasil direview.');
    }

    public function post(Journal $journal): JsonResponse
    {
        $this->guardOpenPeriod($journal->journal_date->toDateString());

        return $this->transition($journal, 'reviewed', 'posted', [
            'posted_by' => request()->user()->id,
            'posted_at' => now(),
        ], 'Journal berhasil diposting.');
    }

    public function reverse(Request $request, Journal $journal): JsonResponse
    {
        if ($journal->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => 'Hanya journal posted yang dapat direverse.',
            ]);
        }

        $query = Journal::whereKey($journal->id);
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
            $reversal = Journal::query()->create([
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
        $payload = $request->validate([
            'journal_number' => ['nullable', 'string', 'max:40', 'unique:journals,journal_number,'.($journal?->id ?? 'NULL').',id'],
            'journal_date' => ['required', 'date'],
            'journal_type' => ['required', 'in:manual,adjustment,recurring,accrual,reversal,fx_adjustment'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'lines.*.program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'lines.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'lines.*.budget_line_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
            'lines.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'lines.*.line_description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($payload['lines'] as $index => $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => 'Setiap baris journal harus memiliki debit atau credit, bukan keduanya.',
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
                'lines' => 'Total debit harus sama dengan total credit.',
            ]);
        }

        $payload['exchange_rate'] = $payload['exchange_rate'] ?? 1;

        return $payload;
    }

    private function syncLines(Journal $journal, array $lines): void
    {
        foreach ($lines as $line) {
            $journal->lines()->create($line);
        }
    }

    public function transition(Journal $journal, string $from, string $to, array $extra, string $message, ?Request $request = null): JsonResponse
    {
        $request = $request ?? request();

        $query = Journal::whereKey($journal->id);
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
                'status' => "Journal harus berstatus {$from} untuk aksi ini.",
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
            'journal_type' => $journal->journal_type,
            'reference' => $journal->reference,
            'description' => $journal->description,
            'currency_id' => $journal->currency_id,
            'currency_code' => $journal->currency?->code,
            'exchange_rate' => $journal->exchange_rate,
            'status' => $journal->status,
            'total_debit' => $journal->total_debit,
            'total_credit' => $journal->total_credit,
            'lines' => $journal->lines->map(fn ($line) => [
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'donor_id' => $line->donor_id,
                'program_id' => $line->program_id,
                'project_id' => $line->project_id,
                'budget_line_id' => $line->budget_line_id,
                'department_id' => $line->department_id,
                'line_description' => $line->line_description,
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
