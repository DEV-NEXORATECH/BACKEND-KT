<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetCategory extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'asset_categories';

    protected $fillable = ['code', 'name', 'useful_life_months', 'depreciation_method', 'asset_gl_account_id', 'depreciation_gl_account_id', 'accumulated_gl_account_id', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function assetGlAccount()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'asset_gl_account_id');
    }

    public function depreciationGlAccount()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'depreciation_gl_account_id');
    }

    public function accumulatedGlAccount()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'accumulated_gl_account_id');
    }

}