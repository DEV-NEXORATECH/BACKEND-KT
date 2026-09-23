<?php

namespace App\Models;

use App\Models\Master\Project;
use App\Traits\AuditTrailTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAssignment extends Model
{
    use AuditTrailTrait;

    protected $fillable = [
        'user_id',
        'project_id',
        'role',
        'assigned_from',
        'assigned_to',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'assigned_from' => 'date',
        'assigned_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}