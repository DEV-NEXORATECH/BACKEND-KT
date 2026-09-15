<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrantAgreement extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'grant_agreements';

    protected $fillable = ['grant_no', 'donor_id', 'funding_source_id', 'agreement_name', 'start_date', 'end_date', 'currency_id', 'grant_value', 'exchange_rate_contract', 'bank_account_id', 'status', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function donor()
    {
        return $this->belongsTo(\App\Models\Master\Donor::class, 'donor_id');
    }

    public function fundingSource()
    {
        return $this->belongsTo(\App\Models\Master\FundingSource::class, 'funding_source_id');
    }

    public function currency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'currency_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(\App\Models\Master\BankAccount::class, 'bank_account_id');
    }

    public function projects()
    {
        return $this->hasMany(\App\Models\Master\Project::class);
    }

    public function budgetLines()
    {
        return $this->hasMany(\App\Models\Master\BudgetLine::class);
    }

}