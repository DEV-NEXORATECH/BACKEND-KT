<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExchangeRate extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'exchange_rates';

    protected $fillable = ['date', 'from_currency_id', 'to_currency_id', 'rate', 'source', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function fromCurrency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'from_currency_id');
    }

    public function toCurrency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'to_currency_id');
    }

}