<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowEvent extends Model
{
    protected $fillable = [
        'approval_workflow_run_id', 'approval_workflow_action_id', 'event',
        'from_level', 'to_level', 'version', 'actor_id', 'actor_name',
        'actor_position', 'notes', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowRun::class, 'approval_workflow_run_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
