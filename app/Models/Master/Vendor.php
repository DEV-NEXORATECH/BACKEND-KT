<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'vendors';

    protected $fillable = ['code', 'name', 'type', 'vendor_category_id', 'npwp', 'address', 'contact_person', 'phone', 'email', 'bank_name', 'bank_account_number', 'bank_account_holder', 'tax_id', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tax()
    {
        return $this->belongsTo(\App\Models\Master\Tax::class, 'tax_id');
    }

    public function category()
    {
        return $this->belongsTo(VendorCategory::class, 'vendor_category_id');
    }

}
