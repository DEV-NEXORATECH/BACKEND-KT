<?php

namespace App\Models\Expense;

use App\Models\Master\BudgetLine;
use App\Models\Master\Donor;
use App\Models\Master\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseRequestFunding extends Model
{
    protected $fillable = ['expense_request_id', 'donor_id', 'program_id', 'budget_line_id', 'amount', 'currency_code', 'exchange_rate'];
    protected $casts = ['amount' => 'decimal:2', 'exchange_rate' => 'decimal:6'];

    public function expenseRequest(): BelongsTo { return $this->belongsTo(ExpenseRequest::class); }
    public function donor(): BelongsTo { return $this->belongsTo(Donor::class); }
    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
    public function budgetLine(): BelongsTo { return $this->belongsTo(BudgetLine::class); }
}
