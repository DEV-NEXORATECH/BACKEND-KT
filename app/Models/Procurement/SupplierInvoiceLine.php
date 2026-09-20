<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceLine extends Model
{
    protected $fillable = ['supplier_invoice_id', 'purchase_order_line_id', 'item_description', 'quantity', 'unit_price', 'total_amount'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total_amount' => 'decimal:2'];

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
