<?php

namespace App\Models\Expense;

use App\Models\Accounting\Journal;
use App\Models\Finance\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\AuditTrailTrait;

class CashAdvanceReimbursement extends Model
{
    use AuditTrailTrait;
    protected $fillable = ['expense_request_id', 'employee_id', 'project_id', 'budget_line_id', 'amount', 'status', 'payment_id', 'journal_id', 'paid_at', 'notes', 'created_by', 'updated_by'];
    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    public function expenseRequest(): BelongsTo { return $this->belongsTo(ExpenseRequest::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
}
