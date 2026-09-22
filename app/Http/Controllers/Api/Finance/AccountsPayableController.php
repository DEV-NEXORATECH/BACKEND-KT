<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Finance\BankTransaction;
use App\Models\Finance\Payment;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Procurement\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AccountsPayableController extends Controller
{
    public function invoices(): JsonResponse
    {
        $invoices = SupplierInvoice::with(['vendor:id,code,name', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'lines.purchaseOrderLine.budgetLine.glAccount'])
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invoices->map(fn (SupplierInvoice $invoice) => $this->formatInvoice($invoice)),
        ]);
    }

    public function postInvoice(SupplierInvoice $supplierInvoice): JsonResponse
    {
        if (! in_array($supplierInvoice->match_status, ['matched', 'partial_match'], true)) {
            throw ValidationException::withMessages(['match_status' => 'Invoice harus matched atau partial match sebelum posting AP.']);
        }
        if (in_array($supplierInvoice->status, ['posted', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => 'Invoice sudah diposting.']);
        }

        $apAccount = ChartOfAccount::query()->where('account_type', 'liability')->where('is_header', false)->first()
            ?? ChartOfAccount::query()->where('account_type', 'liability')->first();
        if (! $apAccount) {
            throw ValidationException::withMessages(['account' => 'COA liability/AP belum tersedia.']);
        }

        $invoice = DB::transaction(function () use ($supplierInvoice, $apAccount) {
            $journal = Journal::create([
                'journal_number' => 'AP-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $supplierInvoice->invoice_date,
                'journal_type' => 'accrual',
                'reference' => $supplierInvoice->invoice_number,
                'description' => 'AP posting '.$supplierInvoice->invoice_number,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);

            foreach ($supplierInvoice->lines as $line) {
                $expenseAccount = $line->purchaseOrderLine?->budgetLine?->glAccount
                    ?? ChartOfAccount::query()->where('account_type', 'expense')->where('is_header', false)->first();
                if (! $expenseAccount) {
                    throw ValidationException::withMessages(['account' => 'COA expense belum tersedia.']);
                }
                $journal->lines()->create([
                    'account_id' => $expenseAccount->id,
                    'budget_line_id' => $line->purchaseOrderLine?->budget_line_id,
                    'line_description' => $line->item_description,
                    'debit' => $line->total_amount,
                    'credit' => 0,
                    'line_order' => $line->id,
                ]);
            }
            $journal->lines()->create([
                'account_id' => $apAccount->id,
                'line_description' => 'Accounts payable '.$supplierInvoice->invoice_number,
                'debit' => 0,
                'credit' => $supplierInvoice->total_amount,
                'line_order' => 999,
            ]);

            $supplierInvoice->update([
                'status' => 'posted',
                'journal_id' => $journal->id,
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);

            return $supplierInvoice->fresh(['vendor:id,code,name', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'lines']);
        });

        return response()->json(['success' => true, 'message' => 'Supplier invoice berhasil diposting ke AP.', 'data' => $this->formatInvoice($invoice)]);
    }

    public function payInvoice(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        if (! in_array($supplierInvoice->status, ['posted', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => 'Invoice harus posted sebelum payment.']);
        }

        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $outstanding = round((float) $supplierInvoice->total_amount - (float) $supplierInvoice->paid_amount, 2);
        if ((float) $data['amount'] > $outstanding) {
            throw ValidationException::withMessages(['amount' => 'Payment melebihi outstanding AP.']);
        }

        $bank = BankAccount::findOrFail($data['bank_account_id']);
        if (! $bank->gl_account_id) {
            throw ValidationException::withMessages(['bank_account_id' => 'Bank account belum memiliki GL account.']);
        }
        $apAccount = ChartOfAccount::query()->where('account_type', 'liability')->where('is_header', false)->first();
        if (! $apAccount) {
            throw ValidationException::withMessages(['account' => 'COA liability/AP belum tersedia.']);
        }

        $payment = DB::transaction(function () use ($supplierInvoice, $data, $bank, $apAccount) {
            $journal = Journal::create([
                'journal_number' => 'PAY-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $data['payment_date'],
                'journal_type' => 'manual',
                'reference' => $data['reference'] ?? $supplierInvoice->invoice_number,
                'description' => 'Payment '.$supplierInvoice->invoice_number,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
            ]);
            $journal->lines()->create(['account_id' => $apAccount->id, 'line_description' => 'AP payment', 'debit' => $data['amount'], 'credit' => 0, 'line_order' => 1]);
            $journal->lines()->create(['account_id' => $bank->gl_account_id, 'line_description' => 'Bank payment', 'debit' => 0, 'credit' => $data['amount'], 'line_order' => 2]);

            $payment = Payment::create([
                'payment_number' => 'PAY-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'supplier_invoice_id' => $supplierInvoice->id,
                'vendor_id' => $supplierInvoice->vendor_id,
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
                'description' => 'Payment '.$supplierInvoice->invoice_number,
                'debit' => 0,
                'credit' => $data['amount'],
                'status' => 'matched',
            ]);

            $paid = round((float) $supplierInvoice->paid_amount + (float) $data['amount'], 2);
            $supplierInvoice->update([
                'paid_amount' => $paid,
                'status' => $paid >= (float) $supplierInvoice->total_amount ? 'paid' : 'posted',
            ]);

            return $payment->load(['supplierInvoice:id,invoice_number', 'bankAccount:id,bank_name,account_number']);
        });

        return response()->json(['success' => true, 'message' => 'Payment berhasil dicatat dan bank transaction dibuat.', 'data' => $payment], Response::HTTP_CREATED);
    }

    public function payments(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Payment::with(['supplierInvoice:id,invoice_number', 'bankAccount:id,bank_name,account_number'])->latest('id')->get()]);
    }

    public function bankTransactions(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => BankTransaction::with(['bankAccount:id,bank_name,account_number'])->latest('transaction_date')->latest('id')->get()]);
    }

    public function importBankTransactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($data['file']->getRealPath(), 'rb');
        $created = 0;
        $skipped = 0;
        $row = 0;
        while (($columns = fgetcsv($handle)) !== false) {
            $row++;
            if ($row === 1 && isset($columns[0]) && preg_match('/date|tanggal/i', (string) $columns[0])) continue;
            if (count($columns) < 4 || ! trim((string) $columns[0])) { $skipped++; continue; }
            try {
                $date = \Carbon\Carbon::parse(trim((string) $columns[0]))->toDateString();
                $reference = trim((string) ($columns[1] ?? '')) ?: null;
                $description = trim((string) ($columns[2] ?? '')) ?: null;
                $debit = (float) str_replace([',', ' '], ['', ''], (string) ($columns[3] ?? 0));
                $credit = (float) str_replace([',', ' '], ['', ''], (string) ($columns[4] ?? 0));
                $exists = BankTransaction::where('bank_account_id', $data['bank_account_id'])->whereDate('transaction_date', $date)->where('reference', $reference)->where('debit', $debit)->where('credit', $credit)->exists();
                if ($exists) { $skipped++; continue; }
                BankTransaction::create(['bank_account_id' => $data['bank_account_id'], 'transaction_date' => $date, 'reference' => $reference, 'description' => $description, 'debit' => $debit, 'credit' => $credit, 'status' => 'unmatched']);
                $created++;
            } catch (\Throwable) { $skipped++; }
        }
        fclose($handle);
        return response()->json(['success' => true, 'message' => "{$created} transaksi diimport, {$skipped} dilewati.", 'data' => ['created' => $created, 'skipped' => $skipped]]);
    }

    public function reconcileBankTransaction(Request $request, BankTransaction $bankTransaction): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:matched,excluded,reconciled,unmatched']]);
        $bankTransaction->update(['status' => $data['status']]);
        return response()->json(['success' => true, 'message' => 'Status rekonsiliasi diperbarui.', 'data' => $bankTransaction->fresh(['bankAccount:id,bank_name,account_number'])]);
    }

    private function formatInvoice(SupplierInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'vendor_name' => $invoice->vendor?->name,
            'po_number' => $invoice->purchaseOrder?->po_number,
            'grn_number' => $invoice->goodsReceipt?->grn_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'match_status' => $invoice->match_status,
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'outstanding_amount' => round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2),
        ];
    }
}
