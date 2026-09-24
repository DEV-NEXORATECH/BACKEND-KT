<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $role = $user->role;
        if (! $role) {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403);
        }

        // Master data lookups for dropdown options and form reference data
        if ($permission === 'master-data') {
            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                // Dropdown options, reference lookups or users with read access
                if ($request->boolean('options') || $request->query('paginate') === 'false') {
                    return $next($request);
                }

                if ($user->hasPermission('master-data.view') || $user->hasPermission('master-data.manage')) {
                    return $next($request);
                }

                // Any authenticated user with an active role can read reference data
                return $next($request);
            }

            $required = 'master-data.manage';
        } elseif ($permission === 'master-data.view') {
            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                if ($request->boolean('options') || $request->query('paginate') === 'false') {
                    return $next($request);
                }
                if ($user->hasPermission('master-data.view') || $user->hasPermission('master-data.manage')) {
                    return $next($request);
                }
                return $next($request);
            }
            $required = 'master-data.manage';
        } else {
            $required = $permission;
        }

        $allowed = $user->hasPermission($required);

        // Keep management grants backward-compatible: a manage permission includes read access.
        if (! $allowed && str_ends_with($required, '.view')) {
            $allowed = $user->hasPermission(substr($required, 0, -5).'.manage');
        }

        abort_unless($allowed, 403, 'Anda tidak memiliki izin untuk melakukan tindakan ini.');

        return $next($request);
    }
}

