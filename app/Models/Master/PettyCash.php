<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCash extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'petty_cashes';

    protected $fillable = ['organization_id', 'office_location_id', 'code', 'name', 'custodian_name', 'currency_id', 'limit_amount', 'gl_account_id', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(\App\Models\Master\Organization::class, 'organization_id');
    }

    public function officeLocation()
    {
        return $this->belongsTo(\App\Models\Master\OfficeLocation::class, 'office_location_id');
    }

    public function currency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'currency_id');
    }

    public function glAccount()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'gl_account_id');
    }

}