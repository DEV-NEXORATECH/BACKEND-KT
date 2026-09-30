<?php

namespace App\Models\Expense;

use App\Models\Accounting\Journal;
use App\Models\Finance\BankTransaction;
use App\Models\Master\BankAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvanceReturn extends Model
{
    protected $fillable = ['expense_request_id', 'employee_id', 'bank_account_id', 'return_amount', 'payment_method', 'return_date', 'reference_no', 'status', 'journal_id', 'bank_transaction_id', 'notes', 'created_by', 'updated_by'];
    protected $casts = ['return_amount' => 'decimal:2', 'return_date' => 'date'];
    public function expenseRequest(): BelongsTo { return $this->belongsTo(ExpenseRequest::class); }
    public function bankAccount(): BelongsTo { return $this->belongsTo(BankAccount::class); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
    public function bankTransaction(): BelongsTo { return $this->belongsTo(BankTransaction::class); }
}
