<?php

namespace App\Models\Procurement;

use App\Models\Master\Vendor;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rfq extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $table = 'rfqs';

    protected $fillable = ['purchase_request_id', 'rfq_number', 'rfq_date', 'submission_deadline', 'terms', 'status', 'attachments', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['rfq_date' => 'date', 'submission_deadline' => 'date', 'attachments' => 'array'];

    public function purchaseRequest(): BelongsTo { return $this->belongsTo(PurchaseRequest::class); }
    public function vendors(): BelongsToMany { return $this->belongsToMany(Vendor::class, 'rfq_vendors')->withPivot('status')->withTimestamps(); }
    public function quotations(): HasMany { return $this->hasMany(VendorQuotation::class, 'rfq_id'); }
    public function cba(): HasOne { return $this->hasOne(ComparativeBidAnalysis::class, 'rfq_id'); }
}
