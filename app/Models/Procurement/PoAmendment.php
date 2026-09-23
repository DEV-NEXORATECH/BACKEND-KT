<?php

namespace App\Models\Procurement;

use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoAmendment extends Model
{
    use AuditTrailTrait;

    protected $fillable = [
        'purchase_order_id', 'amendment_number', 'version', 'changes',
        'reason', 'status', 'approved_by', 'approved_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'changes' => 'array',
        'approved_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
