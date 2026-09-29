<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Master\Activity;
use App\Models\Master\ApprovalMatrix;
use App\Models\Master\ChartOfAccount;
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

    public function test_external_employee_rate_auto_calculated_from_contract_fee(): void
    {
        $employee = Employee::create([
            'employee_id_number' => 'EXT-001',
            'name' => 'Consultant Expert',
            'email' => 'consultant@example.test',
            'employment_type' => 'external',
            'contract_total_fee' => 10000000,
            'contract_total_days' => 20,
            'default_rate_scheme' => 'daily_capped_8h',
            'is_active' => true,
        ]);

        $this->assertEquals(500000.00, (float) $employee->daily_cost_rate);
        $this->assertEquals(62500.00, (float) $employee->hourly_cost_rate);
    }

    public function test_timesheet_daily_capped_scheme_caps_at_8_hours(): void
    {
        [$staff, $approver, $employee, $project, $activity] = $this->fixture();

        $employee->update([
            'employment_type' => 'external',
            'contract_total_fee' => 10000000,
            'contract_total_days' => 20,
            'daily_cost_rate' => 500000,
            'hourly_cost_rate' => 62500,
            'default_rate_scheme' => 'daily_capped_8h',
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $employee->id,
                'entry_date' => '2026-09-20',
                'hours' => 10.0, // 10 hours worked
                'description' => 'External consulting full day + overtime',
                'project_id' => $project->id,
                'rate_scheme' => 'daily_capped_8h',
                'is_billable' => true,
            ])
            ->assertCreated();

        // Under daily_capped_8h: 10h worked is capped at 8h billable => 8 * 62500 = 500,000 Rp
        $response->assertJsonPath('data.rate_scheme', 'daily_capped_8h')
            ->assertJsonPath('data.applied_rate', 62500)
            ->assertJsonPath('data.billable_hours', 8)
            ->assertJsonPath('data.calculated_amount', 500000);
    }

    public function test_timesheet_hourly_unlimited_scheme_pays_all_hours(): void
    {
        [$staff, $approver, $employee, $project, $activity] = $this->fixture();

        $employee->update([
            'employment_type' => 'external',
            'contract_total_fee' => 10000000,
            'contract_total_days' => 20,
            'daily_cost_rate' => 500000,
            'hourly_cost_rate' => 62500,
            'default_rate_scheme' => 'hourly_unlimited',
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $employee->id,
                'entry_date' => '2026-09-21',
                'hours' => 10.0, // 10 hours worked
                'description' => 'External consulting with hourly rate scheme',
                'project_id' => $project->id,
                'rate_scheme' => 'hourly_unlimited',
                'is_billable' => true,
            ])
            ->assertCreated();

        // Under hourly_unlimited: all 10h are billable => 10 * 62500 = 625,000 Rp
        $response->assertJsonPath('data.rate_scheme', 'hourly_unlimited')
            ->assertJsonPath('data.applied_rate', 62500)
            ->assertJsonPath('data.billable_hours', 10)
            ->assertJsonPath('data.calculated_amount', 625000);
    }

    public function test_external_timesheet_with_general_task(): void
    {
        [$staff, $approver, $employee, $project] = $this->fixture();

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $employee->id,
                'entry_date' => '2026-09-22',
                'hours' => 8.0,
                'description' => 'General consulting support & documentation',
                'task_type' => 'general',
                'project_id' => $project->id,
                'activity_id' => null,
            ])
            ->assertCreated();

        $response->assertJsonPath('data.task_type', 'general')
            ->assertJsonPath('data.activity', null);
    }

    public function test_post_labor_cost_uses_precalculated_amount(): void
    {
        [$staff, $approver, $employee, $project, $activity] = $this->fixture();

        $employee->update([
            'hourly_cost_rate' => 62500,
            'daily_cost_rate' => 500000,
        ]);

        ChartOfAccount::create(['code' => '5101', 'name' => 'Labor Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_header' => false, 'is_active' => true]);
        ChartOfAccount::create(['code' => '2101', 'name' => 'Accrued Payroll', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => false, 'is_active' => true]);

        $create = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $employee->id,
                'entry_date' => '2026-09-20',
                'hours' => 10.0,
                'description' => 'Consulting work capped at 8h',
                'project_id' => $project->id,
                'rate_scheme' => 'daily_capped_8h',
                'is_billable' => true,
            ])
            ->assertCreated();

        $entryId = $create->json('data.id');

        // Submit & direct update to approved status for posting
        \App\Models\Timesheet\TimesheetEntry::where('id', $entryId)->update(['status' => 'approved']);

        $postRes = $this->actingAs($approver, 'sanctum')
            ->postJson('/api/v1/timesheets/entries/post-labor-cost', [
                'timesheet_ids' => [$entryId],
                'posting_date' => '2026-09-20',
            ])
            ->assertOk();

        $postRes->assertJsonPath('posted_count', 1)
            ->assertJsonPath('total_cost', 500000);

        $journal = Journal::where('reference', "TS-{$entryId}")->first();
        $this->assertNotNull($journal);
        $this->assertEquals(500000, (float) $journal->lines()->first()->debit);
    }

    public function test_employee_master_api_store_and_update_with_contract_rate(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        foreach (['master-data', 'master-data.view', 'master-data.create', 'master-data.update', 'master-data.manage'] as $p) {
            $adminRole->permissions()->attach(Permission::firstOrCreate(['slug' => $p], ['name' => $p]));
        }

        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $createRes = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/master/employees', [
                'employee_id_number' => 'EXT-200',
                'name' => 'Senior Technical Consultant',
                'email' => 'tech.consultant@example.test',
                'employment_type' => 'external',
                'contract_total_fee' => 16000000,
                'contract_total_days' => 20,
                'default_rate_scheme' => 'hourly_unlimited',
                'is_active' => true,
            ])
            ->assertCreated();

        $createRes->assertJsonPath('data.employment_type', 'external')
            ->assertJsonPath('data.contract_total_fee', '16000000.00')
            ->assertJsonPath('data.contract_total_days', 20)
            ->assertJsonPath('data.daily_cost_rate', '800000.00')
            ->assertJsonPath('data.hourly_cost_rate', '100000.00');

        $empId = $createRes->json('data.id');

        $updateRes = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/master/employees/{$empId}", [
                'employee_id_number' => 'EXT-200',
                'name' => 'Senior Technical Consultant Lead',
                'employment_type' => 'external',
                'contract_total_fee' => 20000000,
                'contract_total_days' => 20,
                'daily_cost_rate' => 0, // trigger recalculate
                'hourly_cost_rate' => 0,
                'default_rate_scheme' => 'hourly_unlimited',
                'is_active' => true,
            ])
            ->assertOk();

        $updateRes->assertJsonPath('data.daily_cost_rate', '1000000.00')
            ->assertJsonPath('data.hourly_cost_rate', '125000.00');
    }

    public function test_timesheet_update_recalculates_amount(): void
    {
        [$staff, $approver, $employee, $project, $activity] = $this->fixture();

        $employee->update([
            'hourly_cost_rate' => 50000,
            'default_rate_scheme' => 'daily_capped_8h',
        ]);

        $create = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/timesheets/entries', [
                'employee_id' => $employee->id,
                'entry_date' => '2026-09-20',
                'hours' => 5.0,
                'description' => 'Draft entry initial',
                'project_id' => $project->id,
                'activity_id' => $activity->id,
            ])
            ->assertCreated();

        $create->assertJsonPath('data.calculated_amount', 250000);

        $entryId = $create->json('data.id');

        // Update hours to 10 with daily_capped_8h -> should cap at 8h * 50k = 400k
        $update = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/v1/timesheets/entries/{$entryId}", [
                'employee_id' => $employee->id,
                'entry_date' => '2026-09-20',
                'hours' => 10.0,
                'description' => 'Draft entry revised to 10 hours',
                'project_id' => $project->id,
                'activity_id' => $activity->id,
                'rate_scheme' => 'daily_capped_8h',
            ])
            ->assertOk();

        $update->assertJsonPath('data.billable_hours', 8)
            ->assertJsonPath('data.calculated_amount', 400000);
    }

    public function test_timesheet_index_totals_breakdown_by_rate_scheme(): void
    {
        [$staff, $approver, $employee, $project, $activity] = $this->fixture();

        $employee->update(['hourly_cost_rate' => 50000]);

        $this->actingAs($staff, 'sanctum')->postJson('/api/v1/timesheets/entries', [
            'employee_id' => $employee->id,
            'entry_date' => '2026-09-20',
            'hours' => 10.0,
            'description' => 'Entry 1',
            'rate_scheme' => 'daily_capped_8h',
        ])->assertCreated();

        $this->actingAs($staff, 'sanctum')->postJson('/api/v1/timesheets/entries', [
            'employee_id' => $employee->id,
            'entry_date' => '2026-09-21',
            'hours' => 10.0,
            'description' => 'Entry 2',
            'rate_scheme' => 'hourly_unlimited',
        ])->assertCreated();

        $list = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/v1/timesheets/entries')
            ->assertOk();

        $list->assertJsonPath('totals.hours', 20)
            ->assertJsonPath('totals.billable_hours', 18)
            ->assertJsonPath('totals.calculated_amount', 900000) // (8 * 50k) + (10 * 50k) = 400k + 500k = 900k
            ->assertJsonPath('totals.entries', 2);
    }

    private function fixture(): array
    {
        $staffRole = Role::create(['name' => 'Timesheet Staff', 'slug' => 'timesheet-staff']);
        foreach (['timesheet.view', 'timesheet.create', 'timesheet.update', 'timesheet.submit'] as $permission) {
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
