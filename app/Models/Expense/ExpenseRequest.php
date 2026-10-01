<?php

namespace App\Models\Expense;

use App\Models\Accounting\Journal;
use App\Models\Master\Department;
use App\Models\Master\Donor;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Program;
use App\Models\Master\FundingSource;
use App\Models\Master\DocumentType;
use App\Models\Master\Tax;
use App\Models\Master\Project;
use App\Models\User;
use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseRequest extends Model
{
    use SoftDeletes, AuditTrailTrait, FiscalYearScopedTrait;

    protected $fillable = ['fiscal_year_id', 'request_number', 'external_request_id', 'requester_id', 'donor_id', 'grant_agreement_id', 'program_id', 'project_id', 'department_id', 'funding_source_id', 'document_type_id', 'tax_id', 'expense_type', 'request_date', 'currency_code', 'exchange_rate', 'description', 'status', 'journal_id', 'settlement_journal_id', 'parent_expense_request_id', 'paid_amount', 'settled_amount', 'actual_expense_amount', 'return_amount', 'additional_reimbursement_amount', 'settlement_state', 'settlement_status', 'settlement_due_date', 'submitted_by', 'submitted_at', 'verified_by', 'verified_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'decision_notes', 'attachments', 'created_by', 'updated_by', 'deleted_by', 'functional_currency_code', 'original_amount', 'converted_amount', 'rate_source', 'rate_date', 'fx_gain_loss'];

    protected $casts = ['request_date' => 'date', 'settlement_due_date' => 'date', 'rate_date' => 'date', 'original_amount' => 'decimal:2', 'converted_amount' => 'decimal:2', 'fx_gain_loss' => 'decimal:2', 'exchange_rate' => 'decimal:6', 'paid_amount' => 'decimal:2', 'settled_amount' => 'decimal:2', 'actual_expense_amount' => 'decimal:2', 'return_amount' => 'decimal:2', 'additional_reimbursement_amount' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'attachments' => 'array'];

    public function lines(): HasMany { return $this->hasMany(ExpenseRequestLine::class)->orderBy('line_order'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function donor(): BelongsTo { return $this->belongsTo(Donor::class); }
    public function grantAgreement(): BelongsTo { return $this->belongsTo(GrantAgreement::class); }
    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
    public function fundingSource(): BelongsTo { return $this->belongsTo(FundingSource::class); }
    public function documentType(): BelongsTo { return $this->belongsTo(DocumentType::class); }
    public function tax(): BelongsTo { return $this->belongsTo(Tax::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
    public function settlementJournal(): BelongsTo { return $this->belongsTo(Journal::class, 'settlement_journal_id'); }
    public function cashAdvanceReturns(): HasMany { return $this->hasMany(CashAdvanceReturn::class); }
    public function cashAdvanceReimbursements(): HasMany { return $this->hasMany(CashAdvanceReimbursement::class); }

    public function getTotalAmountAttribute(): string
    {
        return number_format((float) $this->lines->sum('amount'), 2, '.', '');
    }
}
