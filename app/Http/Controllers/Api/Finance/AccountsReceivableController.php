<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Finance\BankTransaction;
use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\Payment;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AccountsReceivableController extends Controller
{
    private array $with = ['customer:id,code,name', 'project:id,code,name,program_id', 'program:id,code,name', 'donor:id,code,name', 'lines.revenueAccount:id,code,name'];

    public function customers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'email']),
        ]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', 'unique:customers,code'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        $customer = Customer::create([
            ...$data,
            'code' => $data['code'] ?? 'CUST-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'message' => 'Customer berhasil dibuat.', 'data' => $customer], Response::HTTP_CREATED);
    }

    public function invoices(): JsonResponse
    {
        $invoices = CustomerInvoice::query()->with($this->with)->latest('id')->get();

        return response()->json(['success' => true, 'data' => $invoices->map(fn (CustomerInvoice $invoice) => $this->formatInvoice($invoice))]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $payload = $this->validateInvoice($request);

        $invoice = DB::transaction(function () use ($payload) {
            $invoice = CustomerInvoice::create([
                'invoice_number' => $payload['invoice_number'] ?? $this->nextInvoiceNumber(),
                'customer_id' => $payload['customer_id'],
                'project_id' => $payload['project_id'] ?? null,
                'donor_id' => $payload['donor_id'] ?? null,
                'program_id' => $payload['program_id'] ?? null,
                'invoice_date' => $payload['invoice_date'],
                'due_date' => $payload['due_date'] ?? null,
                'currency_code' => $payload['currency_code'] ?? 'IDR',
                'exchange_rate' => $payload['exchange_rate'] ?? 1,
                'description' => $payload['description'] ?? null,
                'status' => 'draft',
                'total_amount' => collect($payload['lines'])->sum('total_amount'),
            ]);
            $this->syncLines($invoice, $payload['lines']);

            return $invoice->fresh($this->with);
        });

        return response()->json(['success' => true, 'message' => 'Customer invoice berhasil dibuat.', 'data' => $this->formatInvoice($invoice)], Response::HTTP_CREATED);
    }

    public function postInvoice(CustomerInvoice $customerInvoice): JsonResponse
    {
        if ($customerInvoice->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Customer invoice hanya bisa diposting dari draft.']);
        }

        $arAccount = ChartOfAccount::query()->where('account_type', 'asset')->where('is_header', false)->where(function ($query) {
            $query->where('name', 'like', '%receivable%')->orWhere('name', 'like', '%piutang%');
        })->first() ?? ChartOfAccount::query()->where('account_type', 'asset')->where('is_header', false)->first();
        if (! $arAccount) {
            throw ValidationException::withMessages(['account' => 'COA asset/AR belum tersedia.']);
        }

        $invoice = DB::transaction(function () use ($customerInvoice, $arAccount) {
            $journal = Journal::create([
                'journal_number' => 'AR-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $customerInvoice->invoice_date,
                'journal_type' => 'accrual',
                'reference' => $customerInvoice->invoice_number,
                'description' => 'AR invoice '.$customerInvoice->invoice_number,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);
            $journal->lines()->create(['account_id' => $arAccount->id, 'line_description' => 'Accounts receivable '.$customerInvoice->invoice_number, 'debit' => $customerInvoice->total_amount, 'credit' => 0, 'line_order' => 1]);

            foreach ($customerInvoice->lines as $index => $line) {
                $revenue = $line->revenueAccount ?: ChartOfAccount::query()->where('account_type', 'revenue')->where('is_header', false)->first();
                if (! $revenue) {
                    throw ValidationException::withMessages(['account' => 'COA revenue belum tersedia.']);
                }
                $journal->lines()->create([
                    'account_id' => $revenue->id,
                    'budget_line_id' => $line->budget_line_id,
                    'line_description' => $line->description,
                    'debit' => 0,
                    'credit' => $line->total_amount,
                    'line_order' => $index + 2,
                ]);
            }

            $customerInvoice->update([
                'status' => 'posted',
                'journal_id' => $journal->id,
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);

            return $customerInvoice->fresh($this->with);
        });

        return response()->json(['success' => true, 'message' => 'Customer invoice berhasil diposting ke AR.', 'data' => $this->formatInvoice($invoice)]);
    }

    public function receivePayment(Request $request, CustomerInvoice $customerInvoice): JsonResponse
    {
        if (! in_array($customerInvoice->status, ['posted', 'partially_received'], true)) {
            throw ValidationException::withMessages(['status' => 'Invoice harus posted sebelum receive payment.']);
        }

        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $outstanding = round((float) $customerInvoice->total_amount - (float) $customerInvoice->received_amount, 2);
        if ((float) $data['amount'] > $outstanding) {
            throw ValidationException::withMessages(['amount' => 'Receipt melebihi outstanding AR.']);
        }

        $bank = BankAccount::findOrFail($data['bank_account_id']);
        if (! $bank->gl_account_id) {
            throw ValidationException::withMessages(['bank_account_id' => 'Bank account belum memiliki GL account.']);
        }
        $arAccount = ChartOfAccount::query()->where('account_type', 'asset')->where('is_header', false)->where(function ($query) {
            $query->where('name', 'like', '%receivable%')->orWhere('name', 'like', '%piutang%');
        })->first() ?? ChartOfAccount::query()->where('account_type', 'asset')->where('is_header', false)->first();
        if (! $arAccount) {
            throw ValidationException::withMessages(['account' => 'COA asset/AR belum tersedia.']);
        }

        $payment = DB::transaction(function () use ($customerInvoice, $data, $bank, $arAccount) {
            $journal = Journal::create([
                'journal_number' => 'RCV-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $data['payment_date'],
                'journal_type' => 'manual',
                'reference' => $data['reference'] ?? $customerInvoice->invoice_number,
                'description' => 'Receipt '.$customerInvoice->invoice_number,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);
            $journal->lines()->create(['account_id' => $bank->gl_account_id, 'line_description' => 'Bank receipt', 'debit' => $data['amount'], 'credit' => 0, 'line_order' => 1]);
            $journal->lines()->create(['account_id' => $arAccount->id, 'line_description' => 'AR receipt', 'debit' => 0, 'credit' => $data['amount'], 'line_order' => 2]);

            $payment = Payment::create([
                'payment_number' => 'RCV-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'customer_invoice_id' => $customerInvoice->id,
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
                'description' => 'Receipt '.$customerInvoice->invoice_number,
                'debit' => $data['amount'],
                'credit' => 0,
                'status' => 'matched',
            ]);

            $received = round((float) $customerInvoice->received_amount + (float) $data['amount'], 2);
            $customerInvoice->update([
                'received_amount' => $received,
                'status' => $received >= (float) $customerInvoice->total_amount ? 'received' : 'partially_received',
            ]);

            return $payment->load(['customerInvoice:id,invoice_number', 'bankAccount:id,bank_name,account_number']);
        });

        return response()->json(['success' => true, 'message' => 'Receipt berhasil dicatat dan bank transaction dibuat.', 'data' => $payment], Response::HTTP_CREATED);
    }

    private function validateInvoice(Request $request): array
    {
        return $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:50', 'unique:customer_invoices,invoice_number'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.revenue_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.budget_line_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function syncLines(CustomerInvoice $invoice, array $lines): void
    {
        $invoice->lines()->delete();
        foreach ($lines as $index => $line) {
            $quantity = (float) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];
            $invoice->lines()->create([
                'revenue_account_id' => $line['revenue_account_id'] ?? null,
                'budget_line_id' => $line['budget_line_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => round($quantity * $unitPrice, 2),
                'line_order' => $index + 1,
            ]);
        }
        $invoice->update(['total_amount' => $invoice->lines()->sum('total_amount')]);
    }

    private function nextInvoiceNumber(): string
    {
        return 'AR-'.now()->format('YmdHis').'-'.random_int(100, 999);
    }

    private function formatInvoice(CustomerInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer' => $invoice->customer ? ['id' => $invoice->customer->id, 'code' => $invoice->customer->code, 'name' => $invoice->customer->name] : null,
            'project' => $invoice->project ? ['id' => $invoice->project->id, 'code' => $invoice->project->code, 'name' => $invoice->project->name] : null,
            'donor' => $invoice->donor ? ['id' => $invoice->donor->id, 'code' => $invoice->donor->code, 'name' => $invoice->donor->name] : null,
            'program' => $invoice->program ? ['id' => $invoice->program->id, 'code' => $invoice->program->code, 'name' => $invoice->program->name] : null,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'currency_code' => $invoice->currency_code,
            'status' => $invoice->status,
            'total_amount' => $invoice->total_amount,
            'received_amount' => $invoice->received_amount,
            'outstanding_amount' => round((float) $invoice->total_amount - (float) $invoice->received_amount, 2),
            'description' => $invoice->description,
            'lines' => $invoice->lines->map(fn ($line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'total_amount' => $line->total_amount,
                'revenue_account' => $line->revenueAccount ? ['id' => $line->revenueAccount->id, 'code' => $line->revenueAccount->code, 'name' => $line->revenueAccount->name] : null,
            ])->values(),
        ];
    }
}
