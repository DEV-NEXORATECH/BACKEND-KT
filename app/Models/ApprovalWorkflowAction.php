<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Master\ApprovalMatrix;

class ApprovalWorkflowAction extends Model
{
    protected $fillable = ['approval_workflow_run_id', 'approval_matrix_id', 'level', 'role_id', 'user_id', 'employee_id', 'status', 'action_by', 'action_at', 'notes'];
    protected $casts = ['action_at' => 'datetime'];

    public function run(): BelongsTo { return $this->belongsTo(ApprovalWorkflowRun::class, 'approval_workflow_run_id'); }

    public function approvalMatrix(): BelongsTo { return $this->belongsTo(ApprovalMatrix::class, 'approval_matrix_id'); }
}
