<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class PrepaidExpenseAmortization extends Model
{
    protected $fillable = ['prepaid_expense_id', 'period', 'amortization_date', 'amount', 'journal_id', 'created_by'];
    protected $casts = ['amortization_date' => 'date', 'amount' => 'decimal:2'];

    public function journal() { return $this->belongsTo(Journal::class); }
}
