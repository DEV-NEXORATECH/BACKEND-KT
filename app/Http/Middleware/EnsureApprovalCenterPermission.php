<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovalCenterPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) abort(401);

        $permissions = [
            'expenses-approvals.view', 'expense.approve', 'expenses.approve', 'procurement.pr.approve',
            'procurement.po.approve', 'procurement.cba.approve', 'procurement.pr.view',
            'accounting.journal.review', 'accounting.journal.post', 'ap.post', 'ap.pay',
            'ar.post', 'ar.receive', 'timesheet.approve', 'tax.manage', 'tax.approve',
            'banking.view', 'banking.reconcile', 'expense.submit', 'settings.manage',
            'accounting.period.close', 'budget.approve', 'asset.capitalize', 'asset.dispose',
            'expense.pay',
        ];

        if (! $user->hasAnyPermission($permissions) && ! (bool) ($user->is_super_admin ?? false)) {
            abort(403, 'Tidak memiliki permission untuk mengakses Approval Center.');
        }

        return $next($request);
    }
}
