<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiDeviceLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_user_can_keep_multiple_active_login_sessions(): void
    {
        $role = Role::create(['name' => 'Staff', 'slug' => 'staff']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'multi-login@example.test',
            'password' => Hash::make('password'),
        ]);

        $firstLogin = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json();

        $secondLogin = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])->assertOk()->json();

        $this->assertNotSame($firstLogin['token'], $secondLogin['token']);
        $this->assertSame(2, $user->tokens()->count());

        $this->withToken($firstLogin['token'])->getJson('/api/me')->assertOk();
        $this->withToken($secondLogin['token'])->getJson('/api/me')->assertOk();

        $this->withToken($firstLogin['token'])->postJson('/api/logout')->assertOk();

        $this->withToken($secondLogin['token'])->getJson('/api/me')->assertOk();
        $this->assertSame(1, $user->fresh()->tokens()->count());
    }
}
