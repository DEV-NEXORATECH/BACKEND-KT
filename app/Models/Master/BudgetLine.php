<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetLine extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'budget_lines';

    protected $fillable = ['grant_agreement_id', 'project_id', 'budget_category_id', 'line_code', 'description', 'unit_of_measure_id', 'unit_price', 'quantity', 'total_amount', 'gl_account_id', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function grantAgreement()
    {
        return $this->belongsTo(\App\Models\Master\GrantAgreement::class, 'grant_agreement_id');
    }

    public function project()
    {
        return $this->belongsTo(\App\Models\Master\Project::class, 'project_id');
    }

    public function budgetCategory()
    {
        return $this->belongsTo(\App\Models\Master\BudgetCategory::class, 'budget_category_id');
    }

    public function unitOfMeasure()
    {
        return $this->belongsTo(\App\Models\Master\UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function glAccount()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'gl_account_id');
    }

}