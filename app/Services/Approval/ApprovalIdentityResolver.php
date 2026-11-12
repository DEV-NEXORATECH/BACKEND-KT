<?php

namespace App\Services\Approval;

use App\Models\Master\Employee;
use App\Models\User;

class ApprovalIdentityResolver
{
    public function employeeId(User $user): ?int
    {
        if ($user->employee_id) {
            return Employee::withoutGlobalScope('fiscal_year_context')->whereKey($user->employee_id)->value('id');
        }
        // Legacy email link is valid only when unique; names never authorize.
        if (!$user->email) return null;
        $ids = Employee::withoutGlobalScope('fiscal_year_context')->where('email', $user->email)->limit(2)->pluck('id');
        return $ids->count() === 1 ? (int)$ids->first() : null;
    }

    public function matches($assignment, User $user): bool
    {
        if ($assignment->user_id || $assignment->employee_id) {
            // A role label cannot broaden an explicit person assignment.
            return (!$assignment->user_id || (int)$assignment->user_id === (int)$user->id)
                && (!$assignment->employee_id || (int)$assignment->employee_id === $this->employeeId($user));
        }
        return $assignment->role_id && (int)$assignment->role_id === (int)$user->role_id;
    }
}
