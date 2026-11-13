<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'projects';

    protected $fillable = ['code', 'relation_no', 'program_id', 'grant_agreement_id', 'bank_account_id', 'name', 'theme', 'manager_name', 'start_date', 'end_date', 'budget_currency_id', 'total_budget', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['relation_number'];

    public function getRelationNumberAttribute(): string
    {
        return $this->relation_no ?: ($this->id ? 'KT-PRJ-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT) : 'KT-PRJ-0000');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('fiscal_year_context', function ($query) {
            if (! function_exists('request')) {
                return;
            }

            $request = request();
            $fiscalYearId = $request->query('fiscal_year_id')
                ?: $request->input('fiscal_year_id')
                ?: $request->header('X-Fiscal-Year-Id');

            if ($fiscalYearId) {
                $query->whereHas('fiscalYears', fn ($fiscalQuery) => $fiscalQuery->whereKey((int) $fiscalYearId));
            }
        });
    }

    public function program()
    {
        return $this->belongsTo(\App\Models\Master\Program::class, 'program_id');
    }

    public function grantAgreement()
    {
        return $this->belongsTo(\App\Models\Master\GrantAgreement::class, 'grant_agreement_id');
    }

    public function grantAgreementUnscoped()
    {
        return $this->belongsTo(\App\Models\Master\GrantAgreement::class, 'grant_agreement_id')
            ->withoutGlobalScope('fiscal_year_context');
    }

    public function budgetCurrency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'budget_currency_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(\App\Models\Master\BankAccount::class, 'bank_account_id')->withoutGlobalScope('fiscal_year_context');
    }

    public function activities()
    {
        return $this->hasMany(\App\Models\Master\Activity::class);
    }

    public function logframes()
    {
        return $this->hasMany(\App\Models\Master\ProjectLogframe::class);
    }

    public function workplans()
    {
        return $this->hasMany(\App\Models\Master\ProjectWorkplan::class);
    }

    public function budgetLines()
    {
        return $this->hasMany(\App\Models\Master\BudgetLine::class);
    }

    public function fiscalYears()
    {
        return $this->belongsToMany(FiscalYear::class, 'project_fiscal_year')
            ->orderBy('year');
    }

    public function partners()
    {
        return $this->belongsToMany(BeneficiaryPartner::class, 'project_partner', 'project_id', 'partner_id')->withPivot('role')->withTimestamps();
    }

    public function fundingSources()
    {
        return $this->belongsToMany(FundingSource::class, 'project_funding_source')->withPivot('amount')->withTimestamps();
    }

}
