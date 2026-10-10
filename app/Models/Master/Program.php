<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'programs';

    protected $fillable = ['fiscal_year_id', 'code', 'name', 'objective', 'manager_name', 'start_date', 'end_date', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function projects()
    {
        return $this->hasMany(\App\Models\Master\Project::class);
    }

    public function fiscalYear()
    {
        return $this->belongsTo(\App\Models\Master\FiscalYear::class, 'fiscal_year_id');
    }

    public function grantAgreement()
    {
        return $this->belongsTo(\App\Models\Master\GrantAgreement::class, 'grant_agreement_id');
    }

    public function grantAgreements()
    {
        return $this->hasManyThrough(\App\Models\Master\GrantAgreement::class, \App\Models\Master\Project::class, 'program_id', 'id', 'id', 'grant_agreement_id');
    }
}
