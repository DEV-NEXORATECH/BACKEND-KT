<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Donor extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'donors';

    protected $fillable = ['code', 'name', 'type', 'donor_type_id', 'country', 'contact_person', 'email', 'phone', 'default_currency_id', 'fiscal_year_id', 'status', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Donor $donor) {
            if ($donor->isDirty('status') && $donor->status !== null) {
                $donor->is_active = $donor->status === 'active';
            } elseif ($donor->isDirty('is_active')) {
                $donor->status = $donor->is_active ? 'active' : 'inactive';
            }
        });
    }

    public function defaultCurrency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'default_currency_id');
    }

    public function donorType()
    {
        return $this->belongsTo(DonorType::class, 'donor_type_id');
    }

    public function fiscalYear()
    {
        return $this->belongsTo(\App\Models\Master\FiscalYear::class, 'fiscal_year_id');
    }

    public function grantAgreements()
    {
        return $this->hasMany(\App\Models\Master\GrantAgreement::class);
    }

}
