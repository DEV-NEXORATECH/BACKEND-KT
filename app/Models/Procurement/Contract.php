<?php

namespace App\Models\Procurement;

use App\Models\Master\Donor;
use App\Models\Master\FiscalYear;
use App\Models\Master\Project;
use App\Models\Master\Vendor;
use App\Models\User;
use App\Traits\AuditTrailTrait;
use App\Traits\FiscalYearScopedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes, AuditTrailTrait, FiscalYearScopedTrait;

    protected $table = 'procurement_contracts';

    protected $fillable = [
        'fiscal_year_id',
        'contract_number',
        'contract_type',
        'title',
        'vendor_id',
        'vendor_name',
        'vendor_contact',
        'project_id',
        'project_name',
        'donor_id',
        'donor_name',
        'start_date',
        'end_date',
        'total_value',
        'currency',
        'status',
        'payment_terms',
        'scope_of_work',
        'notes',
        'attachments',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_value' => 'decimal:2',
        'attachments' => 'array',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
