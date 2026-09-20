<?php

namespace App\Models\Procurement;

use App\Models\Master\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorQuotation extends Model
{
    protected $fillable = ['rfq_id', 'vendor_id', 'quotation_number', 'quotation_date', 'total_amount', 'currency_code', 'terms', 'delivery_terms', 'technical_score', 'financial_score', 'total_score', 'status', 'notes', 'created_by'];

    protected $casts = ['quotation_date' => 'date', 'total_amount' => 'decimal:2', 'technical_score' => 'decimal:2', 'financial_score' => 'decimal:2', 'total_score' => 'decimal:2'];

    public function rfq(): BelongsTo { return $this->belongsTo(Rfq::class, 'rfq_id'); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
}
