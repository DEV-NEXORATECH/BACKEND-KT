<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class UserSecurityController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('user.manage'), 403);
        return response()->json(['success' => true, 'data' => User::query()->with('role:id,name')->orderBy('name')->get(['id','name','email','role_id','is_active','must_change_password'])->map(fn (User $user) => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role?->name,
            'is_active' => $user->is_active, 'must_change_password' => $user->must_change_password,
        ])]);
    }

    public function sessions(Request $request, ?User $user = null): JsonResponse
    {
        $user ??= $request->user();
        $this->ensureCanManage($request, $user);
        $currentId = $user->is($request->user()) ? $request->user()->currentAccessToken()?->id : null;

        return response()->json(['success' => true, 'data' => $user->tokens()->latest('id')->get()->map(fn (PersonalAccessToken $token) => [
            'id' => $token->id, 'name' => $token->name, 'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(), 'is_current' => $token->id === $currentId,
        ])->values()]);
    }

    public function revokeSession(Request $request, int $tokenId, ?User $user = null): JsonResponse
    {
        $user ??= $request->user();
        $this->ensureCanManage($request, $user);
        $token = $user->tokens()->findOrFail($tokenId);
        $token->delete();
        $this->audit($request, 'REVOKE_SESSION', $user, ['token_id' => $tokenId]);
        return response()->json(['success' => true, 'message' => 'Sesi perangkat berhasil dicabut.']);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => true])->save();
        $user->tokens()->delete();
        $this->audit($request, 'ADMIN_RESET_PASSWORD', $user, ['all_sessions_revoked' => true]);
        return response()->json(['success' => true, 'message' => 'Password direset dan seluruh sesi pengguna dicabut.']);
    }

    public function generatePassword(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->hasPermission('user.manage'), 403);
        $password = 'Kt!'.Str::password(11, true, true, true, false);
        $user->forceFill(['password' => Hash::make($password), 'must_change_password' => true])->save();
        $user->tokens()->delete();
        $this->audit($request, 'ADMIN_GENERATE_PASSWORD', $user, ['all_sessions_revoked' => true]);
        return response()->json(['success' => true, 'message' => 'Password baru berhasil dibuat. Sampaikan password ini kepada pengguna secara aman.', 'password' => $password]);
    }

    public function setActive(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['is_active' => ['present', 'boolean']]);
        $user->update(['is_active' => $data['is_active']]);
        if (! $data['is_active']) $user->tokens()->delete();
        if ($employee = $user->employee()->first()) $employee->update(['is_active' => $data['is_active']]);
        $this->audit($request, $data['is_active'] ? 'ACTIVATE_USER' : 'DEACTIVATE_USER', $user, ['is_active' => $data['is_active']]);
        return response()->json(['success' => true, 'message' => $data['is_active'] ? 'Akun pengguna diaktifkan.' : 'Akun dinonaktifkan dan semua sesi dicabut.']);
    }

    private function ensureCanManage(Request $request, User $target): void
    {
        if (! $target->is($request->user()) && ! $request->user()->hasPermission('user.manage')) abort(403, 'Tidak boleh mengelola sesi pengguna lain.');
    }

    private function audit(Request $request, string $action, User $user, array $newValues): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'module' => 'user_security', 'action' => $action, 'entity_type' => User::class, 'entity_id' => $user->id, 'new_values' => $newValues, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
