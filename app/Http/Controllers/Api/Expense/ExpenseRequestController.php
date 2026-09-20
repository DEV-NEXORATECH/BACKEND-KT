<?php

namespace App\Http\Controllers\Api\Expense;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Expense\ExpenseRequest;
use App\Models\Finance\BankTransaction;
use App\Models\Finance\Payment;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Services\Budget\BudgetMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ExpenseRequestController extends Controller
{
    private array $with = ['requester:id,name,email', 'project:id,code,name', 'department:id,code,name', 'lines.expenseCategory:id,code,name,default_gl_account_id', 'lines.budgetLine:id,line_code,description,gl_account_id', 'lines.budgetLine.glAccount:id,code,name'];

    public function index(Request $request): JsonResponse
    {
        $items = ExpenseRequest::with($this->with)
            ->when(! $this->canAccessAll($request), fn ($query) => $query->where('requester_id', $request->user()->id))
            ->latest('id')
            ->get();

        return response()->json(['success' => true, 'data' => $items->map(fn (ExpenseRequest $expense) => $this->format($expense))]);
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

    public function submit(Request $request, ExpenseRequest $expenseRequest): JsonResponse
    {
        $this->authorizeScope($request, $expenseRequest);

        return $this->transition($expenseRequest, 'draft', 'submitted', [
            'submitted_by' => request()->user()->id,
            'submitted_at' => now(),
        ], 'Expense request berhasil disubmit.');
    }

    public function approve(ExpenseRequest $expenseRequest, BudgetMonitoringService $budgetService): JsonResponse
    {
        if ($expenseRequest->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Expense harus submitted untuk approval.']);
        }

        foreach ($expenseRequest->lines as $line) {
            if ($line->budget_line_id) {
                $validation = $budgetService->validate($line->budget_line_id, (float) $line->amount);
                if (! $validation['allowed']) {
                    throw ValidationException::withMessages(['budget_line_id' => "{$line->description}: {$validation['message']}"]);
                }
            }
        }

        return $this->transition($expenseRequest, 'submitted', 'approved', [
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
            'decision_notes' => request('notes'),
        ], 'Expense request berhasil diapprove.');
    }

    public function reject(ExpenseRequest $expenseRequest): JsonResponse
    {
        if (! in_array($expenseRequest->status, ['submitted', 'approved'], true)) {
            throw ValidationException::withMessages(['status' => 'Expense tidak dapat direject dari status saat ini.']);
        }

        $expenseRequest->update([
            'status' => 'rejected',
            'rejected_by' => request()->user()->id,
            'rejected_at' => now(),
            'decision_notes' => request('notes'),
        ]);

        return response()->json(['success' => true, 'message' => 'Expense request berhasil direject.', 'data' => $this->format($expenseRequest->fresh($this->with))]);
    }

    public function post(ExpenseRequest $expenseRequest): JsonResponse
    {
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

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'request_number' => ['nullable', 'string', 'max:40', 'unique:expense_requests,request_number'],
            'external_request_id' => ['nullable', 'string', 'max:80', 'unique:expense_requests,external_request_id'],
            'requester_id' => ['nullable', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'expense_type' => ['required', 'in:reimbursement,supplier_payment,loan,cash_advance,settlement_advance'],
            'request_date' => ['required', 'date'],
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
        return $request->user()->hasAnyPermission([
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

    private function transition(ExpenseRequest $expenseRequest, string $from, string $to, array $extra, string $message): JsonResponse
    {
        if ($expenseRequest->status !== $from) {
            throw ValidationException::withMessages(['status' => "Expense harus {$from} untuk aksi ini."]);
        }

        $expenseRequest->update(['status' => $to, ...$extra]);

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
