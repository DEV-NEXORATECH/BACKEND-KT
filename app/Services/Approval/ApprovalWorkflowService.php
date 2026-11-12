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
    private function eligibleMatricesFor(string $module, Model $entity, float $amount, ?int $submittedBy = null)
    {
        $matrices = $this->matricesFor($module, $entity, $amount);
        $requesterId = $submittedBy ?? $entity->getAttribute('submitted_by');
        if ($requesterId) {
            $requester = User::with('employee')->find($requesterId);
            if ($requester) {
                // A role is not a person. Do not remove an entire role stage
                // just because the requester has the same role; remove only
                // matrix rows that resolve to the requester themselves.
                $matrices = $matrices->reject(fn ($matrix) => $this->matrixBelongsToUser($matrix, $requester))->values();
            }
        }
        // A person may be represented by both an explicit user and employee
        // matrix row. Keep the first matching row so one person never appears
        // twice in a single approval chain.
        $seenPeople = [];
        return $matrices->filter(function ($matrix) use (&$seenPeople) {
            $identity = $this->matrixPersonKey($matrix);
            if ($identity === null) return true;
            if (isset($seenPeople[$identity])) return false;
            $seenPeople[$identity] = true;
            return true;
        })->values();
    }

    public function start(string $module, Model $entity, float $amount, ?int $submittedBy = null): ?ApprovalWorkflowRun
    {
        $matrices = $this->eligibleMatricesFor($module, $entity, $amount, $submittedBy);
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
            // Also protect legacy runs that were created before the
            // requester/self-approval rule was enabled.
            $this->skipRequesterActions($run, $entity);
            $run = $run->fresh('actions');
            $this->recordEvent($run, 'resubmitted', null, $run->current_level, $submittedBy, 'Document submitted or resubmitted for approval.');
            return $run;
        });
    }

    public function requiresApproval(string $module, Model $entity, float $amount, ?User $viewer = null): bool
    {
        $currentUser = $viewer ?? (function_exists('request') ? request()->user() : null);
        if ($currentUser instanceof User && $this->isSuperAdmin($currentUser)) {
            return false;
        }

        return $this->eligibleMatricesFor($module, $entity, $amount)->isNotEmpty();
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
            $action = $actions->first(fn ($item) => $this->matchesApprover($item, $user, $entity));
            if (! $action) throw ValidationException::withMessages(['approval' => 'You are not the approver for the current approval stage.']);
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

    public function reject(string $module, Model $entity, User $user, string $notes, bool $asReviewer = false): void
    {
        DB::transaction(function () use ($module, $entity, $user, $notes, $asReviewer) {
            $run = ApprovalWorkflowRun::query()
                ->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])
                ->lockForUpdate()->first();
            if (! $run) return;
            $run->load('actions.approvalMatrix');
            $currentActions = $run->actions()->where('level', $run->current_level)->where('status', 'pending')->get();
            if ($asReviewer && $this->isRequester($entity, $user)) {
                throw ValidationException::withMessages(['approval' => 'Requester cannot reject their own document as reviewer.']);
            }
            $action = $asReviewer
                ? $currentActions->first()
                : $currentActions->first(fn ($item) => $this->matchesApprover($item, $user, $entity));
            if (! $asReviewer && ! $action) throw ValidationException::withMessages(['approval' => 'You are not the approver for the current approval stage.']);
            if ($action) {
                $action->update(['status' => 'rejected', 'action_by' => $user->id, 'action_at' => now(), 'notes' => $notes]);
            }
            $run->update(['status' => 'rejected', 'completed_at' => now()]);
            $this->recordEvent($run, 'rejected', $run->current_level, null, $user->id, $notes);
        });
    }

    /** Assign or reassign an active eligible user to approve the active stage of a workflow run. */
    public function assignCurrentStageApprover(string $module, Model $entity, User $targetUser, User $actor, ?string $notes = null): array
    {
        if ($targetUser->is_active === false) {
            throw ValidationException::withMessages(['user_id' => 'User approver yang dipilih tidak aktif.']);
        }
        if ($this->isRequester($entity, $targetUser)) {
            throw ValidationException::withMessages(['user_id' => 'Requester atau pembuat dokumen tidak dapat ditugaskan sebagai approver.']);
        }
        if ($module === 'journal' && ! $targetUser->hasPermission('accounting.journal.post')) {
            throw ValidationException::withMessages(['user_id' => 'User yang dipilih tidak memiliki izin accounting.journal.post untuk approve/post journal.']);
        }

        return DB::transaction(function () use ($module, $entity, $targetUser, $actor, $notes) {
            $resolver = app(ApprovalIdentityResolver::class);
            $resolvedEmployeeId = $resolver->employeeId($targetUser);
            $run = ApprovalWorkflowRun::query()
                ->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])
                ->lockForUpdate()
                ->first();

            if (! $run) {
                $run = ApprovalWorkflowRun::create([
                    'module' => $module,
                    'approvable_type' => $entity::class,
                    'approvable_id' => $entity->getKey(),
                    'status' => 'in_progress',
                    'current_level' => 1,
                    'submitted_by' => $entity->getAttribute('submitted_by') ?: $entity->getAttribute('created_by') ?: $actor->id,
                ]);
                $run->actions()->create([
                    'approval_matrix_id' => null,
                    'level' => 1,
                    'role_id' => $targetUser->role_id,
                    'user_id' => $targetUser->id,
                    'employee_id' => $resolvedEmployeeId,
                    'status' => 'pending',
                ]);
            } else {
                if ($run->status !== 'in_progress') {
                    throw ValidationException::withMessages(['approval' => 'Approver hanya dapat ditetapkan saat workflow berstatus in_progress.']);
                }
                $run->load('actions.approvalMatrix');
                $action = $run->actions()
                    ->where('level', $run->current_level)
                    ->where('status', 'pending')
                    ->orderBy('id')
                    ->first();

                if (! $action) {
                    $action = $run->actions()->create([
                        'approval_matrix_id' => null,
                        'level' => $run->current_level ?: 1,
                        'role_id' => $targetUser->role_id,
                        'user_id' => $targetUser->id,
                        'employee_id' => $resolvedEmployeeId,
                        'status' => 'pending',
                    ]);
                } else {
                    $action->update([
                        'user_id' => $targetUser->id,
                        'employee_id' => $resolvedEmployeeId,
                        'role_id' => $action->role_id ?: $targetUser->role_id,
                    ]);
                }
            }

            $level = (int) ($run->current_level ?: 1);
            $auditNote = trim((string) $notes) !== ''
                ? trim((string) $notes)
                : "Approver tahap {$level} ditetapkan ke {$targetUser->name}.";

            $this->recordEvent($run, 'approver_assigned', $level, $level, $actor->id, $auditNote, [
                'assigned_user_id' => $targetUser->id,
                'assigned_user_name' => $targetUser->name,
                'assigned_employee_id' => $resolvedEmployeeId,
            ]);

            return [
                'level' => $level,
                'assigned_user_id' => $targetUser->id,
                'assigned_user_name' => $targetUser->name,
                'message' => $auditNote,
            ];
        });
    }

    public function returnToLevel(string $module, Model $entity, User $user, int $targetLevel, string $notes): array
    {
        if (trim($notes) === '') throw ValidationException::withMessages(['notes' => 'A return reason is required.']);
        return DB::transaction(function () use ($module, $entity, $user, $targetLevel, $notes) {
            $run = ApprovalWorkflowRun::query()->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])->lockForUpdate()->first();
            if (! $run || ! in_array($run->status, ['in_progress', 'approved'], true)) throw ValidationException::withMessages(['approval' => 'Workflow approval tidak aktif.']);
            if ($targetLevel < 1 || $targetLevel >= (int) $run->current_level) throw ValidationException::withMessages(['target_level' => 'A document can only be returned to a previous level.']);
            $run->load('actions.approvalMatrix');
            $fromLevel = (int) $run->current_level;
            $current = $run->actions()->where('level', $run->current_level)->whereIn('status', ['pending', 'approved'])->first();
            if (! $current || (! $this->isSuperAdmin($user) && ! $this->matchesApprover($current, $user, $entity))) throw ValidationException::withMessages(['approval' => 'You are not the approver for this stage.']);
            $current->update(['notes' => trim(($current->notes ? $current->notes."\n" : '').'Returned to level '.$targetLevel.': '.$notes), 'action_by' => $user->id, 'action_at' => now()]);
            $run->actions()->where('level', $targetLevel)->update(['status' => 'pending', 'action_by' => null, 'action_at' => null, 'notes' => 'Returned for revision: '.$notes]);
            $run->actions()->where('level', '>', $targetLevel)->update(['status' => 'queued', 'action_by' => null, 'action_at' => null]);
            $run->update(['status' => 'in_progress', 'current_level' => $targetLevel, 'completed_at' => null]);
            if ($entity->getAttribute('status')) $entity->forceFill(['status' => 'returned_for_revision'])->save();
            $this->recordEvent($run, 'returned_for_revision', $fromLevel, $targetLevel, $user->id, $notes, [
                'reason' => $notes,
                'target_level' => $targetLevel,
            ]);
            return ['target_level' => $targetLevel, 'message' => 'Document returned for revision.'];
        });
    }

    public function returnToRequester(string $module, Model $entity, User $user, string $notes): array
    {
        if (trim($notes) === '') throw ValidationException::withMessages(['notes' => 'A return reason is required.']);

        return DB::transaction(function () use ($module, $entity, $user, $notes) {
            $run = ApprovalWorkflowRun::query()
                ->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])
                ->lockForUpdate()->first();
            if (! $run) throw ValidationException::withMessages(['approval' => 'Workflow approval belum tersedia.']);

            $fromLevel = (int) $run->current_level;
            $run->actions()->update([
                'status' => 'queued',
                'action_by' => null,
                'action_at' => null,
                'notes' => 'Returned to requester: '.$notes,
            ]);
            $run->update([
                'status' => 'returned_for_revision',
                'current_level' => 1,
                'completed_at' => null,
                'submitted_by' => $entity->getAttribute('requester_id') ?: $entity->getAttribute('submitted_by'),
            ]);
            $this->recordEvent($run, 'returned_for_revision', $fromLevel ?: null, null, $user->id, $notes, [
                'reason' => $notes,
                'returned_to' => 'requester',
            ]);

            return ['message' => 'Document returned to the requester for correction.', 'status' => 'draft'];
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
        return $action->status === 'pending' && $action->run?->status === 'in_progress'
            && (int)$action->level === (int)$action->run->current_level
            && $this->matchesApprover($action, $user, $entity);
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
        if (!$run && $module !== 'journal') return $this->legacyProgressFor($module, $entity, $viewer);
        $actions = $run?->actions ?? collect();
        $active = $run?->status === 'in_progress'
            ? $actions->filter(fn ($a) => $a->status === 'pending' && (int)$a->level === (int)$run->current_level)
            : collect();
        $isPostedJournal = $module === 'journal' && $entity->getAttribute('status') === 'posted';
        $ready = true;
        $reason = null;
        if ($module === 'journal') {
            if ($isPostedJournal) {
                $ready = false;
            } elseif (!$viewer) { $ready = false; $reason = 'authentication_required'; }
            else {
                try { $entity = clone $entity; $entity->fiscal_year_id = app(\App\Services\Accounting\JournalPostingGuard::class)->assertReady($entity, $viewer); }
                catch (ValidationException $error) { $ready = false; $reason = $error->validator->errors()->first(); }
                catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $ready = false; $reason = $error->getMessage(); }
            }
        }
        $canApprove = $ready && $viewer && $active->contains(fn ($a) => $this->matchesApprover($a, $viewer, $entity));
        $amount = $module === 'journal' ? (float)$entity->lines()->sum('debit') : (float)($entity->total_amount ?? $entity->amount ?? 0);
        $canPost = $module === 'journal' && $ready && $viewer && ($run?->status === 'approved' || (!$run && !$this->requiresApproval($module, $entity, $amount, $viewer)));
        if (!$canApprove && !$canPost && !$reason && !$isPostedJournal) {
            $reason = $run?->status === 'in_progress' ? 'not_current_approver' : ($run ? 'workflow_not_active' : 'approval_not_started');
        }
        $visible = $viewer && $this->isSuperAdmin($viewer) ? $actions : $actions->filter(fn ($a) => in_array($a->status, ['approved', 'pending', 'rejected', 'skipped'], true));
        $steps = $visible->map(function ($action) use ($entity, $viewer, $active, $module, $ready) {
            $approvers = $this->approversFor($action, $entity, $module);
            $isActive = $active->contains('id', $action->id);
            $missingReason = empty($approvers) ? $this->missingApproverReason($action, $entity, $module) : null;
            return [
                'id' => $action->id,
                'level' => (int)$action->level,
                'label' => $action->approvalMatrix?->approver_title ?: 'Approval Level '.$action->level,
                'status' => $action->status,
                'user_id' => $action->user_id,
                'employee_id' => $action->employee_id,
                'role_id' => $action->role_id,
                'approvers' => $approvers,
                'missing_reason' => $missingReason,
                'approver_name' => $action->actionBy?->name ?: (count($approvers) === 1 ? $approvers[0]['name'] : null),
                'approver_position' => $action->approvalMatrix?->approver_title,
                'approved_at' => $action->action_at?->toISOString(),
                'action_by' => $action->action_by,
                'signature_url' => $action->actionBy?->employee?->signature_path ? url($action->actionBy->employee->signature_path) : null,
                'can_approve' => (bool)($ready && $viewer && $isActive && $this->matchesApprover($action, $viewer, $entity)),
            ];
        })->values()->all();

        $configuredStage = null;
        $configuredApprovers = [];
        $configuredStagesCount = 0;
        if (! $run && $module === 'journal') {
            $previewMatrices = $this->matricesFor($module, $entity, $amount);
            $configuredStagesCount = $previewMatrices->count();
            $firstMatrix = $previewMatrices->first();
            if ($firstMatrix) {
                $configuredStage = $firstMatrix->approver_title ?: ($firstMatrix->role?->name ?: 'Approval Level '.$firstMatrix->level);
                $configuredApprovers = $this->approversFor($firstMatrix, $entity, $module);
            }
        }
        if ($isPostedJournal && empty($steps)) {
            $steps = $this->journalLifecycleSteps($entity, $configuredStage);
        }

        $needsApproverAssignment = $run?->status === 'in_progress'
            && collect($steps)->contains(fn ($step) => in_array($step['status'], ['pending', 'current'], true) && empty($step['approvers']));

        return [
            'status' => $run?->status ?? 'not_started',
            'document_status' => $entity->getAttribute('status'),
            'current_level' => $active->isNotEmpty() ? (int)$run->current_level : null,
            'current_stage' => $active->first()?->approvalMatrix?->approver_title ?: ($active->isNotEmpty() ? 'Approval Level '.$run->current_level : null),
            'completed_count' => $actions->where('status', 'approved')->count(),
            'total_count' => $actions->count(),
            'can_approve' => (bool)$canApprove,
            'can_post' => (bool)$canPost,
            'unavailable_reason' => $reason,
            'can_approve_reason' => $canApprove ? null : ($reason ?: 'no_pending_approval'),
            'can_post_reason' => $canPost ? null : ($reason ?: 'approval_pending'),
            'needs_approver_assignment' => $needsApproverAssignment,
            'requires_approval' => $module === 'journal' ? $this->requiresApproval($module, $entity, $amount, $viewer) : ($actions->isNotEmpty()),
            'configured_stages_count' => $configuredStagesCount,
            'configured_stage' => $configuredStage,
            'configured_approvers' => $configuredApprovers,
            'steps' => $steps,
        ];
    }

    private function journalLifecycleSteps(Model $entity, ?string $configuredStage): array
    {
        $userIds = array_values(array_filter(array_unique([
            $entity->getAttribute('submitted_by'),
            $entity->getAttribute('reviewed_by'),
            $entity->getAttribute('posted_by'),
        ])));
        $users = empty($userIds) ? collect() : User::query()->whereIn('id', $userIds)->get()->keyBy('id');
        $stages = [];
        $pushStage = function (string $key, string $label, string $actionStatus, ?int $userId, $at, ?string $notes = null) use (&$stages, $users) {
            $name = $userId ? $users->get($userId)?->name : null;
            $stages[] = [
                'id' => $key,
                'level' => count($stages) + 1,
                'label' => $label,
                'status' => 'approved',
                'action_by' => $userId,
                'approver_name' => $name,
                'actor_name' => $name,
                'approved_at' => $at?->toISOString(),
                'action_status' => $actionStatus,
                'notes' => $notes,
                'can_approve' => false,
            ];
        };
        if ($entity->getAttribute('submitted_at') || $entity->getAttribute('submitted_by')) {
            $pushStage('journal-submit', 'Submit Journal', 'Submitted', $entity->getAttribute('submitted_by'), $entity->getAttribute('submitted_at'));
        }
        if ($entity->getAttribute('reviewed_at') || $entity->getAttribute('reviewed_by')) {
            $pushStage('journal-review', 'Review Journal', 'Reviewed', $entity->getAttribute('reviewed_by'), $entity->getAttribute('reviewed_at'));
        }
        $postNote = $configuredStage ? "Direct post tanpa multi-stage approval ({$configuredStage})" : 'Posted langsung ke ledger';
        $pushStage('journal-post', 'Post to Ledger', 'Posted to Ledger', $entity->getAttribute('posted_by'), $entity->getAttribute('posted_at'), $postNote);
        return $stages;
    }

    private function missingApproverReason($action, Model $entity, string $module): string
    {
        if ($action->user_id || $action->employee_id) {
            $candidate = $action->user_id
                ? User::with('role.permissions')->find($action->user_id)
                : User::with('role.permissions')->where('employee_id', $action->employee_id)->first();
            if (! $candidate) {
                return 'Employee/jabatan pada matrix ini belum memiliki akun login user yang terhubung.';
            }
            if ($candidate->is_active === false) {
                return "User {$candidate->name} berstatus nonaktif.";
            }
            if ($this->isRequester($entity, $candidate)) {
                return "User {$candidate->name} adalah requester dokumen ini sehingga tidak dapat melakukan self-approval.";
            }
            if ($module === 'journal' && ! $candidate->hasPermission('accounting.journal.post')) {
                return "User {$candidate->name} belum memiliki izin accounting.journal.post.";
            }
            return 'User approver tidak memenuhi scope atau matriks approval dokumen ini.';
        }

        $role = $action->role_id ? \App\Models\Role::find($action->role_id) : null;
        if ($action->approvalMatrix?->is_conditional_project_manager || in_array($role?->slug, ['budget-holder', 'budget-holder-project-manager'], true)) {
            $projects = $this->dimensionIds($entity, 'project_id');
            if (empty($projects)) {
                return 'Journal tidak mencantumkan Project sehingga Project Manager kondisional tidak dapat ditentukan.';
            }
            return 'Belum ada Project Manager aktif pada Project Assignment yang memenuhi scope & izin approve journal.';
        }

        if ($role) {
            return $module === 'journal'
                ? "Belum ada user aktif pada role {$role->name} dengan izin accounting.journal.post."
                : "Belum ada user aktif pada role {$role->name}.";
        }

        return 'Approver belum ditetapkan pada tahap approval ini.';
    }

    private function approversFor($action, Model $entity, string $module): array
    {
        $query = User::query()->where('is_active', true);
        if ($action->user_id) $query->whereKey($action->user_id);
        elseif (!$action->employee_id) $query->where('role_id', $action->role_id);
        // Employee links are resolved by the same canonical resolver, including
        // unique legacy email links. Never substitute a matrix display name.
        return $query->get()->filter(fn ($user) => $this->matchesApprover($action, $user, $entity)
                && ($module !== 'journal' || $user->hasPermission('accounting.journal.post')))
            ->map(fn ($user) => ['user_id' => $user->id, 'employee_id' => app(ApprovalIdentityResolver::class)->employeeId($user), 'name' => $user->name, 'role_id' => $user->role_id])
            ->values()->all();
    }

    private function legacyProgressFor(string $module, Model $entity, ?User $viewer = null): array
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
            if ($matrices->isNotEmpty()) {
                return [
                    'status' => $entity->getAttribute('status') ?: 'draft',
                    'current_level' => 1,
                    'current_stage' => $matrices->first()?->approver_title ?: ($matrices->first()?->role?->name ?? 'Approval Level 1'),
                    'completed_count' => 0,
                    'total_count' => $matrices->count(),
                    'steps' => $matrices->map(function ($matrix, $index) {
                        $approver = $matrix->user?->name ?: $matrix->employee?->name;
                        return [
                            'level' => (int) $matrix->level,
                            'label' => $matrix->approver_title ?: ($matrix->role?->name ?: 'Approval Level '.$matrix->level),
                            'status' => $index === 0 ? 'pending' : 'upcoming',
                            'approver_name' => $approver,
                            'approver_position' => $matrix->employee?->position ?: $matrix->approver_title ?: $matrix->role?->name,
                            'signature_url' => $matrix->employee?->signature_path ? url($matrix->employee->signature_path) : null,
                            'approved_at' => null,
                        ];
                    })->values()->all(),
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
                'approver_name' => $action->actionBy?->name
                    ?: $action->actionBy?->employee?->name
                    ?: $matrix?->user?->name
                    ?: $matrix?->employee?->name,
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
        return ApprovalWorkflowRun::query()->with([
            'actions.approvalMatrix.role',
            'actions.approvalMatrix.user.employee.positionMaster',
            'actions.approvalMatrix.employee.positionMaster',
            'actions.actionBy.employee.positionMaster',
        ])->where(['module' => $module, 'approvable_type' => $entity::class, 'approvable_id' => $entity->getKey()])->first();
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
                    ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
                    ->get()->pipe(fn ($years) => $years->count() === 1 ? $years->first()->id : null);
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
                ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', (count($this->dimensionIds($entity, 'project_id')) === 1 ? $this->dimensionIds($entity, 'project_id')[0] : null)))
                ->where(fn ($q) => $q->whereNull('donor_id')->orWhere('donor_id', (count($this->dimensionIds($entity, 'donor_id')) === 1 ? $this->dimensionIds($entity, 'donor_id')[0] : null)))
                ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', (count($this->dimensionIds($entity, 'department_id')) === 1 ? $this->dimensionIds($entity, 'department_id')[0] : null)));
            return $query;
        };

        $matrixQuery = $scopeMatrix(ApprovalMatrix::withoutGlobalScope('fiscal_year_context'));
        $projectId = (count($this->dimensionIds($entity, 'project_id')) === 1 ? $this->dimensionIds($entity, 'project_id')[0] : null);
        $hasProjectSpecificMatrix = $projectId && (clone $matrixQuery)->where('project_id', $projectId)->exists();
        if ($hasProjectSpecificMatrix) {
            // A project-specific chain replaces the generic entity chain.
            // This keeps Abil project-only and prevents Denny's internal chain
            // from being merged into project approvals.
            $matrixQuery->where('project_id', $projectId);
        }

        $highestLevel = (clone $matrixQuery)
            ->where('min_amount', '<=', $amount)
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount))
            ->max('level');

        if (! $highestLevel) return collect();

        $matrices = $matrixQuery
            ->where('level', '<=', $highestLevel)
            ->orderBy('level')->orderBy('id')->get();

        $matrices = $matrices->filter(function ($matrix) use ($amount, $entity) {
            if ((int) $matrix->level !== 1) return true;

            return (float) $matrix->min_amount <= $amount
                && ($matrix->max_amount === null || (float) $matrix->max_amount >= $amount);
        })->values();

        return $matrices;
    }

    private function dimensionIds(Model $entity, string $field): array
    {
        if ($entity instanceof \App\Models\Accounting\Journal) {
            return $entity->lines()->whereNotNull($field)->reorder()->distinct()->pluck($field)->map(fn ($id) => (int)$id)->all();
        }
        return $entity->getAttribute($field) ? [(int)$entity->getAttribute($field)] : [];
    }

    private function matchesApprover($action, User $user, ?Model $entity = null): bool
    {
        if (!$entity) return false;
        if ($user->is_active === false || ($entity && $this->isRequester($entity, $user))) return false;
        $matrix = $action->approvalMatrix;
        if ($action->approval_matrix_id && !$matrix) return false;
        // Preserve the configured document scope even for explicit assignments.
        if ($entity && $matrix) {
            foreach (['project_id', 'donor_id', 'department_id'] as $field) {
                if ($matrix->$field && $this->dimensionIds($entity, $field) !== [(int)$matrix->$field]) return false;
            }
            if ($matrix->fiscal_year_id && (int)$matrix->fiscal_year_id !== (int)$entity->getAttribute('fiscal_year_id')) return false;
        }
        if ($this->isSuperAdmin($user)) return true;
        if (!app(ApprovalIdentityResolver::class)->matches($action, $user)) return false;
        // An explicit workflow person is authoritative; role titles/names do not
        // override that assignment. The action snapshot, not a mutable matrix,
        // determines who is assigned to an existing run.
        if ($action->user_id || $action->employee_id) return true;
        $roleSlug = \App\Models\Role::find($action->role_id)?->slug;
        if ($matrix?->is_conditional_project_manager || in_array($roleSlug, ['budget-holder', 'budget-holder-project-manager'], true)) {
            $projects = $entity ? $this->dimensionIds($entity, 'project_id') : [];
            if (!$projects) return false;
            $assigned = \App\Models\ProjectAssignment::query()->where('user_id', $user->id)->where('is_active', true)
                ->whereIn('project_id', $projects)->whereIn('role', ['project-manager', 'budget-holder', 'budget-holder-project-manager'])
                ->where(fn ($q) => $q->whereNull('assigned_from')->orWhereDate('assigned_from', '<=', today()))
                ->where(fn ($q) => $q->whereNull('assigned_to')->orWhereDate('assigned_to', '>=', today()))
                ->distinct()->pluck('project_id')->all();
            return count(array_intersect($projects, $assigned)) === count($projects);
        }
        return true;
    }
    private function matrixBelongsToUser($matrix, User $user): bool
    {
        if (! $matrix) return false;
        return ($matrix->user_id || $matrix->employee_id) && app(ApprovalIdentityResolver::class)->matches($matrix, $user);
    }
    private function matrixPersonKey($matrix): ?string
    {
        if (! $matrix) return null;
        if ($matrix->employee_id) return 'employee:' . (int) $matrix->employee_id;
        if (! $matrix->user_id) return null;

        // Prefer the employee identity when an explicit user is linked to an
        // employee. This prevents the same person from appearing once through
        // user_id and again through employee_id in the same chain.
        $employeeId = $matrix->relationLoaded('user')
            ? $matrix->user?->employee_id
            : User::query()->whereKey($matrix->user_id)->value('employee_id');
        return $employeeId
            ? 'employee:' . (int) $employeeId
            : 'user:' . (int) $matrix->user_id;
    }

    private function isRequester(Model $entity, User $user): bool
    {
        $requesterIds = array_filter([$entity->getAttribute('requester_id'), $entity->getAttribute('submitted_by'), $entity->getAttribute('created_by')]);
        if (in_array((int)$user->id, array_map('intval', $requesterIds), true)) return true;
        $identity = app(ApprovalIdentityResolver::class);
        $employeeId = $identity->employeeId($user);
        if (!$employeeId) return false;
        $requesterEmployeeIds = array_filter([$entity->getAttribute('requester_employee_id'), $entity->getAttribute('submitted_by_employee_id'), $entity->getAttribute('employee_id')]);
        foreach (User::whereIn('id', $requesterIds)->get() as $requester) $requesterEmployeeIds[] = $identity->employeeId($requester);
        return in_array($employeeId, array_map('intval', $requesterEmployeeIds), true);
    }
    private function skipRequesterActions(ApprovalWorkflowRun $run, Model $entity): void
    {
        $requesterId = $entity->getAttribute('requester_id')
            ?? $entity->getAttribute('submitted_by')
            ?? $entity->getAttribute('created_by');
        $requesterEmployeeId = $entity->getAttribute('requester_employee_id')
            ?? $entity->getAttribute('submitted_by_employee_id')
            ?? $entity->getAttribute('employee_id');
        $requester = $requesterId ? User::with('employee')->find($requesterId) : null;
        if (! $requester && ! $requesterEmployeeId) return;

        $run->loadMissing('actions.approvalMatrix');
        while (true) {
            $current = $run->actions->first(fn ($action) => (int) $action->level === (int) $run->current_level && $action->status === 'pending');
            $isRequesterAction = $requester
                ? $this->matrixBelongsToUser($current, $requester)
                : ($current?->employee_id && (int) $current->employee_id === (int) $requesterEmployeeId);
            if (! $current || ! $isRequesterAction) return;

            $level = (int) $current->level;
            $current->update([
                'status' => 'skipped',
                'notes' => 'Skipped: requester cannot approve their own document.',
            ]);
            $nextLevel = $run->actions->where('level', '>', $level)->where('status', 'queued')->min('level');
            if ($nextLevel) {
                $run->actions()->where('level', $nextLevel)->where('status', 'queued')->update(['status' => 'pending']);
                $run->update(['current_level' => $nextLevel]);
                $run->refresh()->load('actions.approvalMatrix');
                continue;
            }

            $run->update(['status' => 'approved', 'completed_at' => now()]);
            return;
        }
    }

    private function isSuperAdmin(User $user): bool
    {
        $slug = strtolower((string) ($user->role?->slug ?? ''));
        return in_array($slug, ['super-admin', 'super_admin', 'superadmin'], true)
            || (bool) ($user->is_super_admin ?? false);
    }
}
