<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Accounting\RecurringJournal;
use App\Services\Accounting\AccountingPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringJournalController extends Controller
{
    public function index(): JsonResponse
    {
        $journals = RecurringJournal::query()
            ->with('lines.account:id,code,name')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $journals,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPayload($request);

        $journal = DB::transaction(function () use ($payload) {
            $lines = $payload['lines'];
            unset($payload['lines']);

            $journal = RecurringJournal::query()->create($payload);
            $this->syncLines($journal, $lines);

            return $journal->load('lines.account:id,code,name');
        });

        return response()->json([
            'success' => true,
            'message' => 'Recurring journal berhasil dibuat.',
            'data' => $journal,
        ], 201);
    }

    public function update(Request $request, RecurringJournal $recurringJournal): JsonResponse
    {
        $payload = $this->validatedPayload($request, true);

        $journal = DB::transaction(function () use ($recurringJournal, $payload) {
            $lines = $payload['lines'];
            unset($payload['lines']);

            $recurringJournal->update($payload);
            $this->syncLines($recurringJournal, $lines);

            return $recurringJournal->fresh('lines.account:id,code,name');
        });

        return response()->json([
            'success' => true,
            'message' => 'Recurring journal berhasil diperbarui.',
            'data' => $journal,
        ]);
    }

    public function destroy(RecurringJournal $recurringJournal): JsonResponse
    {
        $recurringJournal->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Recurring journal berhasil dinonaktifkan.',
            'data' => $recurringJournal->fresh('lines.account:id,code,name'),
        ]);
    }

    public function generate(Request $request, RecurringJournal $recurringJournal): JsonResponse
    {
        if (! $recurringJournal->is_active) {
            throw ValidationException::withMessages(['status' => 'Recurring journal sudah tidak aktif.']);
        }

        if ($recurringJournal->ends_at && $recurringJournal->next_run->gt($recurringJournal->ends_at)) {
            throw ValidationException::withMessages(['next_run' => 'Recurring journal sudah melewati tanggal akhir.']);
        }

        app(AccountingPeriodService::class)->ensureOpen($recurringJournal->next_run->toDateString(), 'next_run');

        $journal = DB::transaction(function () use ($request, $recurringJournal) {
            $recurringJournal->load('lines');

            $journal = Journal::query()->create([
                'journal_number' => $this->nextJournalNumber(),
                'journal_date' => $recurringJournal->next_run->toDateString(),
                'journal_type' => 'recurring',
                'reference' => 'Recurring Journal #'.$recurringJournal->id,
                'description' => $recurringJournal->description ?: $recurringJournal->name,
                'status' => 'draft',
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            $journal->lines()->createMany($recurringJournal->lines->values()->map(fn ($line, int $index) => [
                'account_id' => $line->account_id,
                'line_description' => $line->description ?: $recurringJournal->name,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'line_order' => $index + 1,
            ])->all());

            $recurringJournal->update(['next_run' => $this->advanceNextRun($recurringJournal)]);

            return $journal->load('lines.account:id,code,name');
        });

        return response()->json([
            'success' => true,
            'message' => 'Jurnal draft dari recurring journal berhasil dibuat.',
            'data' => $journal,
        ], 201);
    }

    private function validatedPayload(Request $request, bool $isUpdate = false): array
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'frequency' => ['required', 'in:monthly,quarterly,yearly'],
            'next_run' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:next_run'],
            'description' => ['nullable', 'string'],
            'is_active' => [$isUpdate ? 'sometimes' : 'nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string'],
        ]);

        $debit = round((float) collect($payload['lines'])->sum(fn ($line) => (float) ($line['debit'] ?? 0)), 2);
        $credit = round((float) collect($payload['lines'])->sum(fn ($line) => (float) ($line['credit'] ?? 0)), 2);

        if ($debit <= 0 || $debit !== $credit) {
            throw ValidationException::withMessages([
                'lines' => 'Debit dan credit harus seimbang dan lebih besar dari nol.',
            ]);
        }

        $payload['is_active'] = $payload['is_active'] ?? true;

        return $payload;
    }

    private function syncLines(RecurringJournal $journal, array $lines): void
    {
        $journal->lines()->delete();
        $journal->lines()->createMany(collect($lines)->values()->map(fn ($line, int $index) => [
            'account_id' => $line['account_id'],
            'description' => $line['description'] ?? null,
            'debit' => $line['debit'] ?? 0,
            'credit' => $line['credit'] ?? 0,
            'line_order' => $index + 1,
        ])->all());
    }

    private function nextJournalNumber(): string
    {
        return 'RJ-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }

    private function advanceNextRun(RecurringJournal $journal): string
    {
        $date = CarbonImmutable::parse($journal->next_run);

        return match ($journal->frequency) {
            'quarterly' => $date->addMonthsNoOverflow(3)->toDateString(),
            'yearly' => $date->addYearNoOverflow()->toDateString(),
            default => $date->addMonthNoOverflow()->toDateString(),
        };
    }
}
