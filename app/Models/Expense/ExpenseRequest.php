<?php

namespace App\Models\Expense;

use App\Models\Accounting\Journal;
use App\Models\Master\Department;
use App\Models\Master\Project;
use App\Models\User;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseRequest extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['request_number', 'external_request_id', 'requester_id', 'project_id', 'department_id', 'expense_type', 'request_date', 'currency_code', 'exchange_rate', 'description', 'status', 'journal_id', 'paid_amount', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'decision_notes', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['request_date' => 'date', 'exchange_rate' => 'decimal:6', 'paid_amount' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];

    public function lines(): HasMany { return $this->hasMany(ExpenseRequestLine::class)->orderBy('line_order'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }

    public function getTotalAmountAttribute(): string
    {
        return number_format((float) $this->lines->sum('amount'), 2, '.', '');
    }
}
