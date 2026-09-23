<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_endpoints_require_granular_permissions(): void
    {
        $role = Role::create(['name' => 'Read only', 'slug' => 'read-only']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/master/organizations')
            ->assertForbidden();

        $role->permissions()->attach(Permission::create([
            'name' => 'View master data',
            'slug' => 'master-data.view',
        ]));

        $this->getJson('/api/v1/master/organizations')->assertOk();

        $this->postJson('/api/v1/master/organizations', [
            'code' => 'TEST',
            'name' => 'Test Organization',
        ])->assertForbidden();
    }

    public function test_procurement_master_requires_permission_for_each_action(): void
    {
        $role = Role::create(['name' => 'Procurement master reader', 'slug' => 'procurement-master-reader']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/master/procurement-items')
            ->assertForbidden();

        $role->permissions()->attach(Permission::create([
            'name' => 'View master data',
            'slug' => 'master-data.view',
        ]));

        $this->getJson('/api/v1/master/procurement-items')->assertOk();
        $this->postJson('/api/v1/master/vendor-categories', [
            'code' => 'CONSULTANT',
            'name' => 'Consultant',
        ])->assertForbidden();

        $role->permissions()->attach(Permission::create([
            'name' => 'Manage master data',
            'slug' => 'master-data.manage',
        ]));

        $this->postJson('/api/v1/master/vendor-categories', [
            'code' => 'CONSULTANT',
            'name' => 'Consultant',
        ])->assertCreated()->assertJsonPath('data.code', 'CONSULTANT');
    }
}
