<?php

namespace App\Models\Procurement;

use App\Models\Master\Vendor;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComparativeBidAnalysis extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['rfq_id', 'cba_number', 'analysis_date', 'selected_vendor_id', 'selected_quotation_id', 'selection_reason', 'status', 'approved_by', 'approved_at', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['analysis_date' => 'date', 'approved_at' => 'datetime'];

    public function rfq(): BelongsTo { return $this->belongsTo(Rfq::class, 'rfq_id'); }
    public function selectedVendor(): BelongsTo { return $this->belongsTo(Vendor::class, 'selected_vendor_id'); }
    public function selectedQuotation(): BelongsTo { return $this->belongsTo(VendorQuotation::class, 'selected_quotation_id'); }
}
