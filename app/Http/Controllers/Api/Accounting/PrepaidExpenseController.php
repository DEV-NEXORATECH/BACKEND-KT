<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PrepaidExpense;
use App\Models\Accounting\Journal;
use App\Models\Master\BankAccount;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Project;
use Illuminate\Http\Request;
use App\Services\Rbac\DataScopeService;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\JournalFiscalYearService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrepaidExpenseController extends Controller
{
    private array $with = ['prepaidAccount:id,code,name,account_type', 'expenseAccount:id,code,name,account_type', 'project:id,code,name,relation_no,grant_agreement_id,program_id,bank_account_id', 'grantAgreement:id,grant_no,agreement_name,relation_no,donor_id,bank_account_id', 'donor:id,code,name', 'program:id,code,name', 'budgetLine:id,line_code,description,project_id,grant_agreement_id,relation_no,gl_account_id', 'bankAccount:id,bank_name,account_number,gl_account_id', 'recognitionJournal:id,journal_number,status', 'amortizations:id,prepaid_expense_id,period,amortization_date,amount,journal_id'];

    public function index(Request $request)
    {
        $query = PrepaidExpense::with($this->with);
        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'project_id', null);
        $rows = $query
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($x) => $x->where('reference', 'like', '%'.$request->search.'%')->orWhere('description', 'like', '%'.$request->search.'%')->orWhere('relation_no', 'like', '%'.$request->search.'%')))
            ->when($request->filled('relation_no'), fn ($q) => $q->where('relation_no', trim((string) $request->query('relation_no'))))
            ->latest()->paginate(min(100, max(1, (int) $request->get('per_page', 10))));
        return response()->json(['success' => true, 'data' => $rows->items(), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()]]);
    }

    public function options()
    {
        $projects = Project::query()->withoutGlobalScope('fiscal_year_context')->with(['grantAgreementUnscoped:id,grant_no,agreement_name,relation_no,donor_id,bank_account_id', 'bankAccount:id,bank_name,account_number,gl_account_id'])->where('is_active', true)->orderBy('name')->get();
        return response()->json([
            'success' => true,
            'data' => ChartOfAccount::where('is_header', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'account_type']),
            'projects' => $projects,
            'bank_accounts' => BankAccount::withoutGlobalScope('fiscal_year_context')->where('is_active', true)->orderBy('bank_name')->get(['id', 'bank_name', 'account_number', 'gl_account_id']),
            'budget_lines' => BudgetLine::withoutGlobalScope('fiscal_year_context')->where('is_active', true)->orderBy('line_code')->get(['id', 'line_code', 'description', 'project_id', 'grant_agreement_id', 'relation_no', 'gl_account_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedPayload($request);
        $data['created_by'] = $request->user()?->id;
        $data['status'] ??= 'active';
        return response()->json(['success' => true, 'message' => 'Prepaid expense berhasil dibuat. Posting pengakuan awal sebelum amortisasi.', 'data' => PrepaidExpense::create($data)->load($this->with)], 201);
    }

    public function update(Request $request, PrepaidExpense $prepaidExpense)
    {
        $this->ensureScope($request, $prepaidExpense);
        $data = $this->validatedPayload($request, $prepaidExpense);
        if ($prepaidExpense->recognition_journal_id && collect(['start_date', 'periods', 'total_amount', 'prepaid_account_id', 'bank_account_id'])->contains(fn ($field) => array_key_exists($field, $data) && (string) $data[$field] !== (string) $prepaidExpense->{$field})) throw ValidationException::withMessages(['prepaid_expense' => 'Nilai, periode, akun prepaid, dan bank tidak dapat diubah setelah jurnal pengakuan diposting.']);
        $prepaidExpense->update($data);
        return response()->json(['success' => true, 'message' => 'Prepaid expense berhasil diperbarui.', 'data' => $prepaidExpense->fresh($this->with)]);
    }

    public function recognize(Request $request, PrepaidExpense $prepaidExpense)
    {
        $this->ensureScope($request, $prepaidExpense);
        if ($prepaidExpense->recognition_journal_id) throw ValidationException::withMessages(['journal' => 'Pengakuan prepaid sudah pernah diposting.']);
        $data = $request->validate(['payment_date' => ['nullable', 'date'], 'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id']]);
        $date = $data['payment_date'] ?? $prepaidExpense->start_date->toDateString();
        app(AccountingPeriodService::class)->ensureOpen($date, 'payment_date');
        $prepaidExpense->loadMissing(['project', 'grantAgreement']);
        $bankId = $data['bank_account_id'] ?? $prepaidExpense->bank_account_id ?? $prepaidExpense->project?->bank_account_id ?? $prepaidExpense->grantAgreement?->bank_account_id;
        $bank = $bankId ? BankAccount::withoutGlobalScope('fiscal_year_context')->find($bankId) : null;
        if (! $prepaidExpense->prepaid_account_id || ! $bank?->gl_account_id) throw ValidationException::withMessages(['account' => 'Akun prepaid dan rekening bank dengan GL account wajib dipilih.']);
        $fiscalYear = app(JournalFiscalYearService::class)->resolve($date, $prepaidExpense->fiscal_year_id);

        $row = DB::transaction(function () use ($request, $prepaidExpense, $bank, $date, $fiscalYear) {
            $journal = Journal::create(['fiscal_year_id' => $fiscalYear->id, 'journal_number' => 'PRE-'.now()->format('YmdHis').'-'.random_int(100, 999), 'journal_date' => $date, 'journal_type' => 'manual', 'reference' => $prepaidExpense->reference, 'description' => 'Prepaid recognition - '.$prepaidExpense->description, 'status' => 'posted', 'posted_by' => $request->user()->id, 'posted_at' => now(), 'source_type' => 'prepaid_expense', 'source_id' => $prepaidExpense->id]);
            $dimensions = $this->dimensions($prepaidExpense);
            $journal->lines()->create(['account_id' => $prepaidExpense->prepaid_account_id, ...$dimensions, 'line_description' => 'Recognize prepaid asset', 'debit' => $prepaidExpense->total_amount, 'credit' => 0, 'line_order' => 1]);
            $journal->lines()->create(['account_id' => $bank->gl_account_id, ...$dimensions, 'line_description' => 'Prepaid payment from bank', 'debit' => 0, 'credit' => $prepaidExpense->total_amount, 'line_order' => 2]);
            $journal->syncBankTransactions();
            $prepaidExpense->update(['fiscal_year_id' => $fiscalYear->id, 'bank_account_id' => $bank->id, 'recognition_journal_id' => $journal->id, 'status' => 'active']);
            return $prepaidExpense->fresh($this->with);
        });
        return response()->json(['success' => true, 'message' => 'Pengakuan prepaid dan mutasi bank berhasil diposting.', 'data' => $row]);
    }

    public function amortize(Request $request, PrepaidExpense $prepaidExpense)
    {
        $this->ensureScope($request, $prepaidExpense);
        if (! $prepaidExpense->recognition_journal_id) throw ValidationException::withMessages(['journal' => 'Posting pengakuan prepaid terlebih dahulu.']);
        if ($prepaidExpense->status !== 'active') throw ValidationException::withMessages(['status' => 'Hanya prepaid aktif yang dapat diamortisasi.']);
        if (! $prepaidExpense->expense_account_id || ! $prepaidExpense->prepaid_account_id) throw ValidationException::withMessages(['account' => 'Akun prepaid dan akun beban wajib dikonfigurasi.']);
        $nextPeriod = $prepaidExpense->amortizations()->count() + 1;
        if ($nextPeriod > $prepaidExpense->periods) throw ValidationException::withMessages(['period' => 'Seluruh periode amortisasi sudah diposting.']);
        $data = $request->validate(['amortization_date' => ['nullable', 'date']]);
        $date = $data['amortization_date'] ?? $prepaidExpense->start_date->copy()->addMonths($nextPeriod - 1)->toDateString();
        app(AccountingPeriodService::class)->ensureOpen($date, 'amortization_date');
        $fiscalYear = app(JournalFiscalYearService::class)->resolve($date);
        $monthly = round((float) $prepaidExpense->total_amount / $prepaidExpense->periods, 2);
        $amount = $nextPeriod === (int) $prepaidExpense->periods ? round((float) $prepaidExpense->total_amount - (float) $prepaidExpense->amortized_amount, 2) : $monthly;

        $row = DB::transaction(function () use ($request, $prepaidExpense, $date, $fiscalYear, $nextPeriod, $amount) {
            $journal = Journal::create(['fiscal_year_id' => $fiscalYear->id, 'journal_number' => 'PRE-AM-'.now()->format('YmdHis').'-'.random_int(100, 999), 'journal_date' => $date, 'journal_type' => 'adjustment', 'reference' => $prepaidExpense->reference.'-'.str_pad((string) $nextPeriod, 2, '0', STR_PAD_LEFT), 'description' => 'Prepaid amortization - '.$prepaidExpense->description, 'status' => 'posted', 'posted_by' => $request->user()->id, 'posted_at' => now(), 'source_type' => 'prepaid_expense_amortization', 'source_id' => $prepaidExpense->id]);
            $dimensions = $this->dimensions($prepaidExpense);
            $journal->lines()->create(['account_id' => $prepaidExpense->expense_account_id, ...$dimensions, 'budget_line_id' => $prepaidExpense->budget_line_id, 'line_description' => 'Prepaid amortization expense', 'debit' => $amount, 'credit' => 0, 'line_order' => 1]);
            $journal->lines()->create(['account_id' => $prepaidExpense->prepaid_account_id, ...$dimensions, 'line_description' => 'Reduce prepaid asset', 'debit' => 0, 'credit' => $amount, 'line_order' => 2]);
            $prepaidExpense->amortizations()->create(['period' => $nextPeriod, 'amortization_date' => $date, 'amount' => $amount, 'journal_id' => $journal->id, 'created_by' => $request->user()->id]);
            $amortized = round((float) $prepaidExpense->amortized_amount + $amount, 2);
            $prepaidExpense->update(['amortized_amount' => $amortized, 'status' => $amortized >= (float) $prepaidExpense->total_amount ? 'completed' : 'active']);
            return $prepaidExpense->fresh($this->with);
        });
        return response()->json(['success' => true, 'message' => "Amortisasi periode {$nextPeriod} berhasil diposting ke jurnal.", 'data' => $row]);
    }

    public function destroy(Request $request, PrepaidExpense $prepaidExpense)
    {
        $this->ensureScope($request, $prepaidExpense);
        if ($prepaidExpense->recognition_journal_id || $prepaidExpense->amortizations()->exists()) throw ValidationException::withMessages(['prepaid_expense' => 'Prepaid yang sudah memiliki jurnal tidak dapat dihapus. Gunakan reversal jurnal.']);
        $prepaidExpense->delete();
        return response()->json(['success' => true]);
    }

    public function schedule(Request $request, PrepaidExpense $prepaidExpense)
    {
        $this->ensureScope($request, $prepaidExpense);
        $posted = $prepaidExpense->amortizations()->get()->keyBy('period');
        $monthly = round((float) $prepaidExpense->total_amount / $prepaidExpense->periods, 2);
        return response()->json(['success' => true, 'data' => collect(range(1, $prepaidExpense->periods))->map(function ($n) use ($prepaidExpense, $posted, $monthly) {
            $record = $posted->get($n);
            return ['period' => $n, 'date' => $record?->amortization_date?->toDateString() ?: $prepaidExpense->start_date->copy()->addMonths($n - 1)->toDateString(), 'amount' => $record?->amount ?: ($n === $prepaidExpense->periods ? round((float) $prepaidExpense->total_amount - ($monthly * ($n - 1)), 2) : $monthly), 'status' => $record ? 'posted' : 'scheduled', 'journal_id' => $record?->journal_id];
        })->values()]);
    }

    private function validatedPayload(Request $request, ?PrepaidExpense $row = null): array
    {
        $data = $request->validate(['reference' => 'required|string|max:80|unique:prepaid_expenses,reference'.($row ? ','.$row->id : ''), 'description' => 'required|string|max:255', 'start_date' => 'required|date', 'periods' => 'required|integer|min:1|max:120', 'total_amount' => 'required|numeric|min:0.01', 'prepaid_account_id' => 'required|exists:chart_of_accounts,id', 'expense_account_id' => 'required|exists:chart_of_accounts,id', 'project_id' => 'nullable|exists:projects,id', 'budget_line_id' => 'nullable|exists:budget_lines,id', 'bank_account_id' => 'nullable|exists:bank_accounts,id', 'status' => 'nullable|in:active,completed,on_hold']);
        $prepaidAccount = ChartOfAccount::find($data['prepaid_account_id']);
        $expenseAccount = ChartOfAccount::find($data['expense_account_id']);
        if ($prepaidAccount?->account_type !== 'asset') throw ValidationException::withMessages(['prepaid_account_id' => 'Akun prepaid harus bertipe asset.']);
        if ($expenseAccount?->account_type !== 'expense') throw ValidationException::withMessages(['expense_account_id' => 'Akun amortisasi harus bertipe expense.']);
        if (! empty($data['project_id'])) {
            $project = Project::withoutGlobalScope('fiscal_year_context')->with('grantAgreementUnscoped')->findOrFail($data['project_id']);
            $data['grant_agreement_id'] = $project->grant_agreement_id;
            $data['donor_id'] = $project->grantAgreementUnscoped?->donor_id;
            $data['program_id'] = $project->program_id;
            $data['relation_no'] = $project->relation_no ?: $project->grantAgreementUnscoped?->relation_no;
            $data['bank_account_id'] ??= $project->bank_account_id ?: $project->grantAgreementUnscoped?->bank_account_id;
        } elseif (array_key_exists('project_id', $data)) {
            $data['grant_agreement_id'] = null;
            $data['donor_id'] = null;
            $data['program_id'] = null;
            $data['relation_no'] = null;
        }
        if (! empty($data['budget_line_id'])) {
            $budget = BudgetLine::withoutGlobalScope('fiscal_year_context')->findOrFail($data['budget_line_id']);
            if (! empty($data['project_id']) && $budget->project_id && (int) $budget->project_id !== (int) $data['project_id']) throw ValidationException::withMessages(['budget_line_id' => 'Budget line harus berasal dari project yang sama.']);
            if ($budget->gl_account_id && (int) $budget->gl_account_id !== (int) $data['expense_account_id']) throw ValidationException::withMessages(['expense_account_id' => 'Akun beban harus sama dengan GL account pada budget line.']);
        }
        return $data;
    }

    private function dimensions(PrepaidExpense $row): array
    {
        return ['project_id' => $row->project_id, 'grant_agreement_id' => $row->grant_agreement_id, 'donor_id' => $row->donor_id, 'program_id' => $row->program_id, 'relation_no' => $row->relation_no];
    }

    private function ensureScope(Request $request, PrepaidExpense $prepaidExpense): void
    {
        if (app(DataScopeService::class)->canAccessAll($request->user())) return;
        $projectIds = app(DataScopeService::class)->accessibleProjectIds($request->user());
        abort_unless((int) $prepaidExpense->created_by === (int) $request->user()->id || in_array((int) $prepaidExpense->project_id, array_map('intval', $projectIds), true), 403, 'Anda tidak memiliki akses ke prepaid expense ini.');
    }
}
