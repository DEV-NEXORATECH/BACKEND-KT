<?php

namespace App\Models\Accounting;

use App\Models\Finance\BankTransaction;
use App\Models\Master\BankAccount;
use App\Models\Master\Currency;
use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $fillable = [
        'fiscal_year_id',
        'journal_number',
        'journal_date',
        'journal_type',
        'reference',
        'source_type', 'source_id',
        'description',
        'currency_id',
        'exchange_rate',
        'functional_currency_code', 'original_amount', 'converted_amount', 'rate_source', 'rate_date', 'fx_gain_loss',
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
        'original_amount' => 'decimal:2', 'converted_amount' => 'decimal:2', 'fx_gain_loss' => 'decimal:2', 'rate_date' => 'date',
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Journal $journal) {
            if ($journal->original_amount !== null && $journal->exchange_rate !== null) {
                $journal->converted_amount = round((float) $journal->original_amount * (float) $journal->exchange_rate, 2);
                $journal->rate_date ??= $journal->journal_date?->toDateString() ?? now()->toDateString();
                $journal->rate_source ??= 'transaction_snapshot';
            }
        });

        static::updated(function (Journal $journal) {
            if (! $journal->wasChanged('status') || $journal->status !== 'posted') {
                return;
            }
            $journal->syncBankTransactions();
        });
    }

    public function syncBankTransactions(): void
    {
        if ($this->status !== 'posted') {
            return;
        }
        if (in_array($this->source_type, ['grant_disbursement_receipt', 'bank_account_opening_balance'], true)) {
            return;
        }
        if (str_starts_with((string) $this->journal_number, 'PAY-')) {
            return;
        }

        $lines = $this->lines()->get();
        $accountIds = $lines->pluck('account_id')->filter()->unique()->values();
        if ($accountIds->isEmpty()) {
            return;
        }

        $banksByGl = BankAccount::withoutGlobalScope('fiscal_year_context')
            ->whereIn('gl_account_id', $accountIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('gl_account_id');

        if ($banksByGl->isEmpty()) {
            return;
        }

        foreach ($lines as $line) {
            $bank = $banksByGl->get($line->account_id);
            if (! $bank) {
                continue;
            }
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $existing = BankTransaction::withoutGlobalScope('fiscal_year_context')
                ->where('bank_account_id', $bank->id)
                ->where(function ($query) use ($line) {
                    $query->where('journal_line_id', $line->id)
                        ->orWhere(function ($legacy) {
                            $legacy->whereNull('journal_line_id')
                                ->where(function ($journalMatch) {
                                    $journalMatch->where('journal_id', $this->id)
                                        ->orWhere(function ($referenceMatch) {
                                            $referenceMatch->whereNull('journal_id')
                                    ->where('reference', $this->journal_number);
                                        });
                                });
                        });
                })
                ->where('debit', $debit)
                ->where('credit', $credit)
                ->first();

            if ($existing) {
                if (! $existing->journal_id || ! $existing->journal_line_id) {
                    $existing->update([
                        'journal_id' => $this->id,
                        'journal_line_id' => $line->id,
                    ]);
                }
                continue;
            }

            BankTransaction::create([
                'fiscal_year_id' => $this->fiscal_year_id,
                'bank_account_id' => $bank->id,
                'journal_id' => $this->id,
                'journal_line_id' => $line->id,
                'project_id' => $line->project_id,
                'transaction_date' => $this->journal_date,
                'reference' => $this->journal_number,
                'description' => $line->line_description ?: $this->description,
                'debit' => $debit,
                'credit' => $credit,
                'status' => 'unmatched',
                'created_by' => $this->posted_by ?: $this->created_by,
            ]);
        }
    }

    // A page filter must not make a transaction in another stored year uneditable/unreadable.
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)->withoutGlobalScope('fiscal_year_context');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_order');
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
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
