<?php

namespace App\Models\Procurement;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['purchase_order_id', 'grn_number', 'receipt_date', 'notes', 'status', 'received_by', 'received_at', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['receipt_date' => 'date', 'received_at' => 'datetime'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}
