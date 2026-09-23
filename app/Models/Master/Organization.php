<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'organizations';

    protected $fillable = ['code', 'name', 'legal_name', 'npwp', 'email', 'website', 'pass_code', 'address', 'base_currency_id', 'fiscal_year_start_month', 'logo_url', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function officeLocations()
    {
        return $this->hasMany(\App\Models\Master\OfficeLocation::class);
    }

    public function departments()
    {
        return $this->hasMany(\App\Models\Master\Department::class);
    }

    public function baseCurrency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'base_currency_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function getRelatedRecordsCountAttribute(): int
    {
        return (int) ($this->officeLocations()->count() + $this->departments()->count());
    }
}
