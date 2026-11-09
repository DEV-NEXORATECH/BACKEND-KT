<?php

namespace App\Models\Accounting;

use App\Models\Master\ChartOfAccount;
use App\Models\Master\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrepaidExpense extends Model
{
    use SoftDeletes;

    protected $fillable = ['reference', 'description', 'start_date', 'periods', 'total_amount', 'amortized_amount', 'status', 'prepaid_account_id', 'expense_account_id', 'created_by', 'project_id'];
    protected $casts = ['start_date' => 'date', 'total_amount' => 'decimal:2', 'amortized_amount' => 'decimal:2'];
    public function prepaidAccount() { return $this->belongsTo(ChartOfAccount::class, 'prepaid_account_id'); }
    public function expenseAccount() { return $this->belongsTo(ChartOfAccount::class, 'expense_account_id'); }
    public function project() { return $this->belongsTo(Project::class); }
}
