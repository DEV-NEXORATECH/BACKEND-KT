<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'taxes';

    protected $fillable = [
        'code',
        'name',
        'tax_type',
        'rate_percent',
        'description',
        'sales_gl_account_id',
        'purchase_gl_account_id',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function salesGlAccount()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'sales_gl_account_id');
    }

    public function purchaseGlAccount()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'purchase_gl_account_id');
    }

}