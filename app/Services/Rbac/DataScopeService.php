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
     * Apply data scoping filters on an Eloquent query builder.
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

        // Project currently has no employee/department ownership columns. Until
        // project assignment is modelled explicitly, owner-only is the secure
        // fallback: it neither leaks records nor emits invalid SQL columns.
        if ($ownerColumn === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($ownerColumn, $user->id);
    }
}
