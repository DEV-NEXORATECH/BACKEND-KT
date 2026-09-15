<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingPeriod extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'accounting_periods';

    protected $fillable = ['fiscal_year_id', 'period_number', 'name', 'start_date', 'end_date', 'status', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function fiscalYear()
    {
        return $this->belongsTo(\App\Models\Master\FiscalYear::class, 'fiscal_year_id');
    }

}