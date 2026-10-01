<?php

namespace App\Models\Finance;

use App\Models\Master\BankAccount;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use FiscalYearScopedTrait;

    protected $fillable = ['fiscal_year_id', 'bank_account_id', 'payment_id', 'cash_advance_return_id', 'created_by', 'transaction_date', 'reference', 'description', 'debit', 'credit', 'status'];

    protected $casts = ['transaction_date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2'];

    public function bankAccount(): BelongsTo { return $this->belongsTo(BankAccount::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function cashAdvanceReturn(): BelongsTo { return $this->belongsTo(\App\Models\Expense\CashAdvanceReturn::class); }
}
