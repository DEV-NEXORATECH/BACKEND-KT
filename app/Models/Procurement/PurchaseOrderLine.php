<?php

namespace App\Models\Procurement;

use App\Models\Master\BudgetLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    protected $fillable = ['purchase_order_id', 'purchase_request_line_id', 'budget_line_id', 'item_description', 'quantity', 'unit_price', 'total_amount', 'line_order'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total_amount' => 'decimal:2'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseRequestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class);
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }
}
