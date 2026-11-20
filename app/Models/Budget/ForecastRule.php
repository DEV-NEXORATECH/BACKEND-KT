<?php

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;

class ForecastRule extends Model
{
    protected $fillable = [
        'fiscal_year_id', 'grant_agreement_id', 'project_id', 'relation_no',
        'cost_category', 'direction', 'mode', 'value', 'created_by',
    ];

    protected $casts = ['value' => 'decimal:2'];
}
