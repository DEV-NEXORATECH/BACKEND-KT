<?php

namespace App\Services\Approval;

use App\Models\ApprovalWorkflowRun;
use App\Models\Master\ApprovalMatrix;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowService
{
    public function start(string $module, Model $entity, float $amount, ?int $submittedBy = null): ?ApprovalWorkflowRun
    {
        $matrices = $this->matricesFor($module, $entity, $amount);
        if ($matrices->isEmpty()) return null;

        return DB::transaction(function () use ($module, $entity, $matrices, $submittedBy) {
            $run = ApprovalWorkflowRun::firstOrCreate(
                ['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()],
                ['status' => 'in_progress', 'current_level' => $matrices->min('level'), 'submitted_by' => $submittedBy],
            );
            // A rejected document may be corrected and submitted again. Reset
            // the historical run actions so its next approval starts cleanly.
            if ($run->status === 'rejected') {
                $run->actions()->delete();
                $run->update(['status' => 'in_progress', 'current_level' => $matrices->min('level'), 'submitted_by' => $submittedBy, 'completed_at' => null]);
            }
            if ($run->actions()->doesntExist()) {
                $firstLevel = $matrices->min('level');
                foreach ($matrices as $matrix) {
                    $run->actions()->create([
                        'approval_matrix_id' => $matrix->id,
                        'level' => $matrix->level,
                        'role_id' => $matrix->role_id,
                        'user_id' => $matrix->user_id,
                        'employee_id' => $matrix->employee_id,
                        'status' => $matrix->level === $firstLevel ? 'pending' : 'queued',
                    ]);
                }
            }

            return $run->fresh('actions');
        });
    }

    public function requiresApproval(string $module, Model $entity, float $amount): bool
    {
        return $this->matricesFor($module, $entity, $amount)->isNotEmpty();
    }

    /** @return array{managed: bool, completed: bool, next_level: int|null} */
    public function approve(string $module, Model $entity, User $user, ?string $notes = null): array
    {
        $run = $this->runFor($module, $entity);
        if (! $run) return ['managed' => false, 'completed' => true, 'next_level' => null];
        if ($run->status !== 'in_progress') throw ValidationException::withMessages(['approval' => 'Workflow approval tidak aktif.']);

        return DB::transaction(function () use ($run, $user, $notes) {
            $actions = $run->actions()->where('level', $run->current_level)->where('status', 'pending')->get();
            $action = $actions->first(fn ($item) => $this->matchesApprover($item, $user));
            if (! $action) throw ValidationException::withMessages(['approval' => 'Anda bukan approver pada tahap approval saat ini.']);
            $action->update(['status' => 'approved', 'action_by' => $user->id, 'action_at' => now(), 'notes' => $notes]);

            if ($run->actions()->where('level', $run->current_level)->where('status', 'pending')->exists()) {
                return ['managed' => true, 'completed' => false, 'next_level' => $run->current_level];
            }
            $nextLevel = $run->actions()->where('level', '>', $run->current_level)->where('status', 'queued')->min('level');
            if ($nextLevel) {
                $run->actions()->where('level', $nextLevel)->where('status', 'queued')->update(['status' => 'pending']);
                $run->update(['current_level' => $nextLevel]);
                return ['managed' => true, 'completed' => false, 'next_level' => $nextLevel];
            }
            $run->update(['status' => 'approved', 'completed_at' => now()]);
            return ['managed' => true, 'completed' => true, 'next_level' => null];
        });
    }

    public function reject(string $module, Model $entity, User $user, string $notes): void
    {
        $run = $this->runFor($module, $entity);
        if (! $run) return;
        $action = $run->actions()->where('level', $run->current_level)->where('status', 'pending')->get()->first(fn ($item) => $this->matchesApprover($item, $user));
        if (! $action) throw ValidationException::withMessages(['approval' => 'Anda bukan approver pada tahap approval saat ini.']);
        DB::transaction(function () use ($run, $action, $user, $notes) {
            $action->update(['status' => 'rejected', 'action_by' => $user->id, 'action_at' => now(), 'notes' => $notes]);
            $run->update(['status' => 'rejected', 'completed_at' => now()]);
        });
    }

    private function runFor(string $module, Model $entity): ?ApprovalWorkflowRun
    {
        return ApprovalWorkflowRun::query()->with('actions')->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])->first();
    }

    private function matricesFor(string $module, Model $entity, float $amount)
    {
        return ApprovalMatrix::query()->where('module', $module)->where('is_active', true)
            ->where('min_amount', '<=', $amount)->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount))
            ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $entity->getAttribute('project_id')))
            ->where(fn ($q) => $q->whereNull('donor_id')->orWhere('donor_id', $entity->getAttribute('donor_id')))
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $entity->getAttribute('department_id')))
            ->orderBy('level')->orderBy('id')->get();
    }

    private function matchesApprover($action, User $user): bool
    {
        $employeeId = $user->employee()->value('id');
        return ($action->user_id && (int) $action->user_id === (int) $user->id)
            || ($action->role_id && (int) $action->role_id === (int) $user->role_id)
            || ($action->employee_id && (int) $action->employee_id === (int) $employeeId);
    }
}
