<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailAndRbacPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_mutation_writes_audit_log(): void
    {
        $role = Role::create(['name' => 'Master Admin', 'slug' => 'master-admin']);
        $role->permissions()->attach(Permission::create([
            'name' => 'Manage master data',
            'slug' => 'master-data.manage',
        ]));

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/master/organizations', [
                'code' => 'AUDIT',
                'name' => 'Audit Organization',
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'organizations',
            'action' => 'CREATE',
            'entity_type' => \App\Models\Master\Organization::class,
        ]);

        $this->assertSame('AUDIT', AuditLog::query()->first()->new_values['code'] ?? null);
    }

    public function test_me_payload_includes_parent_menu_for_allowed_child(): void
    {
        $role = Role::create(['name' => 'Child Only', 'slug' => 'child-only']);
        $parent = Menu::create([
            'title' => 'Administration',
            'slug' => 'administration',
            'path' => '/administration',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $child = Menu::create([
            'parent_id' => $parent->id,
            'title' => 'Role Access',
            'slug' => 'role-access',
            'path' => '/administration/role-access',
            'sort_order' => 11,
            'is_active' => true,
        ]);

        $role->menus()->attach($child->id);
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->json();

        $this->assertSame('administration', $response['menus'][0]['slug']);
        $this->assertSame('role-access', $response['menus'][0]['children'][0]['slug']);
    }
}
