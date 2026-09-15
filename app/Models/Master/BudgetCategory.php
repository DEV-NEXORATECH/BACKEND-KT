<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetCategory extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'budget_categories';

    protected $fillable = ['parent_id', 'code', 'name', 'description', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(\App\Models\Master\BudgetCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(\App\Models\Master\BudgetCategory::class);
    }

    public function budgetLines()
    {
        return $this->hasMany(\App\Models\Master\BudgetLine::class);
    }

}