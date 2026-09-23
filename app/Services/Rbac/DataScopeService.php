<?php

namespace App\Services\Rbac;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DataScopeService
{
    /**
     * Determine if the user has full access across all data for a specific area.
     */
    public function canAccessAll(User $user, array $overridePermissions = []): bool
    {
        $roleSlug = $user->role?->slug;

        if (in_array($roleSlug, ['super-admin', 'admin-system-owner', 'finance-manager', 'finance'], true)) {
            return true;
        }

        if (! empty($overridePermissions)) {
            return $user->hasAnyPermission($overridePermissions);
        }

        return false;
    }

    /**
     * Project ids the user is explicitly assigned to.
     */
    public function accessibleProjectIds(User $user): array
    {
        return \App\Models\ProjectAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('project_id')
            ->all();
    }

    /**
     * Apply data scoping filters on an Eloquent query builder.
     *
     * A user sees records they own, records on projects they are explicitly
     * assigned to, or everything when they have full-access privileges.
     */
    public function applyScope(
        Builder $query,
        User $user,
        ?string $ownerColumn = 'created_by',
        ?string $projectColumn = 'project_id',
        ?string $orgColumn = 'organization_id',
        array $overridePermissions = []
    ): Builder {
        if ($this->canAccessAll($user, $overridePermissions)) {
            return $query;
        }

        $projectIds = $this->accessibleProjectIds($user);

        if ($ownerColumn === null && $projectColumn === null && $orgColumn === null) {
            return $query->whereRaw('1 = 0');
        }

        $query->where(function (Builder $inner) use ($ownerColumn, $projectColumn, $user, $projectIds) {
            if ($ownerColumn !== null) {
                $inner->orWhere($ownerColumn, $user->id);
            }

            if ($projectColumn !== null && ! empty($projectIds)) {
                $inner->orWhereIn($projectColumn, $projectIds);
            }
        });

        if ($ownerColumn === null && ($projectColumn === null || empty($projectIds))) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}