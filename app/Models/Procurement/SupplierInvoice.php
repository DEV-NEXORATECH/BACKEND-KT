<?php

namespace App\Models\Procurement;

use App\Models\Master\Vendor;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierInvoice extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['purchase_order_id', 'goods_receipt_id', 'vendor_id', 'invoice_number', 'invoice_date', 'due_date', 'status', 'match_status', 'total_amount', 'paid_amount', 'notes', 'attachments', 'journal_id', 'posted_at', 'posted_by', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['invoice_date' => 'date', 'due_date' => 'date', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'attachments' => 'array', 'posted_at' => 'datetime'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class);
    }
}
