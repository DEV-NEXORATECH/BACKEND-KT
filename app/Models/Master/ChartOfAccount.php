<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'chart_of_accounts';

    protected $fillable = ['parent_id', 'code', 'name', 'account_type', 'normal_balance', 'level', 'is_header', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(\App\Models\Master\ChartOfAccount::class);
    }

}