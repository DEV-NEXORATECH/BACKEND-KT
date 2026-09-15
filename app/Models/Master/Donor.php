<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Donor extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'donors';

    protected $fillable = ['code', 'name', 'type', 'country', 'contact_person', 'email', 'phone', 'default_currency_id', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function defaultCurrency()
    {
        return $this->belongsTo(\App\Models\Master\Currency::class, 'default_currency_id');
    }

    public function grantAgreements()
    {
        return $this->hasMany(\App\Models\Master\GrantAgreement::class);
    }

}