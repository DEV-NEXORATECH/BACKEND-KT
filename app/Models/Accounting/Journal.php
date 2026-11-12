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
            if (in_array($journal->source_type, ['grant_disbursement_receipt', 'bank_account_opening_balance'], true)) {
                return;
            }
            if (str_starts_with((string) $journal->journal_number, 'PAY-')) {
                return;
            }

            $lines = $journal->lines()->get();
            $accountIds = $lines->pluck('account_id')->filter()->unique()->values();
            if ($accountIds->isEmpty()) {
                return;
            }

            $banksByGl = BankAccount::withoutGlobalScope('fiscal_year_context')
                ->whereIn('gl_account_id', $accountIds)
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

                $exists = BankTransaction::withoutGlobalScope('fiscal_year_context')
                    ->where('bank_account_id', $bank->id)
                    ->where('reference', $journal->journal_number)
                    ->where('debit', $debit)
                    ->where('credit', $credit)
                    ->exists();

                if ($exists) {
                    continue;
                }

                BankTransaction::create([
                    'fiscal_year_id' => $journal->fiscal_year_id,
                    'bank_account_id' => $bank->id,
                    'project_id' => $line->project_id,
                    'transaction_date' => $journal->journal_date,
                    'reference' => $journal->journal_number,
                    'description' => $line->line_description ?: $journal->description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'status' => 'unmatched',
                    'created_by' => $journal->posted_by ?: $journal->created_by,
                ]);
            }
        });
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
