<?php

namespace Tests\Feature;

use App\Models\Master\Project;
use App\Models\Master\ProjectLogframe;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFrameworkRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_framework_is_read_and_saved_with_the_integrated_relation_number(): void
    {
        $role = Role::create(['name' => 'Planning', 'slug' => 'planning']);
        $role->permissions()->attach(Permission::create(['name' => 'Manage master data', 'slug' => 'master-data.manage']));
        $user = User::factory()->create(['role_id' => $role->id]);

        $projectA = Project::create(['code' => 'PRJ-A', 'relation_no' => 'KT-REL-A', 'name' => 'Project A', 'is_active' => true]);
        $projectB = Project::create(['code' => 'PRJ-B', 'relation_no' => 'KT-REL-B', 'name' => 'Project B', 'is_active' => true]);
        ProjectLogframe::create(['project_id' => $projectA->id, 'relation_no' => 'KT-REL-A', 'level' => 'impact', 'code' => 'IMP-01', 'description' => 'Impact A', 'is_active' => true]);
        ProjectLogframe::create(['project_id' => $projectB->id, 'relation_no' => 'KT-REL-B', 'level' => 'impact', 'code' => 'IMP-01', 'description' => 'Impact B', 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/master/project-logframes?project_id={$projectA->id}&relation_no=KT-REL-A")
            ->assertOk()
            ->assertJsonPath('scope.relation_no', 'KT-REL-A')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Impact A');

        $this->postJson('/api/v1/master/project-logframes', [
            'project_id' => $projectA->id,
            'relation_no' => 'KT-REL-A',
            'level' => 'outcome',
            'code' => 'OUT-01',
            'description' => 'Outcome A',
        ])->assertCreated()->assertJsonPath('data.relation_no', 'KT-REL-A');

        $this->assertDatabaseHas('project_logframes', [
            'project_id' => $projectA->id,
            'relation_no' => 'KT-REL-A',
            'code' => 'OUT-01',
        ]);

        $this->getJson("/api/v1/master/project-logframes?project_id={$projectA->id}&relation_no=KT-REL-B")
            ->assertStatus(422)
            ->assertJsonValidationErrors('relation_no');
    }
}
