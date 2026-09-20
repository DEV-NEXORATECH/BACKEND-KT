<?php

namespace App\Models\Budget;

use App\Models\Master\BudgetLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCommitment extends Model
{
    protected $fillable = [
        'budget_line_id',
        'source_type',
        'source_id',
        'reference',
        'amount',
        'status',
        'created_by',
        'released_by',
        'released_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'released_at' => 'datetime',
    ];

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
