<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'currencies';

    protected $fillable = ['code', 'name', 'symbol', 'decimal_places', 'is_base_currency', 'fiscal_year_id', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function fiscalYear()
    {
        return $this->belongsTo(\App\Models\Master\FiscalYear::class, 'fiscal_year_id');
    }

}
