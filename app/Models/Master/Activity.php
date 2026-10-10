<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\FiscalYearScopedTrait;

class Activity extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'activities';

    protected $fillable = ['fiscal_year_id', 'project_id', 'code', 'name', 'pic_name', 'start_date', 'end_date', 'target_output', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(\App\Models\Master\Project::class, 'project_id');
    }

}
