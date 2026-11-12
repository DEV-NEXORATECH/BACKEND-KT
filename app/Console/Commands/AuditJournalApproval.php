<?php

namespace App\Console\Commands;

use App\Models\Accounting\Journal;
use App\Models\ApprovalWorkflowRun;
use App\Models\User;
use App\Services\Approval\{ApprovalIdentityResolver, ApprovalWorkflowService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AuditJournalApproval extends Command
{
    protected $signature = 'journals:audit-approval {number} {--user-id=} {--user-name=}';
    protected $description = 'Read-only audit of journal, workflow snapshot, matrix and canonical approver identities';

    public function handle(ApprovalWorkflowService $workflow, ApprovalIdentityResolver $identity): int
    {
        $journal = Journal::withoutGlobalScope('fiscal_year_context')->where('journal_number', $this->argument('number'))->firstOrFail();
        $run = ApprovalWorkflowRun::where('module', 'journal')->where('approvable_type', Journal::class)->where('approvable_id', $journal->id)->with('actions.approvalMatrix')->first();
        $users = User::query()->when($this->option('user-id'), fn ($q, $id) => $q->whereKey($id))
            ->when($this->option('user-name'), fn ($q, $name) => $q->where('name', $name));
        $candidates = ($this->option('user-id') || $this->option('user-name')) ? $users->get() : collect();
        $this->line(json_encode([
            'journal' => $journal->only(['id', 'journal_number', 'status', 'fiscal_year_id', 'journal_date', 'created_by', 'submitted_by']),
            'run' => $run?->only(['id', 'status', 'current_level', 'submitted_by']),
            'actions' => $run?->actions->map(fn ($a) => [
                'snapshot' => $a->only(['id', 'level', 'status', 'user_id', 'employee_id', 'role_id', 'action_by', 'approval_matrix_id']),
                'live_matrix' => $a->approvalMatrix?->only(['id', 'level', 'user_id', 'employee_id', 'role_id', 'project_id', 'donor_id', 'department_id', 'fiscal_year_id', 'is_conditional_project_manager', 'is_active']),
            ]),
            'candidate_users' => $candidates->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'role_id' => $u->role_id, 'stored_employee_id' => $u->employee_id,
                'resolved_employee_id' => $identity->employeeId($u), 'is_active' => $u->is_active,
                'has_post_permission' => $u->hasPermission('accounting.journal.post'),
                'progress' => $workflow->progressFor('journal', $journal, $u),
            ]),
            'fiscal_year_foreign_keys' => Schema::getForeignKeys('journals'),
            'journal_indexes' => Schema::getIndexes('journals'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
