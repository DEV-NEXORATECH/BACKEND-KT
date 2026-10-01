<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'bank_accounts';

    protected $fillable = ['fiscal_year_id', 'organization_id', 'bank_name', 'account_number', 'account_name', 'swift_code', 'description', 'currency_id', 'gl_account_id', 'opening_balance', 'opening_balance_date', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
    ];

    public function organization()
    {
        return $this->belongsTo(\App\Models\Master\Organization::class, 'organization_id');
    }

    public function currency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'currency_id');
    }

    public function glAccount()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'gl_account_id');
    }

    public function transactions()
    {
        return $this->hasMany(\App\Models\Finance\BankTransaction::class, 'bank_account_id');
    }

    public function projects()
    {
        return $this->hasMany(\App\Models\Master\Project::class, 'bank_account_id');
    }

}
