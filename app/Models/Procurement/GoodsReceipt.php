<?php

namespace App\Models\Procurement;

use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use SoftDeletes, AuditTrailTrait, FiscalYearScopedTrait;

    protected $fillable = ['fiscal_year_id', 'purchase_order_id', 'grn_number', 'receipt_date', 'notes', 'status', 'attachments', 'received_by', 'received_at', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['receipt_date' => 'date', 'attachments' => 'array', 'received_at' => 'datetime'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}
