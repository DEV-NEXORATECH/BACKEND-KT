<?php

namespace Tests\Feature;

use App\Models\Master\Activity;
use App\Models\Master\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProjectWorkplanIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithMasterPermission(): User
    {
        $role = Role::create(['name' => 'Planning Manager', 'slug' => 'planning-manager']);
        $role->permissions()->attach(Permission::create(['name' => 'Manage master data', 'slug' => 'master-data.manage']));
        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_workplan_rejects_activity_from_another_project(): void
    {
        $user = $this->userWithMasterPermission();
        $projectA = Project::create(['code' => 'P-A', 'name' => 'Project A', 'is_active' => true]);
        $projectB = Project::create(['code' => 'P-B', 'name' => 'Project B', 'is_active' => true]);
        $activityA = Activity::create(['project_id' => $projectA->id, 'code' => 'A-A', 'name' => 'Activity A', 'is_active' => true]);
        $activityB = Activity::create(['project_id' => $projectB->id, 'code' => 'A-B', 'name' => 'Activity B', 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/master/project-workplans', [
                'project_id' => $projectA->id,
                'activity_id' => $activityA->id,
                'activity' => 'Valid activity',
            ])->assertCreated();

        $invalid = $this->postJson('/api/v1/master/project-workplans', [
            'project_id' => $projectA->id,
            'activity_id' => $activityB->id,
            'activity' => 'Cross project activity',
        ]);

        $invalid->assertStatus(422)->assertJsonValidationErrors('activity_id');
        $this->assertDatabaseMissing('project_workplans', ['activity_id' => $activityB->id]);
    }

    public function test_workplan_update_rejects_changing_to_another_project_activity(): void
    {
        $user = $this->userWithMasterPermission();
        $projectA = Project::create(['code' => 'P-UA', 'name' => 'Project A', 'is_active' => true]);
        $projectB = Project::create(['code' => 'P-UB', 'name' => 'Project B', 'is_active' => true]);
        $activityA = Activity::create(['project_id' => $projectA->id, 'code' => 'A-UA', 'name' => 'Activity A', 'is_active' => true]);
        $activityB = Activity::create(['project_id' => $projectB->id, 'code' => 'A-UB', 'name' => 'Activity B', 'is_active' => true]);

        $this->actingAs($user, 'sanctum');
        $created = $this->postJson('/api/v1/master/project-workplans', [
            'project_id' => $projectA->id,
            'activity_id' => $activityA->id,
            'activity' => 'Valid activity',
        ])->assertCreated()->json('data');

        $this->putJson("/api/v1/master/project-workplans/{$created['id']}", [
            'activity_id' => $activityB->id,
        ])->assertStatus(422)->assertJsonValidationErrors('activity_id');
    }

    public function test_import_rejects_cross_project_activity_atomically(): void
    {
        $user = $this->userWithMasterPermission();
        $projectA = Project::create(['code' => 'P-IA', 'name' => 'Project A', 'is_active' => true]);
        $projectB = Project::create(['code' => 'P-IB', 'name' => 'Project B', 'is_active' => true]);
        $activityB = Activity::create(['project_id' => $projectB->id, 'code' => 'A-IB', 'name' => 'Activity B', 'is_active' => true]);
        $rows = array_fill(0, 10, 'header');
        $rows[] = "OUT-1,ACT-1,Invalid activity,Owner,2026-01-01,2026-01-31,{$activityB->id}";
        $file = UploadedFile::fake()->createWithContent('workplan.csv', implode("\n", $rows));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/master/project-workplans/import', [
                'project_id' => $projectA->id,
                'file' => $file,
            ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('project_workplans', 0);
    }
}
