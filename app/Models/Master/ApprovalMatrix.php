<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalMatrix extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'approval_matrices';

    protected $fillable = [
        'module',
        'level',
        'min_amount',
        'max_amount',
        'role_id',
        'project_id',
        'donor_id',
        'department_id',
        'approver_title',
        'is_conditional_project_manager',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'level' => 'integer',
        'is_conditional_project_manager' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(\App\Models\Role::class, 'role_id');
    }

    public function project()
    {
        return $this->belongsTo(\App\Models\Master\Project::class, 'project_id');
    }

    public function donor()
    {
        return $this->belongsTo(\App\Models\Master\Donor::class, 'donor_id');
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\Master\Department::class, 'department_id');
    }
}