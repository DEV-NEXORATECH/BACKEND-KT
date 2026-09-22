<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'departments';

    protected $fillable = ['organization_id', 'parent_id', 'code', 'name', 'manager_name', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(\App\Models\Master\Organization::class, 'organization_id');
    }

    public function parent()
    {
        return $this->belongsTo(\App\Models\Master\Department::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(\App\Models\Master\Department::class, 'parent_id');
    }

    public function costCenters()
    {
        return $this->hasMany(\App\Models\Master\CostCenter::class);
    }

    public function employees()
    {
        return $this->hasMany(\App\Models\Master\Employee::class);
    }

    public function approvalMatrices()
    {
        return $this->hasMany(\App\Models\Master\ApprovalMatrix::class);
    }

    public function journalLines()
    {
        return $this->hasMany(\App\Models\Accounting\JournalLine::class);
    }

    public function purchaseRequests()
    {
        return $this->hasMany(\App\Models\Procurement\PurchaseRequest::class);
    }

    public function expenseRequests()
    {
        return $this->hasMany(\App\Models\Expense\ExpenseRequest::class);
    }

    public function timesheetEntries()
    {
        return $this->hasMany(\App\Models\Timesheet\TimesheetEntry::class);
    }

    public function updatedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function getRelatedRecordsCountAttribute(): int
    {
        $relations = ['children', 'costCenters', 'employees', 'approvalMatrices', 'journalLines', 'purchaseRequests', 'expenseRequests', 'timesheetEntries'];
        return (int) collect($relations)->sum(function (string $relation): int {
            try {
                return (int) $this->{$relation}()->count();
            } catch (\Throwable) {
                // Keep master data usable when an optional module migration is not deployed yet.
                return 0;
            }
        });
    }
}
