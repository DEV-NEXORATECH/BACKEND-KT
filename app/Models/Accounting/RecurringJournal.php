<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringJournal extends Model
{
    protected $fillable = [
        'name',
        'frequency',
        'next_run',
        'ends_at',
        'description',
        'is_active',
    ];

    protected $casts = [
        'next_run' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(RecurringJournalLine::class)->orderBy('line_order');
    }
}
