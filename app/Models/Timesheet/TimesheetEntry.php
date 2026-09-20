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

    protected $fillable = ['employee_id', 'user_id', 'entry_date', 'hours', 'description', 'donor_id', 'program_id', 'project_id', 'activity_id', 'department_id', 'is_billable', 'supervisor_id', 'status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'decision_notes', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'entry_date' => 'date',
        'hours' => 'decimal:2',
        'is_billable' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
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
