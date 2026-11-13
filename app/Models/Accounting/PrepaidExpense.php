<?php

namespace App\Models\Accounting;

use App\Models\Master\ChartOfAccount;
use App\Models\Master\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrepaidExpense extends Model
{
    use SoftDeletes;

    protected $fillable = ['fiscal_year_id', 'reference', 'description', 'start_date', 'periods', 'total_amount', 'amortized_amount', 'status', 'prepaid_account_id', 'expense_account_id', 'created_by', 'project_id', 'grant_agreement_id', 'donor_id', 'program_id', 'relation_no', 'budget_line_id', 'bank_account_id', 'recognition_journal_id'];
    protected $casts = ['start_date' => 'date', 'total_amount' => 'decimal:2', 'amortized_amount' => 'decimal:2'];
    public function prepaidAccount() { return $this->belongsTo(ChartOfAccount::class, 'prepaid_account_id'); }
    public function expenseAccount() { return $this->belongsTo(ChartOfAccount::class, 'expense_account_id'); }
    public function project() { return $this->belongsTo(Project::class)->withoutGlobalScope('fiscal_year_context'); }
    public function grantAgreement() { return $this->belongsTo(\App\Models\Master\GrantAgreement::class)->withoutGlobalScope('fiscal_year_context'); }
    public function donor() { return $this->belongsTo(\App\Models\Master\Donor::class)->withoutGlobalScope('fiscal_year_context'); }
    public function program() { return $this->belongsTo(\App\Models\Master\Program::class); }
    public function budgetLine() { return $this->belongsTo(\App\Models\Master\BudgetLine::class)->withoutGlobalScope('fiscal_year_context'); }
    public function bankAccount() { return $this->belongsTo(\App\Models\Master\BankAccount::class)->withoutGlobalScope('fiscal_year_context'); }
    public function recognitionJournal() { return $this->belongsTo(Journal::class, 'recognition_journal_id'); }
    public function amortizations() { return $this->hasMany(PrepaidExpenseAmortization::class)->orderBy('period'); }
}
