<?php

namespace App\Models\Accounting;

use App\Models\Master\Currency;
use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $fillable = [
        'journal_number',
        'journal_date',
        'journal_type',
        'reference',
        'description',
        'currency_id',
        'exchange_rate',
        'status',
        'reversal_of_id',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'posted_by',
        'posted_at',
        'reversed_by',
        'reversed_at',
        'attachments',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_order');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function getTotalDebitAttribute(): string
    {
        return number_format((float) $this->lines->sum('debit'), 2, '.', '');
    }

    public function getTotalCreditAttribute(): string
    {
        return number_format((float) $this->lines->sum('credit'), 2, '.', '');
    }
}
