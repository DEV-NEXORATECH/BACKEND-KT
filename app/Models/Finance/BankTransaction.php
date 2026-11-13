<?php

namespace App\Models\Finance;

use App\Models\Master\BankAccount;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use FiscalYearScopedTrait, SoftDeletes;

    protected $fillable = ['fiscal_year_id', 'bank_account_id', 'project_id', 'payment_id', 'journal_id', 'journal_line_id', 'cash_advance_return_id', 'created_by', 'transaction_date', 'reference', 'description', 'debit', 'credit', 'difference', 'remarks', 'status'];

    protected $casts = ['transaction_date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2', 'difference' => 'decimal:2'];

    public function bankAccount(): BelongsTo { return $this->belongsTo(BankAccount::class)->withoutGlobalScope('fiscal_year_context'); }
    public function project(): BelongsTo { return $this->belongsTo(\App\Models\Master\Project::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function journal(): BelongsTo { return $this->belongsTo(\App\Models\Accounting\Journal::class); }
    public function journalLine(): BelongsTo { return $this->belongsTo(\App\Models\Accounting\JournalLine::class); }
    public function cashAdvanceReturn(): BelongsTo { return $this->belongsTo(\App\Models\Expense\CashAdvanceReturn::class); }
}
