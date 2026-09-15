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

    protected $fillable = ['code', 'program_id', 'grant_agreement_id', 'name', 'manager_name', 'start_date', 'end_date', 'budget_currency_id', 'total_budget', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function program()
    {
        return $this->belongsTo(\App\Models\Master\Program::class, 'program_id');
    }

    public function grantAgreement()
    {
        return $this->belongsTo(\App\Models\Master\GrantAgreement::class, 'grant_agreement_id');
    }

    public function budgetCurrency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'budget_currency_id');
    }

    public function activities()
    {
        return $this->hasMany(\App\Models\Master\Activity::class);
    }

    public function budgetLines()
    {
        return $this->hasMany(\App\Models\Master\BudgetLine::class);
    }

}