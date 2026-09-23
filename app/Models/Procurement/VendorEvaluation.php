<?php

namespace App\Models\Procurement;

use App\Models\Master\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorEvaluation extends Model
{
    protected $fillable = [
        'vendor_id', 'purchase_order_id', 'goods_receipt_id',
        'quality_score', 'delivery_score', 'price_score', 'service_score',
        'overall_score', 'evaluator_notes', 'evaluated_by',
    ];

    protected $casts = [
        'overall_score' => 'decimal:1',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
