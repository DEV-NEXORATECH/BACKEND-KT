<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'expense_categories';

    protected $fillable = ['parent_id', 'code', 'name', 'default_gl_account_id', 'is_taxable', 'requires_receipt', 'requires_advance_settlement', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(\App\Models\Master\ExpenseCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(\App\Models\Master\ExpenseCategory::class);
    }

    public function defaultGlAccount()
    {
        return $this->belongsTo(\App\Models\Master\ChartOfAccount::class, 'default_gl_account_id');
    }

}