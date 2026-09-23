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

        $employee = $user->employee;

        return $query->where(function (Builder $sub) use ($user, $employee, $ownerColumn, $projectColumn, $orgColumn) {
            $hasCondition = false;

            if ($ownerColumn !== null) {
                $sub->where($ownerColumn, $user->id);
                $hasCondition = true;
            }

            if ($employee && $employee->id && $ownerColumn !== null && $ownerColumn !== 'employee_id') {
                $sub->orWhere('employee_id', $employee->id);
            }

            if ($projectColumn !== null && $employee && $employee->department_id) {
                $sub->orWhereHas('project', function (Builder $pQuery) use ($employee) {
                    $pQuery->where('department_id', $employee->department_id)
                        ->orWhere('manager_employee_id', $employee->id);
                });
            }

            if (! $hasCondition) {
                $sub->whereRaw('1 = 1');
            }
        });
    }
}
