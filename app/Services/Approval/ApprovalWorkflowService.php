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
        $currentUser = function_exists('request') ? request()->user() : null;
        if ($currentUser instanceof User && $this->isSuperAdmin($currentUser)) {
            return false;
        }

        return $this->matricesFor($module, $entity, $amount)->isNotEmpty();
    }

    /** @return array{managed: bool, completed: bool, next_level: int|null} */
    public function approve(string $module, Model $entity, User $user, ?string $notes = null): array
    {
        return DB::transaction(function () use ($module, $entity, $user, $notes) {
            $run = ApprovalWorkflowRun::query()
                ->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])
                ->lockForUpdate()->first();
            if (! $run) return ['managed' => false, 'completed' => true, 'next_level' => null];
            if ($run->status === 'approved') return ['managed' => true, 'completed' => true, 'next_level' => null];
            if ($run->status !== 'in_progress') throw ValidationException::withMessages(['approval' => 'Workflow approval tidak aktif.']);
            $run->load('actions.approvalMatrix');
            $actions = $run->actions()->where('level', $run->current_level)->where('status', 'pending')->get();
            $action = $actions->first(fn ($item) => $this->matchesApprover($item, $user, $run->approvable));
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
        DB::transaction(function () use ($module, $entity, $user, $notes) {
            $run = ApprovalWorkflowRun::query()
                ->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])
                ->lockForUpdate()->first();
            if (! $run) return;
            $run->load('actions.approvalMatrix');
            $action = $run->actions()->where('level', $run->current_level)->where('status', 'pending')->get()->first(fn ($item) => $this->matchesApprover($item, $user, $run->approvable));
            if (! $action) throw ValidationException::withMessages(['approval' => 'Anda bukan approver pada tahap approval saat ini.']);
            $action->update(['status' => 'rejected', 'action_by' => $user->id, 'action_at' => now(), 'notes' => $notes]);
            $run->update(['status' => 'rejected', 'completed_at' => now()]);
        });
    }

    public function userCanApproveAction(\App\Models\ApprovalWorkflowAction $action, User $user, ?Model $entity = null): bool
    {
        return $this->matchesApprover($action, $user, $entity);
    }

    private function runFor(string $module, Model $entity): ?ApprovalWorkflowRun
    {
        return ApprovalWorkflowRun::query()->with('actions.approvalMatrix')->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])->first();
    }

    private function matricesFor(string $module, Model $entity, float $amount)
    {
        $fiscalYearId = $entity->getAttribute('fiscal_year_id');

        // The nominal threshold determines the highest level reached. Every
        // lower level must then be completed in sequence before that level.
        // Example: a 120m transaction matches level 4 and therefore runs
        // levels 1, 2, 3, and 4 instead of jumping directly to level 4.
        $scopeMatrix = function ($query) use ($module, $fiscalYearId, $entity) {
            $query->where('module', $module)->where('is_active', true)
                ->where(function ($scope) use ($fiscalYearId) {
                // A transaction without a fiscal year may only use global matrices.
                // This prevents a 2025/2026-specific rule from being applied ambiguously.
                $scope->whereNull('fiscal_year_id');
                if ($fiscalYearId) {
                    $scope->orWhere('fiscal_year_id', $fiscalYearId);
                }
                })
                ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $entity->getAttribute('project_id')))
                ->where(fn ($q) => $q->whereNull('donor_id')->orWhere('donor_id', $entity->getAttribute('donor_id')))
                ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $entity->getAttribute('department_id')));
            return $query;
        };

        $highestLevel = (clone $scopeMatrix(ApprovalMatrix::query()))
            ->where('min_amount', '<=', $amount)
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount))
            ->max('level');

        if (! $highestLevel) return collect();

        return $scopeMatrix(ApprovalMatrix::query())
            ->where('level', '<=', $highestLevel)
            ->orderBy('level')->orderBy('id')->get();
    }

    private function matchesApprover($action, User $user, ?Model $entity = null): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $matrix = $action->approvalMatrix;
        if ($matrix?->is_conditional_project_manager) {
            $project = $entity && method_exists($entity, 'project') ? $entity->project : null;
            $projectManagerName = $project?->manager_name;
            if ($projectManagerName) {
                $employee = $user->employee;
                $names = array_filter([$user->name, $employee?->name]);
                return collect($names)->contains(fn ($name) => strcasecmp(trim((string) $name), trim((string) $projectManagerName)) === 0);
            }
        }

        $employeeId = $user->employee()->value('id');
        return ($action->user_id && (int) $action->user_id === (int) $user->id)
            || ($action->role_id && (int) $action->role_id === (int) $user->role_id)
            || ($action->employee_id && (int) $action->employee_id === (int) $employeeId);
    }

    private function isSuperAdmin(User $user): bool
    {
        $slug = strtolower((string) ($user->role?->slug ?? ''));
        return in_array($slug, ['super-admin', 'super_admin', 'superadmin'], true)
            || (bool) ($user->is_super_admin ?? false);
    }
}
