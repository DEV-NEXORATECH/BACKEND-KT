<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Permission;
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

    public function test_user_can_revoke_an_individual_session_and_admin_can_deactivate_account(): void
    {
        $role = Role::create(['name' => 'Security Admin', 'slug' => 'security-admin']);
        $role->permissions()->attach(Permission::create(['name' => 'Manage users', 'slug' => 'user.manage']));
        $admin = User::factory()->create(['role_id' => $role->id, 'password' => Hash::make('password')]);
        $target = User::factory()->create(['role_id' => $role->id, 'email' => 'target@example.test', 'password' => Hash::make('password')]);
        $first = $target->createToken('device-one')->plainTextToken;
        $second = $target->createToken('device-two')->plainTextToken;
        $tokenId = $target->tokens()->where('name', 'device-one')->value('id');

        $this->withToken($second)->getJson('/api/me/sessions')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($second)->deleteJson("/api/me/sessions/{$tokenId}")->assertOk();
        $this->assertSame(1, $target->fresh()->tokens()->count());

        $adminToken = $admin->createToken('admin')->plainTextToken;
        $this->withToken($adminToken)->putJson("/api/users/{$target->id}/active", ['is_active' => false])->assertOk();
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(0, $target->tokens()->count());
        $this->postJson('/api/login', ['email' => $target->email, 'password' => 'password'])->assertUnprocessable();
    }
}
