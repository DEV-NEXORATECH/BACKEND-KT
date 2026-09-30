<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'employees';

    protected $fillable = [
        'employee_id_number', 'name', 'email', 'join_date', 'end_date', 'department_id', 'office_location_id',
        'position_id', 'position', 'employment_type', 'contract_total_fee', 'contract_total_days',
        'daily_cost_rate', 'hourly_cost_rate', 'default_rate_scheme',
        'bank_name', 'bank_account_number', 'bank_account_holder',
        'project_bank_name', 'project_bank_account_number',
        'is_active', 'created_by', 'updated_by', 'deleted_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'join_date' => 'date',
        'end_date' => 'date',
        'contract_total_fee' => 'decimal:2',
        'contract_total_days' => 'integer',
        'daily_cost_rate' => 'decimal:2',
        'hourly_cost_rate' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Employee $employee) {
            if ($employee->contract_total_fee !== null && $employee->contract_total_days && $employee->contract_total_days > 0) {
                if ($employee->daily_cost_rate === null || $employee->daily_cost_rate <= 0) {
                    $employee->daily_cost_rate = round((float) $employee->contract_total_fee / $employee->contract_total_days, 2);
                }
                if ($employee->hourly_cost_rate === null || $employee->hourly_cost_rate <= 0) {
                    $employee->hourly_cost_rate = round((float) $employee->daily_cost_rate / 8, 2);
                }
            } elseif ($employee->daily_cost_rate !== null && $employee->daily_cost_rate > 0) {
                if ($employee->hourly_cost_rate === null || $employee->hourly_cost_rate <= 0) {
                    $employee->hourly_cost_rate = round((float) $employee->daily_cost_rate / 8, 2);
                }
            }
        });
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\Master\Department::class, 'department_id');
    }

    public function officeLocation()
    {
        return $this->belongsTo(\App\Models\Master\OfficeLocation::class, 'office_location_id');
    }

    public function positionMaster()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'email', 'email');
    }
}
