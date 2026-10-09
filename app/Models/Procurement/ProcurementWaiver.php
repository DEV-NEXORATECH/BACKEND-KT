<?php

namespace App\Models\Procurement;

use App\Models\Master\FiscalYear;
use App\Models\Master\Project;
use App\Models\Master\Vendor;
use App\Models\User;
use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementWaiver extends Model
{
    use SoftDeletes, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'procurement_waivers';

    protected $fillable = [
        'fiscal_year_id',
        'waiver_number',
        'purchase_request_id',
        'project_id',
        'project_name',
        'vendor_id',
        'vendor_name',
        'total_amount',
        'description',
        'justification',
        'attachment_name',
        'attachment_path',
        'date',
        'status',
        'approved_by',
        'approved_at',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
