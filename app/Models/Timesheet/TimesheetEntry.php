<?php

namespace App\Models\Timesheet;

use App\Models\Master\Activity;
use App\Models\Master\Department;
use App\Models\Master\Donor;
use App\Models\Master\Employee;
use App\Models\Master\Program;
use App\Models\Master\Project;
use App\Models\User;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimesheetEntry extends Model
{
    use SoftDeletes, AuditTrailTrait;

    protected $fillable = ['employee_id', 'user_id', 'worker_type', 'vendor_name', 'contract_reference', 'invoice_reference', 'billing_mode', 'fee_total', 'work_days', 'rate_per_day', 'rate_per_hour', 'break_hours', 'normal_hours', 'payable_amount', 'entry_date', 'hours', 'description', 'work_area', 'workstream', 'donor_id', 'program_id', 'project_id', 'activity_id', 'department_id', 'is_billable', 'supervisor_id', 'status', 'journal_id', 'posted_by', 'posted_at', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'decision_notes', 'prepared_signature', 'prepared_signed_at', 'approved_signature', 'approved_signed_at', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'entry_date' => 'date',
        'hours' => 'decimal:2',
        'fee_total' => 'decimal:2',
        'work_days' => 'decimal:2',
        'rate_per_day' => 'decimal:2',
        'rate_per_hour' => 'decimal:2',
        'break_hours' => 'decimal:2',
        'normal_hours' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'is_billable' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'posted_at' => 'datetime',
        'prepared_signed_at' => 'datetime',
        'approved_signed_at' => 'datetime',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function donor(): BelongsTo { return $this->belongsTo(Donor::class); }
    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function activity(): BelongsTo { return $this->belongsTo(Activity::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function supervisor(): BelongsTo { return $this->belongsTo(User::class, 'supervisor_id'); }
}
