<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\Finance\BankTransaction;
use App\Models\Finance\Payment;
use App\Models\Finance\TaxTransaction;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Tax;
use App\Models\Procurement\SupplierInvoice;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AccountsPayableController extends Controller
{
    public function invoices(Request $request): JsonResponse
    {
        $query = SupplierInvoice::with(['vendor:id,code,name,npwp', 'coa:id,code,name', 'tax:id,code,name,tax_type,rate_percent', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'lines.purchaseOrderLine.budgetLine.glAccount', 'payments:id,supplier_invoice_id,payment_number,payment_date,amount,reference']);
        
        app(\App\Services\Rbac\DataScopeService::class)->applyScope(
            $query,
            $request->user(),
            'created_by',
            null,
            null,
            []
        );

        $invoices = $query->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => $invoices->map(fn (SupplierInvoice $invoice) => $this->formatInvoice($invoice)),
        ]);
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:80', 'unique:supplier_invoices,invoice_number'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'coa_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $invoice = SupplierInvoice::create([
            ...$data,
            'status' => 'draft',
            'match_status' => 'unchecked',
            'paid_amount' => 0,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'source_type' => 'accounts_payable',
        ]);
        if ($request->hasFile('attachment')) {
            $invoice->update(['attachments' => [['name' => $request->file('attachment')->getClientOriginalName(), 'path' => $request->file('attachment')->store('ap-attachments', 'public')]]]);
        }

        return response()->json([
            'success' => true,
            'message' => 'AP invoice berhasil dibuat.',
            'data' => $this->formatInvoice($invoice->fresh(['vendor:id,code,name'])),
        ], Response::HTTP_CREATED);
    }

    public function importInvoices(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']]);
        $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true); $headers = array_map(fn ($v) => strtolower(trim((string) $v)), array_shift($rows) ?: []); $map = [];
        foreach ($headers as $col => $header) $map[preg_replace('/[^a-z0-9]+/', '_', $header)] = $col;
        $get = fn ($row, $keys) => collect($keys)->first(fn ($key) => isset($map[$key])) !== null ? ($row[$map[collect($keys)->first(fn ($key) => isset($map[$key]))]] ?? null) : null;
        $created = 0; $errors = [];
        foreach ($rows as $number => $row) { if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) continue; try { $this->createInvoice(Request::create('/', 'POST', ['invoice_number' => $get($row, ['invoice_number']), 'reference_number' => $get($row, ['reference_number']), 'vendor_id' => (int) $get($row, ['vendor_id']), 'coa_id' => (int) $get($row, ['coa_id']), 'invoice_date' => $get($row, ['invoice_date']), 'due_date' => $get($row, ['due_date']) ?: null, 'total_amount' => (float) $get($row, ['total_amount']), 'notes' => $get($row, ['notes'])])); $created++; } catch (\Throwable $e) { $errors[] = 'Baris '.($number + 2).': '.$e->getMessage(); } }
        return response()->json(['success' => $created > 0, 'message' => "{$created} AP invoice berhasil diimport, ".count($errors).' gagal.', 'created' => $created, 'errors' => $errors]);
    }

    public function updateInvoice(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        if ($supplierInvoice->source_type !== 'accounts_payable' || ! in_array($supplierInvoice->status, ['draft', 'matched'], true)) {
            throw ValidationException::withMessages(['invoice' => 'Invoice ini tidak dapat diedit dari Accounts Payable.']);
        }
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:80', 'unique:supplier_invoices,invoice_number,'.$supplierInvoice->id],
            'coa_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'invoice_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'total_amount' => ['required', 'numeric', 'min:0'], 'reference_number' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);
        $supplierInvoice->update([...$data, 'updated_by' => $request->user()->id]);
        if ($request->hasFile('attachment')) {
            $supplierInvoice->update(['attachments' => [['name' => $request->file('attachment')->getClientOriginalName(), 'path' => $request->file('attachment')->store('ap-attachments', 'public')]]]);
        }
        return response()->json(['success' => true, 'message' => 'AP invoice berhasil diperbarui.', 'data' => $this->formatInvoice($supplierInvoice->fresh(['vendor:id,code,name']))]);
    }

    public function submitInvoice(Request $request, SupplierInvoice $supplierInvoice, ApprovalWorkflowService $workflow): JsonResponse
    {
        if (! in_array($supplierInvoice->match_status, ['matched', 'partial_match'], true)) {
            throw ValidationException::withMessages(['match_status' => 'The invoice must be matched or partially matched before AP approval.']);
        }
        if (in_array($supplierInvoice->status, ['posted', 'paid', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Invoice tidak dapat diajukan pada status saat ini.']);
        }

        $run = $workflow->start('ap', $supplierInvoice, (float) $supplierInvoice->total_amount, $request->user()->id);
        return response()->json(['success' => true, 'message' => $run ? 'Invoice submitted to the AP Approval Matrix.' : 'No active AP Approval Matrix; the invoice is ready to post.', 'workflow_managed' => (bool) $run, 'data' => $this->formatInvoice($supplierInvoice->fresh(['vendor:id,code,name', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'lines']))]);
    }

    public function rejectInvoice(Request $request, SupplierInvoice $supplierInvoice, ApprovalWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        if (in_array($supplierInvoice->status, ['posted', 'paid', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Invoice tidak dapat direject dari status saat ini.']);
        }

        $workflow->reject('ap', $supplierInvoice, $request->user(), $data['notes']);
        // Return to its matched state so the preparer can correct and resubmit.
        $supplierInvoice->update(['status' => 'matched', 'updated_by' => $request->user()->id]);
        return response()->json(['success' => true, 'message' => 'Invoice AP direject dan dapat diajukan ulang.', 'data' => $this->formatInvoice($supplierInvoice->fresh(['vendor:id,code,name', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'lines']))]);
    }

    public function postInvoice(Request $request, SupplierInvoice $supplierInvoice, ApprovalWorkflowService $workflow): JsonResponse
    {
        app(AccountingPeriodService::class)->ensureOpen($supplierInvoice->invoice_date->toDateString(), 'invoice_date');
        if (! in_array($supplierInvoice->match_status, ['matched', 'partial_match'], true)) {
            throw ValidationException::withMessages(['match_status' => 'The invoice must be matched or partially matched before AP posting.']);
        }
        if (in_array($supplierInvoice->status, ['posted', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => 'Invoice sudah diposting.']);
        }

        $approval = $workflow->approve('ap', $supplierInvoice, $request->user(), $request->input('notes'));
        if (! $approval['managed'] && $workflow->requiresApproval('ap', $supplierInvoice, (float) $supplierInvoice->total_amount)) {
            throw ValidationException::withMessages(['approval' => 'The invoice must be submitted to the AP Approval Matrix before posting.']);
        }
        if ($approval['managed'] && ! $approval['completed']) {
            return response()->json(['success' => true, 'message' => "Approval AP tahap selesai. Menunggu approver level {$approval['next_level']}.", 'data' => $this->formatInvoice($supplierInvoice->fresh(['vendor:id,code,name', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'lines']))]);
        }

        $apAccount = ChartOfAccount::query()->where('account_type', 'liability')->where('is_header', false)->first()
            ?? ChartOfAccount::query()->where('account_type', 'liability')->first();
        if (! $apAccount) {
            throw ValidationException::withMessages(['account' => 'COA liability/AP belum tersedia.']);
        }

        $invoice = DB::transaction(function () use ($supplierInvoice, $apAccount) {
            $journal = Journal::create([
                'fiscal_year_id' => $supplierInvoice->fiscal_year_id,
                'journal_number' => 'AP-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $supplierInvoice->invoice_date,
                'journal_type' => 'accrual',
                'reference' => $supplierInvoice->invoice_number,
                'description' => 'AP posting '.$supplierInvoice->invoice_number,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
                'source_type' => 'supplier_invoice', 'source_id' => $supplierInvoice->id,
            ]);

            foreach ($supplierInvoice->lines as $line) {
                $expenseAccount = ($supplierInvoice->coa_id ? ChartOfAccount::find($supplierInvoice->coa_id) : null)
                    ?? $line->purchaseOrderLine?->budgetLine?->glAccount
                    ?? ChartOfAccount::query()->where('account_type', 'expense')->where('is_header', false)->first();
                if (! $expenseAccount) {
                    throw ValidationException::withMessages(['account' => 'COA expense belum tersedia.']);
                }
                $journal->lines()->create([
                    'account_id' => $expenseAccount->id,
                    'project_id' => $supplierInvoice->purchaseOrder?->purchaseRequest?->project_id,
                    'donor_id' => $supplierInvoice->purchaseOrder?->purchaseRequest?->project?->grantAgreement?->donor_id,
                    'program_id' => $supplierInvoice->purchaseOrder?->purchaseRequest?->project?->program_id,
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

            if ($supplierInvoice->tax_id) {
                $tax = Tax::findOrFail($supplierInvoice->tax_id);
                $rate = (float) $tax->rate_percent;
                $taxable = round((float) $supplierInvoice->total_amount, 2);
                $taxAmount = round($taxable * $rate / 100, 2);
                TaxTransaction::firstOrCreate(
                    ['source_type' => SupplierInvoice::class, 'source_id' => $supplierInvoice->id],
                    [
                        'tax_id' => $tax->id,
                        'fiscal_year_id' => $supplierInvoice->fiscal_year_id,
                        'transaction_type' => 'ap_invoice',
                        'reference' => $supplierInvoice->invoice_number,
                        'transaction_date' => $supplierInvoice->invoice_date,
                        'direction' => str_starts_with(strtoupper((string) $tax->tax_type), 'PPH') ? 'withholding_in' : 'purchase',
                        'taxable_amount' => $taxable,
                        'tax_rate' => $rate,
                        'tax_amount' => $taxAmount,
                        'net_amount' => round($taxable - $taxAmount, 2),
                        'gross_amount' => round($taxable + $taxAmount, 2),
                        'npwp' => $supplierInvoice->vendor?->npwp,
                        'status' => 'draft',
                        'created_by' => request()->user()->id,
                    ]
                );
            }

            // Budget is now consumed by actual expense; convert and release the
            // source PR commitment to prevent double counting in available budget.
            $purchaseRequestId = $supplierInvoice->purchaseOrder?->purchase_request_id;
            if ($purchaseRequestId) {
                app(\App\Services\Budget\BudgetMonitoringService::class)
                    ->releaseForSource(
                        \App\Models\Procurement\PurchaseRequest::class,
                        $purchaseRequestId,
                        'converted',
                        request()->user()->id,
                    );
            }

            return $supplierInvoice->fresh(['vendor:id,code,name,npwp', 'tax:id,code,name,tax_type,rate_percent', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'lines']);
        });

        return response()->json(['success' => true, 'message' => 'Supplier invoice berhasil diposting ke AP.', 'data' => $this->formatInvoice($invoice)]);
    }

    public function payInvoice(Request $request, SupplierInvoice $supplierInvoice, ApprovalWorkflowService $workflow): JsonResponse
    {
        if (! in_array($supplierInvoice->status, ['posted', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => 'The invoice must be posted before payment.']);
        }

        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $approval = $workflow->approve('payment', $supplierInvoice, $request->user(), $request->input('notes'));
        if (! $approval['managed']) {
            $workflow->start('payment', $supplierInvoice, (float) $data['amount'], $request->user()->id);
            $approval = $workflow->approve('payment', $supplierInvoice, $request->user(), $request->input('notes'));
        }
        if ($approval['managed'] && ! $approval['completed']) {
            return response()->json(['success' => true, 'message' => "Pembayaran menunggu approver level {$approval['next_level']}.", 'data' => $this->formatInvoice($supplierInvoice->fresh())]);
        }
        app(AccountingPeriodService::class)->ensureOpen($data['payment_date'], 'payment_date');

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
            $invoiceRate = max((float) ($supplierInvoice->exchange_rate ?: 1), 0.000001);
            $paymentRate = max((float) ($data['exchange_rate'] ?? $invoiceRate), 0.000001);
            $functionalAmount = round((float) $data['amount'] * $paymentRate, 2);
            $invoiceFunctionalAmount = round((float) $data['amount'] * $invoiceRate, 2);
            $fxGainLoss = round($functionalAmount - $invoiceFunctionalAmount, 2);
            $fxAccount = $fxGainLoss > 0 ? ChartOfAccount::where('code', 'FX-LOSS')->first() : ChartOfAccount::where('code', 'FX-GAIN')->first();
            $journal = Journal::create([
                'fiscal_year_id' => $supplierInvoice->fiscal_year_id,
                'journal_number' => 'PAY-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'journal_date' => $data['payment_date'],
                'journal_type' => 'manual',
                'reference' => $data['reference'] ?? $supplierInvoice->invoice_number,
                'description' => 'Payment '.$supplierInvoice->invoice_number,
                'status' => 'posted',
                'posted_by' => request()->user()->id,
                'posted_at' => now(),
                'original_amount' => $data['amount'],
                'converted_amount' => $functionalAmount,
                'exchange_rate' => $paymentRate,
                'rate_date' => $data['payment_date'],
                'rate_source' => 'payment_snapshot',
                'fx_gain_loss' => $fxGainLoss,
            ]);
            $lineContext = ['project_id' => $supplierInvoice->purchaseOrder?->purchaseRequest?->project_id, 'donor_id' => $supplierInvoice->purchaseOrder?->purchaseRequest?->project?->grantAgreement?->donor_id, 'program_id' => $supplierInvoice->purchaseOrder?->purchaseRequest?->project?->program_id];
            $journal->lines()->create(array_merge(['account_id' => $apAccount->id, 'line_description' => 'AP payment at invoice rate', 'debit' => $invoiceFunctionalAmount, 'credit' => 0, 'line_order' => 1], $lineContext));
            $journal->lines()->create(array_merge(['account_id' => $bank->gl_account_id, 'line_description' => 'Bank payment', 'debit' => 0, 'credit' => $functionalAmount, 'line_order' => 2], $lineContext));
            if (abs($fxGainLoss) >= 0.01 && $fxAccount) {
                $journal->lines()->create(['account_id' => $fxAccount->id, 'line_description' => $fxGainLoss > 0 ? 'FX loss on payment' : 'FX gain on payment', 'debit' => $fxGainLoss > 0 ? $fxGainLoss : 0, 'credit' => $fxGainLoss < 0 ? abs($fxGainLoss) : 0, 'line_order' => 3]);
            }

            $payment = Payment::create([
                'fiscal_year_id' => $supplierInvoice->fiscal_year_id,
                'payment_number' => 'PAY-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'supplier_invoice_id' => $supplierInvoice->id,
                'vendor_id' => $supplierInvoice->vendor_id,
                'bank_account_id' => $bank->id,
                'project_id' => $supplierInvoice->purchaseOrder?->purchaseRequest?->project_id,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'exchange_rate' => $paymentRate,
                'original_amount' => $data['amount'],
                'converted_amount' => $functionalAmount,
                'fx_gain_loss' => $fxGainLoss,
                'rate_source' => 'payment_snapshot',
                'rate_date' => $data['payment_date'],
                'reference' => $data['reference'] ?? null,
                'status' => 'paid',
                'journal_id' => $journal->id,
                'created_by' => request()->user()->id,
            ]);

            BankTransaction::create([
                'fiscal_year_id' => $supplierInvoice->fiscal_year_id,
                'bank_account_id' => $bank->id,
                'project_id' => $supplierInvoice->purchaseOrder?->purchaseRequest?->project_id,
                'payment_id' => $payment->id,
                'journal_id' => $journal->id,
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

    public function payments(Request $request): JsonResponse
    {
        $query = Payment::with(['supplierInvoice:id,invoice_number', 'bankAccount:id,bank_name,account_number']);
        app(\App\Services\Rbac\DataScopeService::class)->applyScope($query, $request->user(), 'created_by', null, null);
        return response()->json(['success' => true, 'data' => $query->latest('id')->get()]);
    }

    public function bankTransactions(Request $request): JsonResponse
    {
        $query = BankTransaction::with([
            'bankAccount:id,bank_name,account_number',
            'project:id,code,name,relation_no,grant_agreement_id',
            'project.grantAgreement:id,relation_no,grant_no,agreement_name',
            'payment:id,payment_number,payment_date,amount,reference,journal_id,bank_account_id,expense_request_id,supplier_invoice_id,customer_invoice_id',
            'payment.journal:id,journal_number,journal_date,status',
            'payment.journal.lines:id,journal_id,relation_no,project_id,grant_agreement_id',
            'payment.journal.lines.project:id,code,name,relation_no',
            'payment.journal.lines.grantAgreement:id,relation_no,grant_no,agreement_name',
            'journal:id,journal_number,journal_date,status,reference,description',
            'payment.supplierInvoice:id,invoice_number,purchase_order_id',
            'payment.supplierInvoice.purchaseOrder:id,po_number,purchase_request_id',
            'payment.supplierInvoice.purchaseOrder.purchaseRequest:id,pr_number,project_id',
            'payment.supplierInvoice.purchaseOrder.purchaseRequest.project:id,code,name,relation_no',
            'payment.supplierInvoice.purchaseOrder.purchaseRequest.project.grantAgreement:id,relation_no,grant_no',
            'payment.customerInvoice:id,invoice_number',
            'payment.expenseRequest:id,request_number,project_id,grant_agreement_id',
            'payment.expenseRequest.grantAgreement:id,relation_no,grant_no,agreement_name',
            'payment.expenseRequest.project:id,code,name,relation_no',
        ]);
        if ($request->filled('bank_account_id')) $query->where('bank_account_id', $request->integer('bank_account_id'));
        if ($request->filled('relation_no')) {
            $relFilter = trim((string) $request->input('relation_no'));
            $query->where(function ($q) use ($relFilter) {
                $q->whereHas('project', fn ($p) => $p->where('relation_no', 'like', "%{$relFilter}%"))
                    ->orWhereHas('payment.expenseRequest.grantAgreement', fn ($g) => $g->where('relation_no', 'like', "%{$relFilter}%"))
                    ->orWhereHas('payment.expenseRequest.project', fn ($p) => $p->where('relation_no', 'like', "%{$relFilter}%"))
                    ->orWhereHas('payment.supplierInvoice.purchaseOrder.purchaseRequest.project', fn ($p) => $p->where('relation_no', 'like', "%{$relFilter}%"))
                    ->orWhereHas('payment.journal.lines', fn ($l) => $l->where('relation_no', 'like', "%{$relFilter}%"));
            });
        }
        if (! app(\App\Services\Rbac\DataScopeService::class)->canAccessAll($request->user())) {
            $query->where(function ($scoped) use ($request) {
                $scoped->where('created_by', $request->user()->id)
                    ->orWhereHas('payment', fn ($payment) => $payment->where('created_by', $request->user()->id));
            });
        }

        $transactions = $query->latest('transaction_date')->latest('id')->get();
        $references = $transactions->pluck('reference')->filter()->unique()->values();
        $refJournals = $references->isNotEmpty()
            ? \App\Models\Accounting\Journal::with([
                'lines:id,journal_id,relation_no,project_id,grant_agreement_id',
                'lines.project:id,code,name,relation_no',
                'lines.grantAgreement:id,relation_no,grant_no,agreement_name',
            ])->whereIn('journal_number', $references)->get()->keyBy('journal_number')
            : collect();

        $rows = $transactions->map(function (BankTransaction $transaction) use ($refJournals) {
            $payment = $transaction->payment;
            $relInfo = $this->resolveBankTransactionRelationInfo($transaction, $refJournals);
            return [
                'id' => $transaction->id,
                'bank_account' => $transaction->bankAccount ? ['id' => $transaction->bankAccount->id, 'bank_name' => $transaction->bankAccount->bank_name, 'account_number' => $transaction->bankAccount->account_number] : null,
                'transaction_date' => $transaction->transaction_date?->toDateString(),
                'reference' => $transaction->reference,
                'relation_no' => $relInfo['relation_no'] ?? null,
                'project' => $relInfo ? [
                    'name' => $relInfo['project_name'] ?? null,
                    'code' => $relInfo['project_code'] ?? null,
                    'relation_no' => $relInfo['relation_no'] ?? null,
                ] : null,
                'description' => $transaction->description,
                'debit' => (float) $transaction->debit,
                'credit' => (float) $transaction->credit,
                'difference' => $transaction->difference !== null ? (float) $transaction->difference : null,
                'remarks' => $transaction->remarks,
                'status' => $transaction->status,
                'journal_id' => $transaction->journal_id ?: $payment?->journal_id,
                'journal' => ($transaction->journal ?: $payment?->journal) ? [
                    'id' => ($transaction->journal ?: $payment?->journal)->id,
                    'journal_number' => ($transaction->journal ?: $payment?->journal)->journal_number,
                    'journal_date' => ($transaction->journal ?: $payment?->journal)->journal_date?->toDateString(),
                    'status' => ($transaction->journal ?: $payment?->journal)->status,
                ] : null,
                'payment' => $payment ? [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'amount' => (float) $payment->amount,
                    'reference' => $payment->reference,
                    'relation_no' => $relInfo['relation_no'] ?? null,
                    'invoice_number' => $payment->supplierInvoice?->invoice_number ?? $payment->customerInvoice?->invoice_number ?? $payment->expenseRequest?->request_number,
                    'journal' => $payment->journal ? ['id' => $payment->journal->id, 'journal_number' => $payment->journal->journal_number, 'journal_date' => $payment->journal->journal_date?->toDateString(), 'status' => $payment->journal->status] : null,
                ] : null,
                'history' => \App\Models\AuditLog::query()
                    ->where('module', 'banking')
                    ->where('entity_type', BankTransaction::class)
                    ->where('entity_id', $transaction->id)
                    ->orderByDesc('id')->limit(3)
                    ->get(['action', 'new_values', 'created_at'])
                    ->map(fn ($log) => ['action' => $log->action, 'values' => $log->new_values, 'at' => $log->created_at?->toIso8601String()])->values(),
            ];
        });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** Queue of imported statement lines that still need a finance decision. */
    public function bankExceptions(Request $request): JsonResponse
    {
        $query = BankTransaction::query()
            ->with([
                'bankAccount:id,bank_name,account_number',
                'project:id,code,name,relation_no,grant_agreement_id',
                'project.grantAgreement:id,relation_no,grant_no,agreement_name',
            ])
            ->where('status', 'unmatched');
        if ($request->filled('bank_account_id')) $query->where('bank_account_id', $request->integer('bank_account_id'));
        $this->applyBankTransactionScope($query, $request);
        $user = $request->user();
        $scope = app(\App\Services\Rbac\DataScopeService::class);
        $items = $query->latest('transaction_date')->latest('id')->limit(100)->get()->map(function (BankTransaction $transaction) use ($user, $scope) {
            $amount = round(max((float) $transaction->debit, (float) $transaction->credit), 2);
            $candidates = Payment::query()
                ->with([
                    'expenseRequest:id,request_number,project_id,grant_agreement_id',
                    'expenseRequest.grantAgreement:id,relation_no,grant_no,agreement_name',
                    'expenseRequest.project:id,code,name,relation_no',
                    'supplierInvoice:id,invoice_number,purchase_order_id',
                    'supplierInvoice.purchaseOrder.purchaseRequest.project:id,code,name,relation_no',
                    'supplierInvoice.purchaseOrder.purchaseRequest.project.grantAgreement:id,relation_no,grant_no',
                    'journal.lines:id,journal_id,relation_no,project_id,grant_agreement_id',
                    'journal.lines.project:id,code,name,relation_no',
                    'journal.lines.grantAgreement:id,relation_no,grant_no',
                ])
                ->where('bank_account_id', $transaction->bank_account_id)
                ->where('status', 'paid')
                ->whereHas('journal', fn ($journal) => $journal->where('status', 'posted'))
                ->where('amount', $amount)
                ->whereBetween('payment_date', [$transaction->transaction_date->copy()->subDays(7)->toDateString(), $transaction->transaction_date->copy()->addDays(7)->toDateString()]);
            $this->applyPaymentDirectionScope($candidates, $transaction);
            if (! $scope->canAccessAll($user)) {
                $candidates->where('created_by', $user->id);
            }
            $candidateCount = $candidates->count();
            $candidateRows = $candidates->orderByDesc('payment_date')->limit(5)->get();
            $relInfo = $this->resolveBankTransactionRelationInfo($transaction);

            return [
                'id' => $transaction->id,
                'transaction_date' => $transaction->transaction_date?->toDateString(),
                'reference' => $transaction->reference,
                'relation_no' => $relInfo['relation_no'] ?? null,
                'project_name' => $relInfo['project_name'] ?? null,
                'description' => $transaction->description,
                'debit' => (float) $transaction->debit,
                'credit' => (float) $transaction->credit,
                'bank_account' => $transaction->bankAccount,
                'candidate_count' => $candidateCount,
                'candidates' => $candidateRows->map(function (Payment $payment) {
                    $candRel = $this->resolvePaymentRelationInfo($payment);
                    return [
                        'id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'payment_date' => $payment->payment_date?->toDateString(),
                        'amount' => (float) $payment->amount,
                        'reference' => $payment->reference,
                        'journal_id' => $payment->journal_id,
                        'journal_number' => $payment->journal?->journal_number,
                        'relation_no' => $candRel['relation_no'] ?? null,
                        'project_name' => $candRel['project_name'] ?? null,
                    ];
                })->values(),
                'recommended_action' => $candidateCount === 1 ? 'auto_match' : ($candidateCount > 1 ? 'manual_match' : 'review'),
            ];
        })->values();

        return response()->json(['success' => true, 'count' => $items->count(), 'data' => $items]);
    }

    public function importBankTransactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls,pdf', 'max:10240'],
            'preview' => ['nullable', 'in:0,1,true,false'],
        ]);

        $previewOnly = filter_var($data['preview'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $bankAccount = BankAccount::withoutGlobalScope('fiscal_year_context')->findOrFail($data['bank_account_id']);
        if (! $bankAccount->is_active || ! $bankAccount->gl_account_id) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Rekening bank harus aktif dan sudah terhubung ke COA sebelum mutasi diimport.',
            ]);
        }
        $extension = strtolower($data['file']->getClientOriginalExtension());
        $rows = $this->readStatementRows($data['file']->getRealPath(), $extension);

        $errorCount = 0;
        $created = 0;
        $duplicates = 0;
        $errors = [];
        $inserts = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // offset header row
            try {
                $date = \Carbon\Carbon::parse(trim((string) $row[0]))->toDateString();
                $reference = trim((string) ($row[1] ?? '')) ?: null;
                $description = trim((string) ($row[2] ?? '')) ?: null;
                $debit = (float) str_replace([',', ' '], ['', ''], (string) ($row[3] ?? 0));
                $credit = (float) str_replace([',', ' '], ['', ''], (string) ($row[4] ?? 0));
                if ($debit < 0 || $credit < 0) {
                    throw new \InvalidArgumentException('Nominal debit/credit tidak boleh negatif.');
                }
                if ($debit <= 0 && $credit <= 0) {
                    throw new \InvalidArgumentException('Each line must have either a debit or a credit.');
                }
                if ($debit > 0 && $credit > 0) {
                    throw new \InvalidArgumentException('Each line may only contain a debit or a credit, not both.');
                }
                $duplicate = BankTransaction::where('bank_account_id', $data['bank_account_id'])
                    ->whereDate('transaction_date', $date)
                    ->where('reference', $reference)
                    ->where('debit', $debit)
                    ->where('credit', $credit)
                    ->exists();
                if ($duplicate) {
                    $duplicates++;
                    continue;
                }
                $inserts[] = [
                    'fiscal_year_id' => $bankAccount->fiscal_year_id,
                    'bank_account_id' => $data['bank_account_id'],
                    'created_by' => $request->user()->id,
                    'transaction_date' => $date,
                    'reference' => $reference,
                    'description' => $description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'status' => 'unmatched',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $created++;
            } catch (\Throwable $e) {
                $errorCount++;
                if (count($errors) < 50) {
                    $errors[] = ['row' => $lineNumber, 'error' => mb_substr($e->getMessage(), 0, 200)];
                }
            }
        }

        if ($previewOnly) {
            return response()->json([
                'success' => true,
                'preview' => true,
                'data' => ['created' => $created, 'duplicates' => $duplicates, 'failed' => $errorCount, 'errors' => $errors, 'rows' => array_map(function (array $row, int $index) {
                    return ['row' => $index + 2, 'transaction_date' => trim((string) $row[0]), 'reference' => trim((string) ($row[1] ?? '')), 'description' => trim((string) ($row[2] ?? '')), 'debit' => (float) str_replace([',', ' '], ['', ''], (string) ($row[3] ?? 0)), 'credit' => (float) str_replace([',', ' '], ['', ''], (string) ($row[4] ?? 0))];
                }, array_slice($rows, 0, 20), array_slice(array_keys($rows), 0, 20))],
            ]);
        }

        if (! empty($inserts)) {
            foreach ($inserts as $insert) {
                BankTransaction::create($insert);
            }
        }

        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'banking',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => 'IMPORT',
            'entity_type' => BankTransaction::class,
            'entity_id' => null,
            'previous_values' => [],
            'new_values' => [
                'bank_account_id' => (int) $data['bank_account_id'],
                'created' => $created,
                'duplicates' => $duplicates,
                'failed' => $errorCount,
                'errors' => array_slice($errors, 0, 10),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$created} transaksi diimport, {$duplicates} duplikat dilewati, {$errorCount} baris gagal.",
            'data' => ['created' => $created, 'duplicates' => $duplicates, 'failed' => $errorCount, 'errors' => $errors],
        ]);
    }

    private function readStatementRows(string $path, string $extension): array
    {
        if ($extension === 'pdf') {
            return $this->readPdfStatementRows($path);
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getActiveSheet();
            $rows = [];
            foreach ($sheet->toArray() as $cells) {
                if ($this->isHeaderRow($cells)) {
                    continue;
                }
                $rows[] = array_values(array_map(fn ($cell) => $cell === null ? '' : (string) $cell, $cells));
            }

            return $rows;
        }

        $handle = fopen($path, 'rb');
        $rows = [];
        while (($columns = fgetcsv($handle)) !== false) {
            if ($this->isHeaderRow($columns)) {
                continue;
            }
            $rows[] = $columns;
        }
        fclose($handle);

        return $rows;
    }

    private function readPdfStatementRows(string $path): array
    {
        $parser = new \Smalot\PdfParser\Parser();
        $text = $parser->parseFile($path)->getText();
        $lines = preg_split('/\r\n|\r|\n/', (string) $text) ?: [];
        $rows = [];

        foreach ($lines as $rawLine) {
            $line = trim(preg_replace('/\s+/', ' ', (string) $rawLine) ?? '');
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^(\d{4}-\d{2}-\d{2}|\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}|\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4})\s+(.+)$/', $line, $dateMatch)) {
                continue;
            }

            $rawDate = $dateMatch[1];
            $rest = trim($dateMatch[2]);

            try {
                $normalizedDate = preg_match('/^\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}$/', $rawDate)
                    ? \Carbon\Carbon::createFromFormat(str_contains($rawDate, '/') ? (strlen($rawDate) <= 8 ? 'd/m/y' : 'd/m/Y') : (strlen($rawDate) <= 8 ? 'd-m-y' : 'd-m-Y'), $rawDate)->toDateString()
                    : \Carbon\Carbon::parse($rawDate)->toDateString();
            } catch (\Throwable) {
                continue;
            }

            if (! preg_match_all('/(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})|\d+(?:[.,]\d{2})?)(\s*(?:DB|CR|D|K))?(?=\s|$)/i', $rest, $amountMatches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $amounts = [];
            $firstAmountOffset = null;
            foreach ($amountMatches[0] as $idx => $fullMatch) {
                $rawNum = $amountMatches[1][$idx][0];
                $flag = strtoupper(trim($amountMatches[2][$idx][0] ?? ''));
                $parsed = $this->parseStatementNumber($rawNum);
                if ($parsed === null) {
                    continue;
                }
                if ($firstAmountOffset === null) {
                    $firstAmountOffset = $fullMatch[1];
                }
                $amounts[] = ['value' => $parsed, 'flag' => $flag];
            }

            if (empty($amounts)) {
                continue;
            }

            $description = $firstAmountOffset !== null ? trim(substr($rest, 0, $firstAmountOffset)) : $rest;
            if ($this->isHeaderRow([$rawDate, $description])) {
                continue;
            }

            $debit = 0.0;
            $credit = 0.0;
            if (count($amounts) >= 3) {
                $debit = $amounts[0]['value'];
                $credit = $amounts[1]['value'];
            } elseif (count($amounts) === 2) {
                $first = $amounts[0];
                if (in_array($first['flag'], ['DB', 'D'], true)) {
                    $credit = $first['value'];
                } elseif (in_array($first['flag'], ['CR', 'K'], true)) {
                    $debit = $first['value'];
                } else {
                    $debit = $first['value'];
                }
            } else {
                $only = $amounts[0];
                if (in_array($only['flag'], ['DB', 'D'], true)) {
                    $credit = $only['value'];
                } else {
                    $debit = $only['value'];
                }
            }

            $rows[] = [$normalizedDate, '', $description, (string) $debit, (string) $credit];
        }

        if (empty($rows)) {
            throw ValidationException::withMessages([
                'file' => 'PDF tidak memiliki baris mutasi teks yang dapat dibaca (pastikan PDF hasil export bank dengan teks aktif, bukan scan gambar).',
            ]);
        }

        return $rows;
    }

    private function parseStatementNumber(string $raw): ?float
    {
        $clean = trim($raw);
        if ($clean === '' || ! preg_match('/\d/', $clean)) {
            return null;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $clean)) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+\.\d{2}$/', $clean)) {
            $clean = str_replace(',', '', $clean);
        } elseif (substr_count($clean, ',') === 1 && ! str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    private function isHeaderRow(array $columns): bool
    {
        $first = trim((string) ($columns[0] ?? ''));
        if ($first === '' || $first === null) {
            return true;
        }

        return (bool) preg_match('/^date|^tanggal|^tgl|^transaction/i', (string) $first);
    }

    /**
     * Suggest and apply a deterministic payment match for an imported bank line.
     * Ambiguous matches deliberately remain unmatched for a finance user to review.
     */
    public function autoMatchBankTransaction(Request $request, BankTransaction $bankTransaction): JsonResponse
    {
        $this->ensureBankTransactionScope($request, $bankTransaction, 'mencocokkan otomatis');

        if ($bankTransaction->status !== 'unmatched') {
            throw ValidationException::withMessages(['status' => 'Auto-match hanya dapat dilakukan untuk transaksi bank berstatus unmatched.']);
        }

        $amount = round(max((float) $bankTransaction->debit, (float) $bankTransaction->credit), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The bank transaction amount must be greater than zero for auto-matching.']);
        }

        $date = $bankTransaction->transaction_date;
        $payments = Payment::query()
            ->with(['supplierInvoice:id,invoice_number', 'customerInvoice:id,invoice_number', 'journal:id,journal_number,status'])
            ->where('bank_account_id', $bankTransaction->bank_account_id)
            ->where('status', 'paid')
            ->whereHas('journal', fn ($journal) => $journal->where('status', 'posted'))
            ->where('amount', $amount)
            ->whereBetween('payment_date', [$date->copy()->subDays(7)->toDateString(), $date->copy()->addDays(7)->toDateString()]);
        $this->applyPaymentDirectionScope($payments, $bankTransaction);

        if (! app(\App\Services\Rbac\DataScopeService::class)->canAccessAll($request->user())) {
            $payments->where('created_by', $request->user()->id);
        }

        $candidates = $payments->orderByDesc('payment_date')->limit(3)->get();
        if ($candidates->count() !== 1) {
            return response()->json([
                'success' => true,
                'matched' => false,
                'message' => $candidates->isEmpty() ? 'Tidak ditemukan payment yang cocok; transaksi tetap unmatched.' : 'Ditemukan lebih dari satu kandidat payment; lakukan matching manual.',
                'data' => $bankTransaction->fresh(['bankAccount:id,bank_name,account_number']),
                'candidates' => $candidates->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'invoice_number' => $payment->supplierInvoice?->invoice_number ?? $payment->customerInvoice?->invoice_number,
                ])->values(),
            ]);
        }

        $payment = $candidates->first();
        $updates = $this->validatedPaymentMatchUpdates($payment, $bankTransaction);
        $previous = ['payment_id' => $bankTransaction->payment_id, 'status' => $bankTransaction->status];
        $bankTransaction->update($updates);
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'banking',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => 'AUTO_MATCH',
            'entity_type' => BankTransaction::class,
            'entity_id' => $bankTransaction->id,
            'previous_values' => $previous,
            'new_values' => $updates,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true, 'matched' => true, 'message' => 'Transaksi bank berhasil dicocokkan otomatis.', 'data' => $bankTransaction->fresh(['bankAccount:id,bank_name,account_number', 'payment:id,payment_number,payment_date,amount,reference'])]);
    }

    /** Confirm a finance user's selected candidate when auto-match is ambiguous. */
    public function matchBankTransaction(Request $request, BankTransaction $bankTransaction): JsonResponse
    {
        $this->ensureBankTransactionScope($request, $bankTransaction, 'mencocokkan');
        if ($bankTransaction->status !== 'unmatched') {
            throw ValidationException::withMessages(['status' => 'Manual match hanya dapat dilakukan untuk transaksi bank berstatus unmatched.']);
        }

        $data = $request->validate(['payment_id' => ['required', 'integer', 'exists:payments,id']]);
        $payment = Payment::with(['journal.lines', 'bankAccount'])->findOrFail($data['payment_id']);
        if ((int) $payment->bank_account_id !== (int) $bankTransaction->bank_account_id) {
            throw ValidationException::withMessages(['payment_id' => 'The payment must use the same bank account.']);
        }
        if (! app(\App\Services\Rbac\DataScopeService::class)->canAccessAll($request->user()) && (int) $payment->created_by !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mencocokkan payment milik user lain.');
        }

        $updates = $this->validatedPaymentMatchUpdates($payment, $bankTransaction);
        $previous = ['payment_id' => $bankTransaction->payment_id, 'status' => $bankTransaction->status];
        $bankTransaction->update($updates);
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'banking',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => 'MATCH',
            'entity_type' => BankTransaction::class,
            'entity_id' => $bankTransaction->id,
            'previous_values' => $previous,
            'new_values' => $updates,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true, 'message' => 'Transaksi bank berhasil dicocokkan ke payment.', 'data' => $bankTransaction->fresh(['bankAccount:id,bank_name,account_number', 'payment:id,payment_number,payment_date,amount,reference'])]);
    }

    public function reconcileBankTransaction(Request $request, BankTransaction $bankTransaction, ApprovalWorkflowService $workflow): JsonResponse
    {
        $this->ensureBankTransactionScope($request, $bankTransaction, 'merekonsiliasi');

        $data = $request->validate([
            'status' => ['required', 'in:matched,excluded,reconciled,unmatched'],
            'difference' => ['nullable', 'numeric'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);
        $journal = null;
        if (in_array($data['status'], ['matched', 'reconciled'], true)) {
            $journal = $this->ensureBankTransactionJournalIntegrity($bankTransaction);
        }
        $approval = $workflow->approve('bank_reconciliation', $bankTransaction, $request->user(), $request->input('notes'));
        if (! $approval['managed']) {
            $workflow->start('bank_reconciliation', $bankTransaction, (float) $bankTransaction->debit + (float) $bankTransaction->credit, $request->user()->id);
            $approval = $workflow->approve('bank_reconciliation', $bankTransaction, $request->user(), $request->input('notes'));
        }
        if ($approval['managed'] && ! $approval['completed']) return response()->json(['success' => true, 'message' => "Rekonsiliasi menunggu approver level {$approval['next_level']}.", 'data' => $bankTransaction]);
        $previous = ['status' => $bankTransaction->status];
        $bankTransaction->update([
            'status' => $data['status'],
            'journal_id' => $journal?->id ?: $bankTransaction->journal_id,
            'difference' => array_key_exists('difference', $data) ? $data['difference'] : $bankTransaction->difference,
            'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $bankTransaction->remarks,
        ]);
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'banking',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => 'RECONCILE',
            'entity_type' => BankTransaction::class,
            'entity_id' => $bankTransaction->id,
            'previous_values' => $previous,
            'new_values' => ['status' => $data['status']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return response()->json(['success' => true, 'message' => 'Reconciliation status updated.', 'data' => $bankTransaction->fresh(['bankAccount:id,bank_name,account_number'])]);
    }

    public function unmatchBankTransaction(Request $request, BankTransaction $bankTransaction): JsonResponse
    {
        $this->ensureBankTransactionScope($request, $bankTransaction, 'membatalkan rekonsiliasi');

        $previous = ['payment_id' => $bankTransaction->payment_id, 'status' => $bankTransaction->status];
        // Imported statement rows may be re-matched. Never detach the internal
        // bank row generated while recording a payment.
        $updates = ['status' => 'unmatched'];
        if ($bankTransaction->created_by) {
            $updates['payment_id'] = null;
        }
        $bankTransaction->update($updates);
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'banking',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => 'UNMATCH',
            'entity_type' => BankTransaction::class,
            'entity_id' => $bankTransaction->id,
            'previous_values' => $previous,
            'new_values' => $updates,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Rekonsiliasi bank berhasil dibatalkan.',
            'data' => $bankTransaction->fresh(['bankAccount:id,bank_name,account_number']),
        ]);
    }

    private function ensureBankTransactionScope(Request $request, BankTransaction $bankTransaction, string $action): void
    {
        if (app(\App\Services\Rbac\DataScopeService::class)->canAccessAll($request->user())) {
            return;
        }

        $allowed = BankTransaction::whereKey($bankTransaction->id)
            ->where(function ($scoped) use ($request) {
                $scoped->where('created_by', $request->user()->id)
                    ->orWhereHas('payment', fn ($payment) => $payment->where('created_by', $request->user()->id));
            })
            ->exists();

        if (! $allowed) {
            abort(Response::HTTP_FORBIDDEN, "Tidak boleh {$action} transaksi bank ini.");
        }
    }

    private function applyBankTransactionScope($query, Request $request): void
    {
        if (app(\App\Services\Rbac\DataScopeService::class)->canAccessAll($request->user())) {
            return;
        }
        $query->where(function ($scoped) use ($request) {
            $scoped->where('created_by', $request->user()->id)
                ->orWhereHas('payment', fn ($payment) => $payment->where('created_by', $request->user()->id));
        });
    }

    private function applyPaymentDirectionScope($query, BankTransaction $bankTransaction): void
    {
        if ((float) $bankTransaction->debit > 0 && (float) $bankTransaction->credit <= 0) {
            $query->whereNotNull('customer_invoice_id');
            return;
        }

        if ((float) $bankTransaction->credit > 0 && (float) $bankTransaction->debit <= 0) {
            $query->whereNull('customer_invoice_id');
        }
    }

    private function validatedPaymentMatchUpdates(Payment $payment, BankTransaction $bankTransaction): array
    {
        $payment->loadMissing([
            'bankAccount',
            'journal.lines',
            'expenseRequest.project',
            'supplierInvoice.purchaseOrder.purchaseRequest.project',
            'customerInvoice.project',
        ]);

        if ($payment->status !== 'paid') {
            throw ValidationException::withMessages(['payment_id' => 'Only paid payments may be matched to a bank statement.']);
        }
        if (! $payment->journal || $payment->journal->status !== 'posted') {
            throw ValidationException::withMessages(['payment_id' => 'Payment must be linked to a posted journal before bank matching.']);
        }
        if (round((float) $payment->amount, 2) !== round(max((float) $bankTransaction->debit, (float) $bankTransaction->credit), 2)) {
            throw ValidationException::withMessages(['payment_id' => 'Payment amount does not match the bank statement amount.']);
        }

        $isReceipt = $payment->customer_invoice_id !== null;
        if ($isReceipt && ((float) $bankTransaction->debit <= 0 || (float) $bankTransaction->credit > 0)) {
            throw ValidationException::withMessages(['payment_id' => 'An incoming receipt must match a debit bank statement line.']);
        }
        if (! $isReceipt && ((float) $bankTransaction->credit <= 0 || (float) $bankTransaction->debit > 0)) {
            throw ValidationException::withMessages(['payment_id' => 'An outgoing payment must match a credit bank statement line.']);
        }

        $journalDebit = round((float) $payment->journal->lines->sum('debit'), 2);
        $journalCredit = round((float) $payment->journal->lines->sum('credit'), 2);
        if (abs($journalDebit - $journalCredit) >= 0.01) {
            throw ValidationException::withMessages(['payment_id' => 'Payment journal is not balanced.']);
        }

        $bankGlAccountId = $payment->bankAccount?->gl_account_id;
        $bankLine = $payment->journal->lines->first(fn ($line) => (int) $line->account_id === (int) $bankGlAccountId);
        if (! $bankGlAccountId || ! $bankLine) {
            throw ValidationException::withMessages(['payment_id' => 'Payment journal is not linked to the selected bank GL account.']);
        }
        if ($isReceipt && (float) $bankLine->debit <= 0) {
            throw ValidationException::withMessages(['payment_id' => 'Receipt journal does not debit the selected bank GL account.']);
        }
        if (! $isReceipt && (float) $bankLine->credit <= 0) {
            throw ValidationException::withMessages(['payment_id' => 'Payment journal does not credit the selected bank GL account.']);
        }

        $projectId = $payment->expenseRequest?->project_id
            ?: $payment->supplierInvoice?->purchaseOrder?->purchaseRequest?->project_id
            ?: $payment->customerInvoice?->project_id
            ?: $payment->journal->lines->first(fn ($line) => $line->project_id)?->project_id;

        return [
            'payment_id' => $payment->id,
            'journal_id' => $payment->journal_id,
            'project_id' => $projectId,
            'fiscal_year_id' => $payment->fiscal_year_id ?: $bankTransaction->fiscal_year_id,
            'status' => 'matched',
        ];
    }

    private function ensureBankTransactionJournalIntegrity(BankTransaction $bankTransaction): \App\Models\Accounting\Journal
    {
        $bankTransaction->loadMissing(['bankAccount', 'journal.lines', 'payment.journal.lines']);
        $journal = $bankTransaction->journal ?: $bankTransaction->payment?->journal;
        if (! $journal || $journal->status !== 'posted') {
            throw ValidationException::withMessages(['status' => 'A matched or reconciled bank transaction must be linked to a posted journal.']);
        }

        $journalDebit = round((float) $journal->lines->sum('debit'), 2);
        $journalCredit = round((float) $journal->lines->sum('credit'), 2);
        if (abs($journalDebit - $journalCredit) >= 0.01) {
            throw ValidationException::withMessages(['status' => 'The linked journal must be balanced before reconciliation.']);
        }

        $bankGlAccountId = $bankTransaction->bankAccount?->gl_account_id;
        $bankLine = $journal->lines->first(fn ($line) => (int) $line->account_id === (int) $bankGlAccountId);
        if (! $bankGlAccountId || ! $bankLine) {
            throw ValidationException::withMessages(['status' => 'The linked journal does not contain the selected bank GL account.']);
        }
        if ((float) $bankTransaction->debit > 0 && (float) $bankLine->debit <= 0) {
            throw ValidationException::withMessages(['status' => 'Incoming bank movement must debit the selected bank GL account.']);
        }
        if ((float) $bankTransaction->credit > 0 && (float) $bankLine->credit <= 0) {
            throw ValidationException::withMessages(['status' => 'Outgoing bank movement must credit the selected bank GL account.']);
        }

        return $journal;
    }

    private function formatInvoice(SupplierInvoice $invoice): array
    {
        $approval = \App\Models\ApprovalWorkflowRun::query()
            ->where('module', 'ap')
            ->where('approvable_type', SupplierInvoice::class)
            ->where('approvable_id', $invoice->id)
            ->latest('id')
            ->first();

        return [
            'id' => $invoice->id,
            'source_type' => $invoice->source_type ?: 'procurement',
            'invoice_number' => $invoice->invoice_number,
            'reference_number' => $invoice->reference_number,
            'coa_id' => $invoice->coa_id,
            'coa_code' => $invoice->coa?->code,
            'coa_name' => $invoice->coa?->name,
            'vendor_name' => $invoice->vendor?->name,
            'tax' => $invoice->tax ? ['id' => $invoice->tax->id, 'code' => $invoice->tax->code, 'name' => $invoice->tax->name, 'tax_type' => $invoice->tax->tax_type, 'rate_percent' => $invoice->tax->rate_percent] : null,
            'po_number' => $invoice->purchaseOrder?->po_number,
            'grn_number' => $invoice->goodsReceipt?->grn_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'match_status' => $invoice->match_status,
            'approval_status' => $approval?->status,
            'approval_progress' => app(\App\Services\Approval\ApprovalWorkflowService::class)->progressFor('ap', $invoice, request()->user()),
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'outstanding_amount' => round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2),
            'payment_history' => $invoice->relationLoaded('payments') ? $invoice->payments->map(fn ($payment) => ['id' => $payment->id, 'payment_number' => $payment->payment_number, 'payment_date' => $payment->payment_date?->toDateString(), 'amount' => $payment->amount, 'reference' => $payment->reference])->values()->all() : [],
        ];
    }

    private function resolveBankTransactionRelationInfo(BankTransaction $transaction, ?\Illuminate\Support\Collection $refJournals = null): ?array
    {
        if ($transaction->project) {
            $rel = $transaction->project->relation_no ?: $transaction->project->grantAgreement?->relation_no;
            if ($rel) {
                return [
                    'relation_no' => $rel,
                    'project_name' => $transaction->project->name,
                    'project_code' => $transaction->project->code,
                ];
            }
        }

        if ($transaction->payment) {
            $rel = $this->resolvePaymentRelationInfo($transaction->payment);
            if ($rel) {
                return $rel;
            }
        }

        if ($refJournals && $transaction->reference && isset($refJournals[$transaction->reference])) {
            $journal = $refJournals[$transaction->reference];
            $lineWithRel = $journal->lines?->first(fn ($l) => !empty($l->relation_no));
            if ($lineWithRel) {
                return [
                    'relation_no' => $lineWithRel->relation_no,
                    'project_name' => $lineWithRel->project?->name ?? $lineWithRel->grantAgreement?->agreement_name,
                    'project_code' => $lineWithRel->project?->code,
                ];
            }
            $lineWithPrj = $journal->lines?->first(fn ($l) => !empty($l->project?->relation_no));
            if ($lineWithPrj) {
                return [
                    'relation_no' => $lineWithPrj->project->relation_no,
                    'project_name' => $lineWithPrj->project->name,
                    'project_code' => $lineWithPrj->project->code,
                ];
            }
        }

        return null;
    }

    private function resolvePaymentRelationInfo(Payment $payment): ?array
    {
        $exp = $payment->expenseRequest;
        if ($exp) {
            $rel = $exp->grantAgreement?->relation_no ?: $exp->project?->relation_no;
            if ($rel) {
                return [
                    'relation_no' => $rel,
                    'project_name' => $exp->project?->name ?? $exp->grantAgreement?->agreement_name,
                    'project_code' => $exp->project?->code,
                ];
            }
        }

        $poProject = $payment->supplierInvoice?->purchaseOrder?->purchaseRequest?->project;
        if ($poProject) {
            $rel = $poProject->relation_no ?: $poProject->grantAgreement?->relation_no;
            if ($rel) {
                return [
                    'relation_no' => $rel,
                    'project_name' => $poProject->name,
                    'project_code' => $poProject->code,
                ];
            }
        }

        if ($payment->journal && $payment->journal->lines) {
            $lineWithRel = $payment->journal->lines->first(fn ($l) => !empty($l->relation_no));
            if ($lineWithRel) {
                return [
                    'relation_no' => $lineWithRel->relation_no,
                    'project_name' => $lineWithRel->project?->name ?? $lineWithRel->grantAgreement?->agreement_name,
                    'project_code' => $lineWithRel->project?->code,
                ];
            }
            $lineWithPrj = $payment->journal->lines->first(fn ($l) => !empty($l->project?->relation_no));
            if ($lineWithPrj) {
                return [
                    'relation_no' => $lineWithPrj->project->relation_no,
                    'project_name' => $lineWithPrj->project->name,
                    'project_code' => $lineWithPrj->project->code,
                ];
            }
        }

        return null;
    }
}
