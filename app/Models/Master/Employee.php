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
        'employee_id_number',
        'name',
        'email',
        'department_id',
        'office_location_id',
        'position',
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

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(\App\Models\Master\Department::class, 'department_id');
    }

    public function officeLocation()
    {
        return $this->belongsTo(\App\Models\Master\OfficeLocation::class, 'office_location_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'email', 'email');
    }
}