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
        $role = $user?->role;

        if (! $role) {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403);
        }

        $required = $permission;
        if ($permission === 'master-data') {
            $required = match ($request->method()) {
                'GET', 'HEAD' => 'master-data.view',
                default => 'master-data.manage',
            };
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
