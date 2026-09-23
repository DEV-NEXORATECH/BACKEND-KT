<?php

namespace App\Models\Procurement;

use App\Models\Master\Vendor;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['purchase_request_id', 'rfq_id', 'cba_id', 'vendor_id', 'po_number', 'contract_number', 'po_date', 'contract_date', 'terms', 'status', 'attachments', 'approved_by', 'approved_at', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['po_date' => 'date', 'contract_date' => 'date', 'attachments' => 'array', 'approved_at' => 'datetime'];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class, 'rfq_id');
    }

    public function cba(): BelongsTo
    {
        return $this->belongsTo(ComparativeBidAnalysis::class, 'cba_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_order');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function amendments(): HasMany { return $this->hasMany(PoAmendment::class); }
    public function vendorEvaluations(): HasMany { return $this->hasMany(VendorEvaluation::class); }

    public function getTotalAmountAttribute(): string
    {
        return number_format((float) $this->lines->sum('total_amount'), 2, '.', '');
    }
}
