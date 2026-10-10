<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DonorType extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'donor_types';

    protected $fillable = ['fiscal_year_id', 'code', 'name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function fiscalYear()
    {
        return $this->belongsTo(\App\Models\Master\FiscalYear::class, 'fiscal_year_id');
    }
}
