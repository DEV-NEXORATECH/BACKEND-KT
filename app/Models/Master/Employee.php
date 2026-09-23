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

<<<<<<< HEAD
    protected $fillable = ['employee_id_number', 'name', 'email', 'department_id', 'office_location_id', 'position_id', 'position', 'hourly_cost_rate', 'bank_name', 'bank_account_number', 'bank_account_holder', 'is_active', 'created_by', 'updated_by', 'deleted_by'];
=======
    protected $fillable = [
        'employee_id_number',
        'name',
        'email',
        'department_id',
        'office_location_id',
        'position',
        'hourly_cost_rate',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'project_bank_name',
        'project_bank_account_number',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
>>>>>>> be42f08fa6f7d14428c85dd1c42fead06d995dd8

    protected $casts = [
        'is_active' => 'boolean',
        'hourly_cost_rate' => 'decimal:2',
    ];

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
