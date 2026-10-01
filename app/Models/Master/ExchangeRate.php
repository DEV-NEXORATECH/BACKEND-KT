<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExchangeRate extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'exchange_rates';

    protected $fillable = ['fiscal_year_id', 'date', 'from_currency_id', 'to_currency_id', 'rate', 'rate_type', 'source', 'reference', 'notes', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function fromCurrency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'from_currency_id');
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function toCurrency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'to_currency_id');
    }

}
