<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FundingSource extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'funding_sources';

    protected $fillable = ['code', 'name', 'funding_type', 'restriction_type', 'donor_id', 'funding_intermediary', 'currency_id', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function grantAgreements()
    {
        return $this->hasMany(\App\Models\Master\GrantAgreement::class);
    }

    public function donor()
    {
        return $this->belongsTo(\App\Models\Master\Donor::class);
    }

    public function currency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class);
    }

}
