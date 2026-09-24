<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Rbac\RbacPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, RbacPayloadBuilder $rbacPayload): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $user = \App\Models\User::with(['role.permissions', 'role.menus'])
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            if ($user && ! Hash::check($credentials['password'], $user->password)) {
                $this->writeLoginAudit($request, $user, 'LOGIN_FAILED', 'Invalid password for '.$credentials['email']);
            } elseif ($user && ! $user->is_active) {
                $this->writeLoginAudit($request, $user, 'LOGIN_BLOCKED', 'Inactive user tried to login');
            } else {
                $this->writeLoginAudit($request, null, 'LOGIN_FAILED', 'Unknown user '.$credentials['email']);
            }

            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak sesuai.'],
            ]);
        }

        $tokenName = $credentials['remember'] ?? false
            ? 'frontend-login-remembered'
            : 'frontend-login';

        $token = $user->createToken($tokenName)->plainTextToken;
        $this->writeLoginAudit($request, $user, 'LOGIN', 'Login berhasil');

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'must_change_password' => (bool) $user->must_change_password,
            ...$rbacPayload->build($user),
        ]);
    }

    public function me(Request $request, RbacPayloadBuilder $rbacPayload): JsonResponse
    {
        return response()->json($rbacPayload->build($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $this->writeLoginAudit($request, $user, 'LOGOUT', 'Logout');
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    private function writeLoginAudit(Request $request, ?\App\Models\User $user, string $action, string $detail): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => $user?->id,
                'module' => 'auth',
                'platform' => strtolower((string) $request->header('X-Client-Platform', 'web')),
                'action' => $action,
                'entity_type' => \App\Models\User::class,
                'entity_id' => $user?->id,
                'new_values' => ['detail' => $detail],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // Login must never fail because of audit persistence.
        }
    }
}
