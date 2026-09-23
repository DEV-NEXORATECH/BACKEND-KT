<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\Master\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_permission_protected_and_persisted(): void
    {
        $role = Role::create(['name' => 'System Owner', 'slug' => 'system-owner']);
        $role->permissions()->attach(Permission::create(['name' => 'Manage settings', 'slug' => 'settings.manage']));
        $user = User::factory()->create(['role_id' => $role->id]);
        $organization = Organization::create(['code' => 'KT', 'name' => 'Kaoem Telapak', 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/settings', [
                'organization' => ['name' => 'Kaoem Telapak Foundation', 'legal_name' => 'Kaoem Telapak Indonesia', 'fiscal_year_start_month' => 1],
                'policy' => ['enable_multi_level_approval' => true, 'require_receipt_for_expense' => true, 'auto_escalate_days' => 3, 'hard_lock_closed_periods' => true, 'enable_over_budget_blocking' => true],
            ])
            ->assertOk();

        $this->assertSame('Kaoem Telapak Foundation', $organization->fresh()->name);
        $this->assertSame(3, ApplicationSetting::query()->where('key', 'approval_budget_policy')->first()->value['auto_escalate_days']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'module' => 'settings']);
    }
}
