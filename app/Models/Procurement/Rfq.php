<?php

namespace App\Models\Procurement;

use App\Models\Master\Vendor;
use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rfq extends Model
{
    use SoftDeletes, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'rfqs';

    protected $fillable = [
        'fiscal_year_id', 'purchase_request_id', 'rfq_number', 'rfq_date', 'submission_deadline',
        'required_delivery_date', 'delivery_method', 'payment_terms', 'supplier_vendor_id',
        'supplier_contact_name', 'supplier_email', 'supplier_phone', 'supplier_fax', 'supplier_mobile', 'supplier_address',
        'procurement_contact_name', 'procurement_contact_email', 'procurement_address',
        'other_tax', 'other_charges', 'checked_by', 'budget_holder_name', 'budget_holder_title',
        'supplier_acceptance_name', 'supplier_acceptance_title', 'terms', 'status', 'attachments',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'rfq_date' => 'date',
        'submission_deadline' => 'date',
        'required_delivery_date' => 'date',
        'other_tax' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'attachments' => 'array',
    ];

    public function purchaseRequest(): BelongsTo { return $this->belongsTo(PurchaseRequest::class); }
    public function vendors(): BelongsToMany { return $this->belongsToMany(Vendor::class, 'rfq_vendors')->withPivot('status')->withTimestamps(); }
    public function quotations(): HasMany { return $this->hasMany(VendorQuotation::class, 'rfq_id'); }
    public function cba(): HasOne { return $this->hasOne(ComparativeBidAnalysis::class, 'rfq_id'); }
}
