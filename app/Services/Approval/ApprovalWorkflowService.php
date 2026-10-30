<?php

namespace App\Services\Approval;

use App\Models\ApprovalWorkflowRun;
use App\Models\ApprovalWorkflowEvent;
use App\Models\Master\ApprovalMatrix;
use App\Models\Master\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowService
{
    public function start(string $module, Model $entity, float $amount, ?int $submittedBy = null): ?ApprovalWorkflowRun
    {
        $matrices = $this->matricesFor($module, $entity, $amount);
        if ($module === 'expense' && $submittedBy) {
            $requester = User::with('employee')->find($submittedBy);
            if ($requester) {
                $matrices = $matrices->reject(fn ($matrix) => ($matrix->user_id && (int) $matrix->user_id === (int) $submittedBy)
                    || ($matrix->employee_id && (int) $matrix->employee_id === (int) $requester->employee_id)
                    || ($matrix->role_id && (int) $matrix->role_id === (int) $requester->role_id))->values();
            }
        }
        // A person may be represented by both an explicit user and employee
        // matrix row. Keep the first matching row so one person never appears
        // twice in a single approval chain.
        $seenPeople = [];
        $matrices = $matrices->filter(function ($matrix) use (&$seenPeople) {
            $identity = $matrix->user_id
                ? 'user:' . (int) $matrix->user_id
                : ($matrix->employee_id ? 'employee:' . (int) $matrix->employee_id : null);
            if ($identity === null) return true;
            if (isset($seenPeople[$identity])) return false;
            $seenPeople[$identity] = true;
            return true;
        })->values();
        if ($matrices->isEmpty()) return null;

        return DB::transaction(function () use ($module, $entity, $matrices, $submittedBy) {
            $run = ApprovalWorkflowRun::firstOrCreate(
                ['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()],
                ['status' => 'in_progress', 'current_level' => $matrices->min('level'), 'submitted_by' => $submittedBy],
            );
            $returnedForRevision = $entity->getAttribute('status') === 'returned_for_revision';
            if ($returnedForRevision) {
                // Resubmission starts a fresh approval version while the old
                // return event remains preserved in approval_workflow_events.
                $entity->forceFill(['status' => 'draft'])->save();
            }
            // A rejected document may be corrected and submitted again. Reset
            // the historical run actions so its next approval starts cleanly.
            if (in_array($run->status, ['rejected', 'returned_for_revision'], true) || $returnedForRevision) {
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

            $run = $run->fresh('actions');
            $this->recordEvent($run, 'resubmitted', null, $run->current_level, $submittedBy, 'Dokumen disubmit/resubmit untuk approval.');
            return $run;
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
            $this->recordEvent($run, 'approved', $run->current_level, $run->current_level, $user->id, $notes);

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
            $this->recordEvent($run, 'completed', $run->current_level, null, $user->id, $notes);
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
            $this->recordEvent($run, 'rejected', $run->current_level, null, $user->id, $notes);
        });
    }

    public function returnToLevel(string $module, Model $entity, User $user, int $targetLevel, string $notes): array
    {
        if (trim($notes) === '') throw ValidationException::withMessages(['notes' => 'Alasan pengembalian wajib diisi.']);
        return DB::transaction(function () use ($module, $entity, $user, $targetLevel, $notes) {
            $run = ApprovalWorkflowRun::query()->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])->lockForUpdate()->first();
            if (! $run || ! in_array($run->status, ['in_progress', 'approved'], true)) throw ValidationException::withMessages(['approval' => 'Workflow approval tidak aktif.']);
            if ($targetLevel < 1 || $targetLevel >= (int) $run->current_level) throw ValidationException::withMessages(['target_level' => 'Dokumen hanya dapat dikembalikan ke level sebelumnya.']);
            $run->load('actions.approvalMatrix');
            $fromLevel = (int) $run->current_level;
            $current = $run->actions()->where('level', $run->current_level)->whereIn('status', ['pending', 'approved'])->first();
            if (! $current || (! $this->isSuperAdmin($user) && ! $this->matchesApprover($current, $user, $entity))) throw ValidationException::withMessages(['approval' => 'Anda bukan approver pada tahap ini.']);
            $current->update(['notes' => trim(($current->notes ? $current->notes."\n" : '').'Returned to level '.$targetLevel.': '.$notes), 'action_by' => $user->id, 'action_at' => now()]);
            $run->actions()->where('level', $targetLevel)->update(['status' => 'pending', 'action_by' => null, 'action_at' => null, 'notes' => 'Returned for revision: '.$notes]);
            $run->actions()->where('level', '>', $targetLevel)->update(['status' => 'queued', 'action_by' => null, 'action_at' => null]);
            $run->update(['status' => 'in_progress', 'current_level' => $targetLevel, 'completed_at' => null]);
            if ($entity->getAttribute('status')) $entity->forceFill(['status' => 'returned_for_revision'])->save();
            $this->recordEvent($run, 'returned_for_revision', $fromLevel, $targetLevel, $user->id, $notes, [
                'reason' => $notes,
                'target_level' => $targetLevel,
            ]);
            return ['target_level' => $targetLevel, 'message' => 'Dokumen dikembalikan untuk revisi.'];
        });
    }

    private function recordEvent(ApprovalWorkflowRun $run, string $event, ?int $fromLevel, ?int $toLevel, ?int $actorId, ?string $notes = null, array $metadata = []): void
    {
        $actor = $actorId ? User::with('employee.positionMaster')->find($actorId) : null;
        $version = ((int) $run->events()->max('version')) + 1;
        ApprovalWorkflowEvent::create([
            'approval_workflow_run_id' => $run->id,
            'approval_workflow_action_id' => $run->actions()->where('level', $fromLevel ?: $toLevel)->latest('id')->value('id'),
            'event' => $event,
            'from_level' => $fromLevel,
            'to_level' => $toLevel,
            'version' => $version,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_position' => $actor?->employee?->position_name ?: $actor?->employee?->positionMaster?->name,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function userCanApproveAction(\App\Models\ApprovalWorkflowAction $action, User $user, ?Model $entity = null): bool
    {
        return $this->matchesApprover($action, $user, $entity);
    }

    /**
     * Return the approval progress visible to the current viewer. Future
     * queued stages are intentionally hidden from normal users; the requester
     * can see completed stages and the current stage, while Super Admin can
     * inspect the complete chain.
     */
    public function progressFor(string $module, Model $entity, ?User $viewer = null): array
    {
        $run = $this->runFor($module, $entity);
        if (! $run) {
            $amount = (float) ($entity->getAttribute('total_amount') ?? $entity->getAttribute('amount') ?? $entity->getAttribute('tax_amount') ?? $entity->getAttribute('acquisition_cost') ?? 0);
            $matrices = $this->matricesFor($module, $entity, $amount);
            $finalStatuses = ['approved', 'posted', 'paid', 'completed', 'reported', 'reconciled', 'matched', 'applied', 'active'];
            $entityStatus = strtolower((string) ($entity->getAttribute('status') ?: 'draft'));
            if ($matrices->isNotEmpty() && in_array($entityStatus, $finalStatuses, true)) {
                $actor = null;
                $actorId = $entity->getAttribute('approved_by') ?: $entity->getAttribute('posted_by') ?: $entity->getAttribute('verified_by');
                if ($actorId) $actor = User::with('employee.positionMaster')->find($actorId);
                return [
                    'status' => $entity->getAttribute('status'),
                    'current_level' => null,
                    'current_stage' => null,
                    'completed_count' => $matrices->count(),
                    'total_count' => $matrices->count(),
                    'steps' => $matrices->map(fn ($matrix) => [
                        'level' => (int) $matrix->level,
                        'label' => $matrix->approver_title ?: ($matrix->role?->name ?: 'Approval Level '.$matrix->level),
                        'status' => 'approved',
                        'approver_name' => $actor?->name,
                        'approver_position' => $actor?->employee?->position_name ?: $actor?->employee?->positionMaster?->name ?: $matrix->approver_title,
                        'signature_url' => $actor?->employee?->signature_path ? url($actor->employee->signature_path) : null,
                        'approved_at' => ($approvalDate = $entity->getAttribute('approved_at') ?: $entity->getAttribute('updated_at')) ? \Carbon\CarbonImmutable::parse($approvalDate)->toISOString() : null,
                    ])->values()->all(),
                ];
            }
            return [
                'status' => $entity->getAttribute('status') ?: 'draft',
                'current_level' => null,
                'current_stage' => null,
                'completed_count' => 0,
                'total_count' => 0,
                'steps' => [],
            ];
        }

        // A workflow has one approval action per level. Older seed data could
        // contain duplicate matrix rows, so collapse legacy duplicates here
        // without changing the stored audit records.
        $actions = $run->actions
            ->sortBy(fn ($action) => sprintf('%08d-%08d', (int) $action->level, (int) $action->id))
            ->groupBy('level')
            ->map(fn ($levelActions) => $levelActions->first())
            ->values();
        $amount = (float) ($entity->getAttribute('total_amount') ?? $entity->getAttribute('amount') ?? $entity->getAttribute('tax_amount') ?? $entity->getAttribute('acquisition_cost') ?? 0);
        $fallbackMatrices = $this->matricesFor($module, $entity, $amount)->keyBy('level');
        $isSuperAdmin = $viewer && $this->isSuperAdmin($viewer);
        $visibleActions = $isSuperAdmin
            ? $actions
            : $actions->filter(fn ($action) => in_array($action->status, ['approved', 'pending'], true))->values();
        $current = $actions->first(fn ($action) => (int) $action->level === (int) $run->current_level && $action->status === 'pending');
        $currentMatrix = $current?->approvalMatrix ?: $fallbackMatrices->get((int) $run->current_level);

        return [
            'status' => $run->status,
            'current_level' => $run->status === 'approved' ? null : (int) $run->current_level,
            'current_stage' => $currentMatrix?->approver_title ?: ($currentMatrix?->role?->name ?? null),
            'completed_count' => $actions->where('status', 'approved')->count(),
            'total_count' => $actions->count(),
            'steps' => $visibleActions->map(function ($action) use ($fallbackMatrices) {
                $matrix = $action->approvalMatrix ?: $fallbackMatrices->get((int) $action->level);
                return [
                'level' => (int) $action->level,
                'label' => $matrix?->approver_title ?: ($matrix?->role?->name ?: 'Approval Level '.$action->level),
                'status' => $action->status,
                'approver_name' => $action->actionBy?->name ?: $action->actionBy?->employee?->name,
                'approver_position' => $action->actionBy?->employee?->position_name
                    ?: $action->actionBy?->employee?->position
                    ?: $action->actionBy?->employee?->positionMaster?->name
                    ?: $matrix?->approver_title
                    ?: ($matrix?->role?->name ?? null),
                'signature_url' => $action->actionBy?->employee?->signature_path
                    ? url($action->actionBy->employee->signature_path)
                    : null,
                'approved_at' => $action->action_at?->toISOString(),
                ];
            })->values()->all(),
        ];
    }

    private function runFor(string $module, Model $entity): ?ApprovalWorkflowRun
    {
        return ApprovalWorkflowRun::query()->with(['actions.approvalMatrix.role', 'actions.actionBy.employee.positionMaster'])->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])->first();
    }

    private function matricesFor(string $module, Model $entity, float $amount)
    {
        $fiscalYearId = $entity->getAttribute('fiscal_year_id');
        if (! $fiscalYearId) {
            $date = $entity->getAttribute('request_date')
                ?: $entity->getAttribute('transaction_date')
                ?: $entity->getAttribute('journal_date')
                ?: $entity->getAttribute('created_at');
            if ($date) {
                $fiscalYearId = FiscalYear::query()
                    ->where('year', \Carbon\CarbonImmutable::parse($date)->year)
                    ->value('id');
            }
        }

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

        $matrices = $scopeMatrix(ApprovalMatrix::query())
            ->where('level', '<=', $highestLevel)
            ->orderBy('level')->orderBy('id')->get();

        $matrices = $matrices->filter(function ($matrix) use ($amount, $entity) {
            if ((int) $matrix->level !== 1) return true;
            $title = strtolower((string) $matrix->approver_title);
            if (! $entity->getAttribute('project_id') && str_contains($title, 'denny')) return $amount <= 267000000;
            return (float) $matrix->min_amount <= $amount
                && ($matrix->max_amount === null || (float) $matrix->max_amount >= $amount);
        })->values();

        if (! $entity->getAttribute('project_id')) {
            $matrices = $matrices->reject(fn ($matrix) => str_contains(strtolower((string) $matrix->approver_title), 'abil'));
        }

        return $matrices;
    }

    private function matchesApprover($action, User $user, ?Model $entity = null): bool
    {
        // The requester/preparer must never approve their own expense or settlement,
        // even when an old workflow action already contains a matching role/user.
        $requesterId = $entity?->getAttribute('requester_id') ?? $entity?->getAttribute('submitted_by');
        if ($requesterId && (int) $requesterId === (int) $user->id) {
            return false;
        }
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $matrix = $action->approvalMatrix;
        if ($matrix?->role && in_array($matrix->role->slug, ['budget-holder', 'budget-holder-project-manager'], true)) {
            // Only the configured budget holders may approve at this stage.
            // The President may be one of them, but is not a mandatory final stage.
            $allowedBudgetHolders = [
                'denny bhatara',
                'abil ahmad akbar',
                'sarah megumi',
                'sarah rosemery megumi w',
                'sandika ariansyah',
            ];
            $names = array_map('strtolower', array_filter([$user->name, $user->employee?->name]));
            $isAllowed = collect($names)->contains(function ($name) use ($allowedBudgetHolders) {
                $normalized = preg_replace('/\s+/', ' ', trim((string) $name));
                return in_array($normalized, $allowedBudgetHolders, true)
                    || str_contains($normalized, 'sandika ariansyah')
                    || str_contains($normalized, 'megumi');
            });
            if (! $isAllowed) return false;
        }
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
