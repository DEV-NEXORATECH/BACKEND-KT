<?php

namespace App\Models\Procurement;

use App\Models\Master\Department;
use App\Models\Master\Project;
use App\Models\Master\Vendor;
use App\Models\User;
use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait, FiscalYearScopedTrait;

    protected $fillable = [
        'fiscal_year_id',
        'pr_number',
        'request_date',
        'required_date',
        'country',
        'office',
        'requester_name',
        'requester_program',
        'requester_email',
        'requester_phone',
        'requester_address',
        'destination',
        'donor_name',
        'delivery_method',
        'requirements',
        'preferred_incoterm',
        'shipping_requirements',
        'requester_id',
        'department_id',
        'project_id',
        'vendor_id',
        'justification',
        'status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'decision_notes',
        'attachments',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'request_date' => 'date',
        'required_date' => 'date',
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class)->orderBy('line_order');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTotalAmountAttribute(): string
    {
        return number_format((float) $this->lines->sum('total_amount'), 2, '.', '');
    }
}
