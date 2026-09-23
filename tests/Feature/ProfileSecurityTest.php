<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_own_profile_and_audit_is_written(): void
    {
        $role = Role::create(['name' => 'Staff', 'slug' => 'staff']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/profile', [
                'name' => 'Updated Staff',
                'email' => 'updated.staff@example.test',
                'bank_name' => 'BCA',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'updated.staff@example.test');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'updated.staff@example.test']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'module' => 'profile', 'action' => 'UPDATE_PROFILE']);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/password', [
                'current_password' => 'incorrect-password',
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('correct-password', $user->fresh()->password));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'CHANGE_PASSWORD']);
    }

    public function test_password_change_updates_hash_and_is_audited(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/password', [
                'current_password' => 'correct-password',
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame('CHANGE_PASSWORD', AuditLog::query()->latest('id')->value('action'));
    }
}
