<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalWorkflowRun extends Model
{
    protected $fillable = ['module', 'approvable_type', 'approvable_id', 'status', 'current_level', 'submitted_by', 'completed_at'];
    protected $casts = ['completed_at' => 'datetime'];

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowAction::class)->orderBy('level')->orderBy('id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }
}
