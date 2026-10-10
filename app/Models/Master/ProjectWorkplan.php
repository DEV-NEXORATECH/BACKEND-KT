<?php

namespace App\Models\Master;

use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;

class ProjectWorkplan extends Model
{
    use FiscalYearScopedTrait;

    protected $fillable = [
        'project_id', 'logframe_id', 'donor_id', 'grant_agreement_id', 'fiscal_year_id', 'activity_id', 'output_code', 'activity_code', 'activity',
        'responsible', 'start_date', 'end_date', 'baseline_start_date', 'baseline_end_date',
        'status', 'progress', 'baseline', 'notes', 'periods', 'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'baseline_start_date' => 'date',
        'baseline_end_date' => 'date',
        'progress' => 'decimal:2',
        'baseline' => 'decimal:2',
        'periods' => 'array',
        'is_active' => 'boolean',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function logframe() { return $this->belongsTo(ProjectLogframe::class, 'logframe_id'); }
    public function donor() { return $this->belongsTo(Donor::class); }
    public function grantAgreement() { return $this->belongsTo(GrantAgreement::class); }
    public function fiscalYear() { return $this->belongsTo(FiscalYear::class); }
    public function activity() { return $this->belongsTo(Activity::class); }
}
