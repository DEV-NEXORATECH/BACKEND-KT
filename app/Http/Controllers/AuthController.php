<?php

namespace App\Http\Controllers;

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

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak sesuai.'],
            ]);
        }

        $tokenName = $credentials['remember'] ?? false
            ? 'frontend-login-remembered'
            : 'frontend-login';

        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            ...$rbacPayload->build($user),
        ]);
    }

    public function me(Request $request, RbacPayloadBuilder $rbacPayload): JsonResponse
    {
        return response()->json($rbacPayload->build($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
