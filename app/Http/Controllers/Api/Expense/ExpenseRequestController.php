<?php

namespace App\Http\Controllers\Api\Expense;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Expense\ExpenseRequest;
use App\Models\Finance\BankTransaction;
use App\Models\Finance\Payment;
use App\Models\Finance\TaxTransaction;
use App\Models\Master\BankAccount;
use App\Models\Notification;
use App\Models\Master\ChartOfAccount;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Budget\BudgetMonitoringService;
use App\Services\Settings\SystemPolicyService;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ExpenseRequestController extends Controller
{
    private array $with = ['requester:id,name,email', 'donor:id,code,name', 'grantAgreement', 'program:id,code,name', 'project:id,code,name', 'department:id,code,name', 'fundingSource:id,code,name', 'documentType:id,code,name', 'tax:id,code,name', 'lines.expenseCategory:id,code,name,default_gl_account_id', 'lines.budgetLine:id,line_code,description,gl_account_id', 'lines.budgetLine.glAccount:id,code,name'];

    public function index(Request $request): JsonResponse
    {
        $items = ExpenseRequest::with($this->with)
            ->when(! $this->canAccessAll($request), fn ($query) => $query->where('requester_id', $request->user()->id))
            ->when($request->filled('donor_id'), fn ($query) => $query->where('donor_id', $request->integer('donor_id')))
            ->when($request->filled('grant_agreement_id'), fn ($query) => $query->where('grant_agreement_id', $request->integer('grant_agreement_id')))
            ->when($request->filled('program_id'), fn ($query) => $query->where('program_id', $request->integer('program_id')))
            ->when($request->filled('project_id'), fn ($query) => $query->where('project_id', $request->integer('project_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('id')
            ->get();

        return response()->json(['success' => true, 'data' => $items->map(fn (ExpenseRequest $expense) => $this->format($expense))]);
    }

    public function show(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $this->authorizeScope($request, $expenseRequest);

        return response()->json(['success' => true, 'data' => $this->format($expenseRequest->load($this->with))]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $query = ExpenseRequest::query()
            ->when(! $this->canAccessAll($request), fn ($builder) => $builder->where('requester_id', $request->user()->id))
            ->when($request->filled('donor_id'), fn ($builder) => $builder->where('donor_id', $request->integer('donor_id')))
            ->when($request->filled('project_id'), fn ($builder) => $builder->where('project_id', $request->integer('project_id')))
            ->when($request->filled('start_date'), fn ($builder) => $builder->whereDate('request_date', '>=', $request->date('start_date')))
            ->when($request->filled('end_date'), fn ($builder) => $builder->whereDate('request_date', '<=', $request->date('end_date')));

        $amount = fn ($builder) => (float) $builder->withSum('lines', 'amount')->get()->sum('lines_sum_amount');
        $cashAdvances = (clone $query)->where('expense_type', 'cash_advance');
        $openAdvances = (clone $cashAdvances)->where(function ($builder) {
            $builder->whereNull('settlement_status')->orWhere('settlement_status', '!=', 'settled');
        });
        $overdue = (clone $openAdvances)->whereIn('status', ['posted', 'paid'])
            ->whereNotNull('settlement_due_date')->whereDate('settlement_due_date', '<', now()->toDateString());

        return response()->json([
            'success' => true,
            'filters' => $request->only(['donor_id', 'project_id', 'start_date', 'end_date']),
            'summary' => [
                'total_requests' => (clone $query)->count(),
                'pending_approval' => (clone $query)->whereIn('status', ['submitted', 'verified'])->count(),
                'total_spending' => $amount((clone $query)->whereIn('status', ['posted', 'paid'])),
                'outstanding_advances' => $amount($openAdvances),
                'overdue_settlement' => $overdue->count(),
                'overdue_settlement_amount' => $amount($overdue),
                'reimbursement_requests' => (clone $query)->where('expense_type', 'reimbursement')->count(),
                'cash_advance_requests' => $cashAdvances->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        if (! $this->canAccessAll($request)) {
            $payload['requester_id'] = $request->user()->id;
        }

        $expense = DB::transaction(function () use ($payload, $request) {
            $lines = $payload['lines'];
            unset($payload['lines']);

            $expense = ExpenseRequest::create([
                ...$payload,
                'request_number' => $payload['request_number'] ?? 'EXP-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'requester_id' => $payload['requester_id'] ?? $request->user()->id,
                'status' => 'draft',
            ]);

            foreach ($lines as $index => $line) {
                $expense->lines()->create([...$line, 'line_order' => $index + 1]);
            }

            return $expense->load($this->with);
        });

        return response()->json(['success' => true, 'message' => 'Expense request berhasil dibuat.', 'data' => $this->format($expense)], Response::HTTP_CREATED);
    }

    public function submit(Request $request, ExpenseRequest $expenseRequest, SystemPolicyService $policies, ApprovalWorkflowService $workflow): JsonResponse
    {
        $this->authorizeScope($request, $expenseRequest);
        $this->ensureReceiptIfRequired($expenseRequest, $policies);
        $workflow->start('expense', $expenseRequest, (float) $expenseRequest->total_amount, $request->user()->id);

        return $this->transition($expenseRequest, 'draft', 'submitted', [
            'submitted_by' => request()->user()->id,
            'submitted_at' => now(),
        ], 'Expense request berhasil disubmit.');
    }

    public function uploadAttachment(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $this->authorizeScope($request, $expenseRequest);
        if ($expenseRequest->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Lampiran hanya dapat ditambahkan saat expense masih draft.']);
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx'],
        ]);
        $file = $data['file'];
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$extension;
        $path = $file->storeAs("expense-attachments/{$expenseRequest->id}", $filename, 'local');
        $attachments = array_values(array_unique([...(array) $expenseRequest->attachments, $path]));
        $expenseRequest->update(['attachments' => $attachments]);

        return response()->json(['success' => true, 'message' => 'Lampiran berhasil diunggah.', 'data' => $this->format($expenseRequest->fresh($this->with))], Response::HTTP_CREATED);
    }

    public function downloadAttachment(Request $request, ExpenseRequest $expenseRequest, int $index): StreamedResponse
    {
        $this->authorizeScope($request, $expenseRequest);
        $path = $expenseRequest->attachments[$index] ?? null;
        $prefix = "expense-attachments/{$expenseRequest->id}/";
        if (! is_string($path) || ! str_starts_with($path, $prefix) || ! Storage::disk('local')->exists($path)) {
            abort(Response::HTTP_NOT_FOUND, 'Lampiran tidak ditemukan.');
        }

        $filename = basename($path);
        $downloadName = str_contains($filename, '_') ? Str::after($filename, '_') : $filename;

        return Storage::disk('local')->download($path, $downloadName);
    }

    public function approve(Request $request, ExpenseRequest $expenseRequest, BudgetMonitoringService $budgetService, SystemPolicyService $policies, ApprovalWorkflowService $workflow): JsonResponse
    {
        if ($expenseRequest->status !== 'verified') {
            throw ValidationException::withMessages(['status' => 'Expense harus diverifikasi Finance untuk approval.']);
        }
        $approval = $workflow->approve('expense', $expenseRequest, $request->user(), $request->input('notes'));
        if ($approval['managed'] && ! $approval['completed']) {
            return response()->json(['success' => true, 'message' => "Approval tahap selesai. Menunggu approver level {$approval['next_level']}.", 'data' => $this->format($expenseRequest->fresh($this->with))]);
        }

        $aggregated = $expenseRequest->lines
            ->where('budget_line_id')
            ->groupBy('budget_line_id')
            ->map(fn ($lines) => round((float) $lines->sum('amount'), 2));

        foreach ($aggregated as $budgetLineId => $amount) {
            $validation = $budgetService->validate($budgetLineId, $amount);
            if (! $validation['allowed'] && $policies->blocksOverBudget()) {
                throw ValidationException::withMessages(['budget_line_id' => "Budget line {$budgetLineId}: {$validation['message']}"]);
            }
        }

        $expense = $this->transition($expenseRequest, 'verified', 'approved', [
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
            'decision_notes' => request('notes'),
        ], 'Expense request berhasil diapprove.');

        DB::transaction(function () use ($expenseRequest, $budgetService, $request) {
            foreach ($expenseRequest->lines as $line) {
                if (! $line->budget_line_id) {
                    continue;
                }
                $budgetService->commit(
                    $line->budget_line_id,
                    ExpenseRequest::class,
                    $expenseRequest->id,
                    (float) $line->amount,
                    $expenseRequest->request_number,
                    $request->user()->id,
                );
            }
        });

        return $expense;
    }

    public function verify(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        if ($expenseRequest->status !== 'submitted') throw ValidationException::withMessages(['status' => 'Expense harus submitted untuk verifikasi.']);
        $expenseRequest->update(['status' => 'verified', 'verified_by' => $request->user()->id, 'verified_at' => now(), 'decision_notes' => $request->input('notes')]);
        return response()->json(['success' => true, 'message' => 'Expense berhasil diverifikasi Finance.', 'data' => $this->format($expenseRequest->fresh($this->with))]);
    }

    public function reject(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        if (! in_array($expenseRequest->status, ['submitted', 'approved'], true)) {
            throw ValidationException::withMessages(['status' => 'Expense tidak dapat direject dari status saat ini.']);
        }
app(ApprovalWorkflowService::class)->reject('expense', $expenseRequest, $request->user(), $data['notes']);

        $expenseRequest->update([
            'status' => 'rejected',
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'decision_notes' => $data['notes'],
        ]);
        app(\App\Services\Budget\BudgetMonitoringService::class)
            ->releaseForSource(ExpenseRequest::class, $expenseRequest->id, 'released', $request->user()->id);
        Notification::create(['user_id' => null, 'title' => 'Expense ditolak', 'message' => "{$expenseRequest->request_number}: {$data['notes']}", 'type' => 'alert', 'action_url' => '/expenses-approvals/expenses']);

        return response()->json(['success' => true, 'message' => 'Expense request berhasil direject.', 'data' => $this->format($expenseRequest->fresh($this->with))]);
    }

    public function resubmit(Request $request, ExpenseRequest $expenseRequest, SystemPolicyService $policies): JsonResponse
    {
        $this->authorizeScope($request, $expenseRequest);
        $this->ensureReceiptIfRequired($expenseRequest, $policies);

        return $this->transition($expenseRequest, 'rejected', 'submitted', [
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ], 'Expense request berhasil diajukan kembali.');
    }

    public function post(ExpenseRequest $expenseRequest): JsonResponse
    {
        app(AccountingPeriodService::class)->ensureOpen($expenseRequest->request_date->toDateString(), 'request_date');
        if ($expenseRequest->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Expense harus approved sebelum posting.']);
        }

        $payable = ChartOfAccount::where('account_type', 'liability')->where('is_header', false)->first();
        if (! $payable) {
            throw ValidationException::withMessages(['account' => 'COA liability/payable belum tersedia.']);
        }

        $expense = DB::transaction(function () use ($expenseRequest, $payable) {
            $journal = Journal::create([
                'journal_number' => 'EXP-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $expenseRequest->request_date,
                'journal_type' => 'accrual',
                'reference' => $expenseRequest->external_request_id ?: $expenseRequest->request_number,
                'description' => $expenseRequest->description,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);

            $lineOrder = 1;
            foreach ($expenseRequest->lines as $line) {
                $account = $line->budgetLine?->glAccount
                    ?? $line->expenseCategory?->defaultGlAccount
                    ?? ChartOfAccount::where('account_type', 'expense')->where('is_header', false)->first();
                if (! $account) {
                    throw ValidationException::withMessages(['account' => 'COA expense belum tersedia.']);
                }
                $journal->lines()->create([
                    'account_id' => $account->id,
                    'project_id' => $expenseRequest->project_id,
                    'budget_line_id' => $line->budget_line_id,
                    'department_id' => $expenseRequest->department_id,
                    'line_description' => $line->description,
                    'debit' => $line->amount,
                    'credit' => 0,
                    'line_order' => $lineOrder++,
                ]);
            }
            $journal->lines()->create([
                'account_id' => $payable->id,
                'line_description' => 'Expense payable '.$expenseRequest->request_number,
                'debit' => 0,
                'credit' => $expenseRequest->total_amount,
                'line_order' => 999,
            ]);

            $expenseRequest->update(['status' => 'posted', 'journal_id' => $journal->id]);

            if ($expenseRequest->tax_id) {
                $tax = $expenseRequest->tax;
                $taxableAmount = (float) $expenseRequest->total_amount;
                $taxAmount = round($taxableAmount * ((float) $tax->rate_percent / 100), 2);
                TaxTransaction::firstOrCreate(
                    ['source_type' => ExpenseRequest::class, 'source_id' => $expenseRequest->id],
                    [
                        'tax_id' => $tax->id,
                        'transaction_type' => 'expense',
                        'reference' => $expenseRequest->request_number,
                        'transaction_date' => $expenseRequest->request_date,
                        'direction' => 'purchase',
                        'taxable_amount' => $taxableAmount,
                        'tax_rate' => $tax->rate_percent,
                        'tax_amount' => $taxAmount,
                        'net_amount' => $taxableAmount,
                        'gross_amount' => round($taxableAmount + $taxAmount, 2),
                        'status' => 'draft',
                        'created_by' => request()->user()->id,
                    ]
                );
            }

            app(\App\Services\Budget\BudgetMonitoringService::class)
                ->releaseForSource(ExpenseRequest::class, $expenseRequest->id, 'converted', request()->user()->id);

            return $expenseRequest->fresh($this->with);
        });

        return response()->json(['success' => true, 'message' => 'Expense berhasil diposting ke accounting.', 'data' => $this->format($expense)]);
    }

    public function pay(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        if (! in_array($expenseRequest->status, ['posted', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => 'Expense harus posted sebelum payment.']);
        }

        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        app(AccountingPeriodService::class)->ensureOpen($data['payment_date'], 'payment_date');

        $outstanding = round((float) $expenseRequest->total_amount - (float) $expenseRequest->paid_amount, 2);
        if ((float) $data['amount'] > $outstanding) {
            throw ValidationException::withMessages(['amount' => 'Payment melebihi outstanding expense.']);
        }

        $bank = BankAccount::findOrFail($data['bank_account_id']);
        if (! $bank->gl_account_id) {
            throw ValidationException::withMessages(['bank_account_id' => 'Bank account belum memiliki GL account.']);
        }
        $payable = ChartOfAccount::where('account_type', 'liability')->where('is_header', false)->first();
        if (! $payable) {
            throw ValidationException::withMessages(['account' => 'COA liability/payable belum tersedia.']);
        }

        $expense = DB::transaction(function () use ($expenseRequest, $data, $bank, $payable) {
            $journal = Journal::create([
                'journal_number' => 'EXPPAY-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $data['payment_date'],
                'journal_type' => 'manual',
                'reference' => $data['reference'] ?? $expenseRequest->request_number,
                'description' => 'Expense payment '.$expenseRequest->request_number,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);
            $journal->lines()->create(['account_id' => $payable->id, 'line_description' => 'Expense payable payment', 'debit' => $data['amount'], 'credit' => 0, 'line_order' => 1]);
            $journal->lines()->create(['account_id' => $bank->gl_account_id, 'line_description' => 'Bank payment', 'debit' => 0, 'credit' => $data['amount'], 'line_order' => 2]);

            $payment = Payment::create([
                'payment_number' => 'EXPPAY-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'expense_request_id' => $expenseRequest->id,
                'bank_account_id' => $bank->id,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'reference' => $data['reference'] ?? null,
                'status' => 'paid',
                'journal_id' => $journal->id,
                'created_by' => request()->user()->id,
            ]);

            BankTransaction::create([
                'bank_account_id' => $bank->id,
                'payment_id' => $payment->id,
                'transaction_date' => $data['payment_date'],
                'reference' => $payment->payment_number,
                'description' => 'Expense payment '.$expenseRequest->request_number,
                'debit' => 0,
                'credit' => $data['amount'],
                'status' => 'matched',
            ]);

            $paid = round((float) $expenseRequest->paid_amount + (float) $data['amount'], 2);
            $expenseRequest->update(['paid_amount' => $paid, 'status' => $paid >= (float) $expenseRequest->total_amount ? 'paid' : 'posted']);

            return $expenseRequest->fresh($this->with);
        });

        return response()->json(['success' => true, 'message' => 'Expense payment berhasil dicatat.', 'data' => $this->format($expense)], Response::HTTP_CREATED);
    }

    public function settle(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $this->authorizeScope($request, $expenseRequest);
        if ($expenseRequest->expense_type !== 'cash_advance') {
            throw ValidationException::withMessages(['expense_type' => 'Hanya Cash Advance yang dapat diselesaikan.']);
        }
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);
        $settled = round((float) $expenseRequest->settled_amount + (float) $data['amount'], 2);
        if ($settled > (float) $expenseRequest->total_amount) {
            throw ValidationException::withMessages(['amount' => 'Settlement melebihi nilai Cash Advance.']);
        }
        $status = $settled >= (float) $expenseRequest->total_amount ? 'settled' : 'partially_settled';
        $expenseRequest->update(['settled_amount' => $settled, 'settlement_status' => $status]);
        Notification::create(['user_id' => null, 'title' => 'Cash Advance diselesaikan', 'message' => "{$expenseRequest->request_number}: settlement {$settled}.", 'type' => 'info', 'action_url' => '/expenses-approvals/cash-advance']);
        return response()->json(['success' => true, 'message' => 'Settlement Cash Advance berhasil dicatat.', 'data' => $this->format($expenseRequest->fresh($this->with))]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'request_number' => ['nullable', 'string', 'max:40', 'unique:expense_requests,request_number'],
            'external_request_id' => ['nullable', 'string', 'max:80', 'unique:expense_requests,external_request_id'],
            'requester_id' => ['nullable', 'integer', 'exists:users,id'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'grant_agreement_id' => ['nullable', 'integer', 'exists:grant_agreements,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'funding_source_id' => ['nullable', 'integer', 'exists:funding_sources,id'],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'max:255'],
            'expense_type' => ['required', 'in:reimbursement,supplier_payment,loan,cash_advance,settlement_advance'],
            'request_date' => ['required', 'date'],
            'settlement_due_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'description' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'lines.*.budget_line_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);
    }

    private function canAccessAll(Request $request): bool
    {
        return (bool) $request->user()?->hasAnyPermission([
            'expense.approve',
            'expense.post',
            'expense.pay',
        ]);
    }

    private function authorizeScope(Request $request, ExpenseRequest $expenseRequest): void
    {
        if (! $this->canAccessAll($request) && (int) $expenseRequest->requester_id !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mengakses expense request user lain.');
        }
    }

    private function ensureReceiptIfRequired(ExpenseRequest $expenseRequest, SystemPolicyService $policies): void
    {
        if ($policies->requiresExpenseReceipt() && empty($expenseRequest->attachments)) {
            throw ValidationException::withMessages([
                'attachments' => 'Lampiran/receipt wajib diunggah sebelum expense diajukan.',
            ]);
        }
    }

    private function transition(ExpenseRequest $expenseRequest, string $from, string $to, array $extra, string $message): JsonResponse
    {
        if ($expenseRequest->status !== $from) {
            throw ValidationException::withMessages(['status' => "Expense harus {$from} untuk aksi ini."]);
        }

        $expenseRequest->update(['status' => $to, ...$extra]);
        $labels = ['submitted' => 'diajukan', 'approved' => 'disetujui', 'posted' => 'diposting', 'rejected' => 'ditolak'];
        $label = $labels[$to] ?? $to;
        Notification::create(['user_id' => null, 'title' => 'Perubahan status expense', 'message' => "{$expenseRequest->request_number} berhasil {$label}.", 'type' => 'info', 'action_url' => '/expenses-approvals/expenses']);

        return response()->json(['success' => true, 'message' => $message, 'data' => $this->format($expenseRequest->fresh($this->with))]);
    }

    private function format(ExpenseRequest $expense): array
    {
        $expense->loadMissing($this->with);
        return [
            'id' => $expense->id,
            'request_number' => $expense->request_number,
            'external_request_id' => $expense->external_request_id,
            'requester_name' => $expense->requester?->name,
            'donor' => $expense->donor ? ['id' => $expense->donor->id, 'code' => $expense->donor->code, 'name' => $expense->donor->name] : null,
            'grant' => $expense->grantAgreement ? ['id' => $expense->grantAgreement->id, 'number' => $expense->grantAgreement->grant_no ?? $expense->grantAgreement->agreement_number ?? null, 'title' => $expense->grantAgreement->agreement_name ?? $expense->grantAgreement->title ?? null] : null,
            'program' => $expense->program ? ['id' => $expense->program->id, 'code' => $expense->program->code, 'name' => $expense->program->name] : null,
            'funding_source' => $expense->fundingSource ? ['id' => $expense->fundingSource->id, 'code' => $expense->fundingSource->code, 'name' => $expense->fundingSource->name] : null,
            'document_type' => $expense->documentType ? ['id' => $expense->documentType->id, 'code' => $expense->documentType->code, 'name' => $expense->documentType->name] : null,
            'tax' => $expense->tax ? ['id' => $expense->tax->id, 'code' => $expense->tax->code, 'name' => $expense->tax->name] : null,
            'attachments' => $expense->attachments ?? [],
            'project_id' => $expense->project_id,
            'project_name' => $expense->project?->name,
            'department_name' => $expense->department?->name,
            'expense_type' => $expense->expense_type,
            'request_date' => $expense->request_date?->toDateString(),
            'currency_code' => $expense->currency_code,
            'exchange_rate' => $expense->exchange_rate,
            'description' => $expense->description,
            'status' => $expense->status,
            'total_amount' => $expense->total_amount,
            'paid_amount' => $expense->paid_amount,
            'outstanding_amount' => max(0, round((float) $expense->total_amount - (float) $expense->paid_amount, 2)),
            'settled_amount' => $expense->settled_amount,
            'settlement_status' => $expense->settlement_status,
            'settlement_due_date' => $expense->settlement_due_date?->toDateString(),
            'decision_notes' => $expense->decision_notes,
            'submitted_at' => $expense->submitted_at?->toISOString(),
            'rejected_at' => $expense->rejected_at?->toISOString(),
            'rejected_by' => $expense->rejected_by,
            'approved_at' => $expense->approved_at?->toISOString(),
            'approved_by' => $expense->approved_by,
            'payment_status' => ((float) $expense->paid_amount >= (float) $expense->total_amount && (float) $expense->total_amount > 0) ? 'paid' : (((float) $expense->paid_amount > 0) ? 'partial' : 'unpaid'),
            'lines' => $expense->lines->map(fn ($line) => [
                'id' => $line->id,
                'expense_category_id' => $line->expense_category_id,
                'expense_category_name' => $line->expenseCategory?->name,
                'budget_line_id' => $line->budget_line_id,
                'budget_line_code' => $line->budgetLine?->line_code,
                'description' => $line->description,
                'amount' => $line->amount,
            ])->values(),
        ];
    }
}
