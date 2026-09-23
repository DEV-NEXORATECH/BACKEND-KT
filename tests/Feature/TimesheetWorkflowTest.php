<?php

namespace Tests\Feature;

use App\Models\Master\Activity;
use App\Models\Master\ApprovalMatrix;
use App\Models\Master\Department;
use App\Models\Master\Employee;
use App\Models\Master\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_timesheet_can_be_submitted_and_approved_with_staff_scope(): void
    {
        [$staff, $approver, $employee, $project, $activity] = $this->fixture();
        ApprovalMatrix::create(['module' => 'timesheet', 'level' => 1, 'min_amount' => 0, 'role_id' => $approver->role_id, 'is_active' => true]);

        $create = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $employee->id,
                'entry_date' => '2026-09-20',
                'hours' => 7.5,
                'description' => 'Field monitoring and partner coordination',
                'project_id' => $project->id,
                'activity_id' => $activity->id,
                'is_billable' => true,
                'supervisor_id' => $approver->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $entryId = $create->json('data.id');

        $this->postJson("/api/v1/timesheets/entries/{$entryId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->actingAs($approver, 'sanctum')
            ->postJson("/api/v1/timesheets/entries/{$entryId}/approve", ['notes' => 'Approved by supervisor'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.decision_notes', 'Approved by supervisor');

        $otherStaff = User::factory()->create(['role_id' => $staff->role_id, 'email' => 'other@example.test']);

        $this->actingAs($otherStaff, 'sanctum')
            ->getJson('/api/v1/timesheets/entries')
            ->assertOk()
            ->assertJsonPath('totals.entries', 0);
    }

    private function fixture(): array
    {
        $staffRole = Role::create(['name' => 'Timesheet Staff', 'slug' => 'timesheet-staff']);
        foreach (['timesheet.view', 'timesheet.create', 'timesheet.submit'] as $permission) {
            $staffRole->permissions()->attach(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]));
        }
        $approverRole = Role::create(['name' => 'Timesheet Approver', 'slug' => 'timesheet-approver']);
        foreach (['timesheet.view', 'timesheet.approve'] as $permission) {
            $approverRole->permissions()->attach(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]));
        }

        $staff = User::factory()->create(['role_id' => $staffRole->id, 'email' => 'staff@example.test']);
        $approver = User::factory()->create(['role_id' => $approverRole->id, 'email' => 'supervisor@example.test']);
        $department = Department::create(['code' => 'OPS', 'name' => 'Operations', 'is_active' => true]);
        $employee = Employee::create(['employee_id_number' => 'EMP-001', 'name' => 'Staff One', 'email' => $staff->email, 'department_id' => $department->id, 'is_active' => true]);
        $project = Project::create(['code' => 'PRJ-001', 'name' => 'Project One', 'program_id' => null, 'is_active' => true]);
        $activity = Activity::create(['project_id' => $project->id, 'code' => 'ACT-001', 'name' => 'Field Activity', 'is_active' => true]);

        return [$staff, $approver, $employee, $project, $activity];
    }
}
