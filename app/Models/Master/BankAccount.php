<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'bank_accounts';

    protected $fillable = ['organization_id', 'bank_name', 'account_number', 'account_name', 'swift_code', 'currency_id', 'gl_account_id', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(\App\Models\Master\Organization::class, 'organization_id');
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