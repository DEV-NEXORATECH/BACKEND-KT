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
}
