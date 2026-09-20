<?php

namespace App\Models\Finance;

use App\Models\Accounting\Journal;
use App\Models\Master\BankAccount;
use App\Models\Master\PaymentMethod;
use App\Models\Master\Vendor;
use App\Models\Expense\ExpenseRequest;
use App\Models\Finance\CustomerInvoice;
use App\Models\Procurement\SupplierInvoice;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['payment_number', 'supplier_invoice_id', 'expense_request_id', 'customer_invoice_id', 'vendor_id', 'bank_account_id', 'payment_method_id', 'payment_date', 'amount', 'reference', 'status', 'journal_id', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function supplierInvoice(): BelongsTo { return $this->belongsTo(SupplierInvoice::class); }
    public function expenseRequest(): BelongsTo { return $this->belongsTo(ExpenseRequest::class); }
    public function customerInvoice(): BelongsTo { return $this->belongsTo(CustomerInvoice::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function bankAccount(): BelongsTo { return $this->belongsTo(BankAccount::class); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
}
