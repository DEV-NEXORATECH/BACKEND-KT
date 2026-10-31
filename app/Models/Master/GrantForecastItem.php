<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrantForecastItem extends Model
{
    use HasFactory;

    protected $table = 'grant_forecast_items';

    protected $fillable = [
        'disbursement_request_id',
        'grant_agreement_id',
        'budget_line_id',
        'period_label',
        'sub_output_code',
        'sub_output_name',
        'activity_code',
        'activity_name',
        'cost_category',
        'proposal_budget',
        'historical_expense',
        'adjustment_type',
        'adjustment_percent',
        'adjustment_idr',
        'forecast_amount',
        'notes',
    ];

    protected $casts = [
        'proposal_budget' => 'decimal:2',
        'historical_expense' => 'decimal:2',
        'adjustment_percent' => 'decimal:2',
        'adjustment_idr' => 'decimal:2',
        'forecast_amount' => 'decimal:2',
    ];

    public function disbursementRequest()
    {
        return $this->belongsTo(GrantDisbursementRequest::class, 'disbursement_request_id');
    }

    public function grantAgreement()
    {
        return $this->belongsTo(GrantAgreement::class, 'grant_agreement_id');
    }

    public function budgetLine()
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }
}

