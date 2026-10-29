<?php

namespace App\Models\Expense;

use App\Models\Master\BudgetLine;
use App\Models\Master\ExpenseCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseRequestLine extends Model
{
    protected $fillable = ['expense_request_id', 'expense_category_id', 'budget_line_id', 'description', 'quantity', 'unit', 'frequency', 'unit_price', 'amount', 'line_order'];

    protected $casts = ['quantity' => 'decimal:4', 'frequency' => 'decimal:4', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];

    public function expenseRequest(): BelongsTo { return $this->belongsTo(ExpenseRequest::class); }
    public function expenseCategory(): BelongsTo { return $this->belongsTo(ExpenseCategory::class); }
    public function budgetLine(): BelongsTo { return $this->belongsTo(BudgetLine::class); }
}
