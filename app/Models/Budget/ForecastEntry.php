<?php

namespace App\Models\Budget;

use App\Models\Master\Currency;
use App\Models\Master\FiscalYear;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use Illuminate\Database\Eloquent\Model;

class ForecastEntry extends Model
{
    protected $fillable = [
        'fiscal_year_id', 'grant_agreement_id', 'project_id', 'entry_type',
        'period_date', 'reference', 'amount', 'currency_id', 'exchange_rate',
        'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_date' => 'date',
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function fiscalYear() { return $this->belongsTo(FiscalYear::class); }
    public function grantAgreement() { return $this->belongsTo(GrantAgreement::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
}
