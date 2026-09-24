<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->payload($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:150'],
        ]);
        $before = $this->payload($user);
        // The employee relation is currently keyed by email; obtain it before an
        // email change so an existing employee record is never orphaned.
        $employee = $user->employee()->first();
        $user->update(['name' => $data['name'], 'email' => $data['email']]);
        if ($employee) {
            $employee->update([
                'name' => $data['name'], 'email' => $data['email'],
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_account_holder' => $data['bank_account_holder'] ?? null,
            ]);
        }
        $user->refresh();
        $this->audit($request, 'UPDATE_PROFILE', $user->id, $before, $this->payload($user));
        return response()->json(['success' => true, 'message' => 'Profil berhasil diperbarui.', 'data' => $this->payload($user)]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string'], 'new_password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) throw ValidationException::withMessages(['current_password' => ['Password saat ini tidak sesuai.']]);
        $user->forceFill(['password' => Hash::make($data['new_password']), 'must_change_password' => false])->save();
        if ($currentTokenId = $request->user()->currentAccessToken()?->id) {
            $request->user()->tokens()->whereKeyNot($currentTokenId)->delete();
        }
        $this->audit($request, 'CHANGE_PASSWORD', $user->id, null, ['other_sessions_revoked' => true]);
        return response()->json(['success' => true, 'message' => 'Password berhasil diubah. Sesi lain telah dikeluarkan.']);
    }

    private function payload($user): array
    {
        $employee = $user->employee()->with(['department', 'officeLocation'])->first();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'must_change_password' => (bool) $user->must_change_password,
            'role' => $user->role?->name,
            'employee' => $employee ? [
                'id_number' => $employee->employee_id_number,
                'department' => $employee->department?->name,
                'office' => $employee->officeLocation?->name,
                'position' => $employee->position,
                'bank_name' => $employee->bank_name,
                'bank_account_number' => $employee->bank_account_number,
                'bank_account_holder' => $employee->bank_account_holder,
            ] : null,
        ];
    }

    private function audit(Request $request, string $action, int $id, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'profile',
            'platform' => strtolower($request->header('X-Client-Platform', 'web')),
            'action' => $action,
            'entity_type' => get_class($request->user()),
            'entity_id' => $id,
            'previous_values' => $before,
            'new_values' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
