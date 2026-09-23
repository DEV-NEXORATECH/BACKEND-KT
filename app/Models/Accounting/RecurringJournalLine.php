<?php

namespace App\Models\Accounting;

use App\Models\Master\ChartOfAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringJournalLine extends Model
{
    protected $fillable = [
        'recurring_journal_id',
        'account_id',
        'description',
        'debit',
        'credit',
        'line_order',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
