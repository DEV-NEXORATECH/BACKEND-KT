<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    protected $fillable = ['goods_receipt_id', 'purchase_order_line_id', 'received_quantity'];

    protected $casts = ['received_quantity' => 'decimal:2'];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
