<?php
namespace App\Models\Master;

use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectLogframe extends Model
{
    use FiscalYearScopedTrait, SoftDeletes;

    protected $fillable = ['fiscal_year_id', 'project_id', 'relation_no', 'level', 'code', 'description', 'indicator', 'baseline', 'target', 'actual', 'unit', 'period_start', 'period_end', 'is_active'];
    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'is_active' => 'boolean'];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function fiscalYear(): BelongsTo { return $this->belongsTo(FiscalYear::class, 'fiscal_year_id'); }
}
