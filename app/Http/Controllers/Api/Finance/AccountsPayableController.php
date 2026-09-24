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
        $query = SupplierInvoice::with(['vendor:id,code,name,npwp', 'tax:id,code,name,tax_type,rate_percent', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'lines.purchaseOrderLine.budgetLine.glAccount']);
        
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

    public function submitInvoice(Request $request, SupplierInvoice $supplierInvoice, ApprovalWorkflowService $workflow): JsonResponse
    {
        if (! in_array($supplierInvoice->match_status, ['matched', 'partial_match'], true)) {
            throw ValidationException::withMessages(['match_status' => 'Invoice harus matched atau partial match sebelum diajukan approval AP.']);
        }
        if (in_array($supplierInvoice->status, ['posted', 'paid', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Invoice tidak dapat diajukan pada status saat ini.']);
        }

        $run = $workflow->start('ap', $supplierInvoice, (float) $supplierInvoice->total_amount, $request->user()->id);
        return response()->json(['success' => true, 'message' => $run ? 'Invoice diajukan ke Approval Matrix AP.' : 'Tidak ada Approval Matrix AP aktif; invoice siap diposting.', 'workflow_managed' => (bool) $run, 'data' => $this->formatInvoice($supplierInvoice->fresh(['vendor:id,code,name', 'purchaseOrder:id,po_number', 'goodsReceipt:id,grn_number', 'lines']))]);
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
            throw ValidationException::withMessages(['match_status' => 'Invoice harus matched atau partial match sebelum posting AP.']);
        }
        if (in_array($supplierInvoice->status, ['posted', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => 'Invoice sudah diposting.']);
        }

        $approval = $workflow->approve('ap', $supplierInvoice, $request->user(), $request->input('notes'));
        if (! $approval['managed'] && $workflow->requiresApproval('ap', $supplierInvoice, (float) $supplierInvoice->total_amount)) {
            throw ValidationException::withMessages(['approval' => 'Invoice harus disubmit ke Approval Matrix AP sebelum posting.']);
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

            if ($supplierInvoice->tax_id) {
                $tax = Tax::findOrFail($supplierInvoice->tax_id);
                $rate = (float) $tax->rate_percent;
                $taxable = round((float) $supplierInvoice->total_amount, 2);
                $taxAmount = round($taxable * $rate / 100, 2);
                TaxTransaction::firstOrCreate(
                    ['source_type' => SupplierInvoice::class, 'source_id' => $supplierInvoice->id],
                    [
                        'tax_id' => $tax->id,
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
                'created_by' => request()->user()->id,
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

    public function payments(Request $request): JsonResponse
    {
        $query = Payment::with(['supplierInvoice:id,invoice_number', 'bankAccount:id,bank_name,account_number']);
        app(\App\Services\Rbac\DataScopeService::class)->applyScope($query, $request->user(), 'created_by', null, null);
        return response()->json(['success' => true, 'data' => $query->latest('id')->get()]);
    }

    public function bankTransactions(Request $request): JsonResponse
    {
        $query = BankTransaction::with(['bankAccount:id,bank_name,account_number', 'payment:id,payment_number,payment_date,amount,reference,journal_id,bank_account_id', 'payment.journal:id,journal_number,journal_date,status', 'payment.supplierInvoice:id,invoice_number', 'payment.customerInvoice:id,invoice_number', 'payment.expenseRequest:id,request_number']);
        if (! app(\App\Services\Rbac\DataScopeService::class)->canAccessAll($request->user())) {
            $query->where(function ($scoped) use ($request) {
                $scoped->where('created_by', $request->user()->id)
                    ->orWhereHas('payment', fn ($payment) => $payment->where('created_by', $request->user()->id));
            });
        }

        $rows = $query->latest('transaction_date')->latest('id')->get()->map(function (BankTransaction $transaction) {
            $payment = $transaction->payment;
            return [
                'id' => $transaction->id,
                'bank_account' => $transaction->bankAccount ? ['id' => $transaction->bankAccount->id, 'bank_name' => $transaction->bankAccount->bank_name, 'account_number' => $transaction->bankAccount->account_number] : null,
                'transaction_date' => $transaction->transaction_date?->toDateString(),
                'reference' => $transaction->reference,
                'description' => $transaction->description,
                'debit' => (float) $transaction->debit,
                'credit' => (float) $transaction->credit,
                'status' => $transaction->status,
                'payment' => $payment ? [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'amount' => (float) $payment->amount,
                    'reference' => $payment->reference,
                    'invoice_number' => $payment->supplierInvoice?->invoice_number ?? $payment->customerInvoice?->invoice_number ?? $payment->expenseRequest?->request_number,
                    'journal' => $payment->journal ? ['journal_number' => $payment->journal->journal_number, 'journal_date' => $payment->journal->journal_date?->toDateString(), 'status' => $payment->journal->status] : null,
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
        $query = BankTransaction::query()->with('bankAccount:id,bank_name,account_number')->where('status', 'unmatched');
        $this->applyBankTransactionScope($query, $request);
        $user = $request->user();
        $scope = app(\App\Services\Rbac\DataScopeService::class);
        $items = $query->latest('transaction_date')->latest('id')->limit(100)->get()->map(function (BankTransaction $transaction) use ($user, $scope) {
            $amount = round(max((float) $transaction->debit, (float) $transaction->credit), 2);
            $candidates = Payment::query()
                ->where('bank_account_id', $transaction->bank_account_id)
                ->where('status', 'paid')
                ->where('amount', $amount)
                ->whereBetween('payment_date', [$transaction->transaction_date->copy()->subDays(7)->toDateString(), $transaction->transaction_date->copy()->addDays(7)->toDateString()]);
            if (! $scope->canAccessAll($user)) {
                $candidates->where('created_by', $user->id);
            }
            $candidateCount = $candidates->count();
            $candidateRows = $candidates->orderByDesc('payment_date')->limit(5)->get(['id', 'payment_number', 'payment_date', 'amount', 'reference']);
            return [
                'id' => $transaction->id,
                'transaction_date' => $transaction->transaction_date?->toDateString(),
                'reference' => $transaction->reference,
                'description' => $transaction->description,
                'debit' => (float) $transaction->debit,
                'credit' => (float) $transaction->credit,
                'bank_account' => $transaction->bankAccount,
                'candidate_count' => $candidateCount,
                'candidates' => $candidateRows->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'amount' => (float) $payment->amount,
                    'reference' => $payment->reference,
                ])->values(),
                'recommended_action' => $candidateCount === 1 ? 'auto_match' : ($candidateCount > 1 ? 'manual_match' : 'review'),
            ];
        })->values();

        return response()->json(['success' => true, 'count' => $items->count(), 'data' => $items]);
    }

    public function importBankTransactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'preview' => ['nullable', 'in:0,1,true,false'],
        ]);

        $previewOnly = filter_var($data['preview'] ?? false, FILTER_VALIDATE_BOOLEAN);
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
                    throw new \InvalidArgumentException('Baris harus memiliki debit atau credit.');
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
            throw ValidationException::withMessages(['amount' => 'Nilai transaksi bank harus lebih besar dari nol untuk auto-match.']);
        }

        $date = $bankTransaction->transaction_date;
        $payments = Payment::query()
            ->with(['supplierInvoice:id,invoice_number', 'customerInvoice:id,invoice_number'])
            ->where('bank_account_id', $bankTransaction->bank_account_id)
            ->where('status', 'paid')
            ->where('amount', $amount)
            ->whereBetween('payment_date', [$date->copy()->subDays(7)->toDateString(), $date->copy()->addDays(7)->toDateString()]);

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
        $previous = ['payment_id' => $bankTransaction->payment_id, 'status' => $bankTransaction->status];
        $bankTransaction->update(['payment_id' => $payment->id, 'status' => 'matched']);
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'banking',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => 'AUTO_MATCH',
            'entity_type' => BankTransaction::class,
            'entity_id' => $bankTransaction->id,
            'previous_values' => $previous,
            'new_values' => ['payment_id' => $payment->id, 'status' => 'matched'],
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
        $payment = Payment::findOrFail($data['payment_id']);
        if ((int) $payment->bank_account_id !== (int) $bankTransaction->bank_account_id) {
            throw ValidationException::withMessages(['payment_id' => 'Payment harus menggunakan bank account yang sama.']);
        }
        if (! app(\App\Services\Rbac\DataScopeService::class)->canAccessAll($request->user()) && (int) $payment->created_by !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mencocokkan payment milik user lain.');
        }

        $previous = ['payment_id' => $bankTransaction->payment_id, 'status' => $bankTransaction->status];
        $bankTransaction->update(['payment_id' => $payment->id, 'status' => 'matched']);
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'banking',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => 'MATCH',
            'entity_type' => BankTransaction::class,
            'entity_id' => $bankTransaction->id,
            'previous_values' => $previous,
            'new_values' => ['payment_id' => $payment->id, 'status' => 'matched'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true, 'message' => 'Transaksi bank berhasil dicocokkan ke payment.', 'data' => $bankTransaction->fresh(['bankAccount:id,bank_name,account_number', 'payment:id,payment_number,payment_date,amount,reference'])]);
    }

    public function reconcileBankTransaction(Request $request, BankTransaction $bankTransaction): JsonResponse
    {
        $this->ensureBankTransactionScope($request, $bankTransaction, 'merekonsiliasi');

        $data = $request->validate(['status' => ['required', 'in:matched,excluded,reconciled,unmatched']]);
        $previous = ['status' => $bankTransaction->status];
        $bankTransaction->update(['status' => $data['status']]);
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
        return response()->json(['success' => true, 'message' => 'Status rekonsiliasi diperbarui.', 'data' => $bankTransaction->fresh(['bankAccount:id,bank_name,account_number'])]);
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
            'invoice_number' => $invoice->invoice_number,
            'vendor_name' => $invoice->vendor?->name,
            'tax' => $invoice->tax ? ['id' => $invoice->tax->id, 'code' => $invoice->tax->code, 'name' => $invoice->tax->name, 'tax_type' => $invoice->tax->tax_type, 'rate_percent' => $invoice->tax->rate_percent] : null,
            'po_number' => $invoice->purchaseOrder?->po_number,
            'grn_number' => $invoice->goodsReceipt?->grn_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'match_status' => $invoice->match_status,
            'approval_status' => $approval?->status,
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'outstanding_amount' => round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2),
        ];
    }
}
