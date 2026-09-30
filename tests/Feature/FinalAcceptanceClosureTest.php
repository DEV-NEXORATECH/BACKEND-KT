<?php

namespace Tests\Feature;

use App\Models\Master\Department;
use App\Models\Master\Employee;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Project;
use App\Models\Master\Position;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalAcceptanceClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_reporting_rejects_direct_indirect_and_self_cycles_but_allows_chain(): void
    {
        [$user, $department] = $this->masterFixture();
        $a = Position::create(['code' => 'A', 'name' => 'A', 'department_id' => $department->id, 'is_active' => true]);
        $b = Position::create(['code' => 'B', 'name' => 'B', 'department_id' => $department->id, 'reports_to_position_id' => $a->id, 'is_active' => true]);
        $c = Position::create(['code' => 'C', 'name' => 'C', 'department_id' => $department->id, 'reports_to_position_id' => $b->id, 'is_active' => true]);
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/master/positions/{$a->id}", ['name' => 'A', 'reports_to_position_id' => $b->id])->assertUnprocessable();
        $this->putJson("/api/v1/master/positions/{$a->id}", ['name' => 'A', 'reports_to_position_id' => $c->id])->assertUnprocessable();
        $this->putJson("/api/v1/master/positions/{$a->id}", ['name' => 'A', 'reports_to_position_id' => $a->id])->assertUnprocessable();
        $this->putJson("/api/v1/master/positions/{$c->id}", ['name' => 'C', 'reports_to_position_id' => $a->id])->assertOk();
    }

    public function test_employee_quality_endpoint_aggregates_fixture_rules(): void
    {
        [$user, $department] = $this->masterFixture();
        Employee::create(['employee_id_number' => 'EMP001', 'name' => 'Valid', 'department_id' => $department->id, 'position_id' => Position::create(['code' => 'P1', 'name' => 'P1', 'is_active' => true])->id, 'is_active' => true]);
        Employee::create(['employee_id_number' => '', 'name' => 'Missing ID', 'is_active' => true]);
        Employee::create(['employee_id_number' => 'DUP001', 'name' => 'Dup 1', 'is_active' => true]);
        Employee::create(['employee_id_number' => 'DUP002', 'name' => 'Dup 2 (DB unique constraint)', 'is_active' => true]);
        Employee::create(['employee_id_number' => 'EMP005', 'name' => 'Missing Dept', 'is_active' => true]);
        Employee::create(['employee_id_number' => 'EMP006', 'name' => 'Missing Position', 'department_id' => $department->id, 'is_active' => true]);
        Employee::create(['employee_id_number' => 'EMP007', 'name' => 'Inactive', 'is_active' => false]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/master/employees/quality')->assertOk()->assertJsonPath('data.total_employee', 7)->assertJsonPath('data.missing_staff_id', 1)->assertJsonPath('data.duplicate_staff_id', 0)->assertJsonPath('data.missing_department', 5)->assertJsonPath('data.missing_position', 6)->assertJsonPath('data.inactive', 1);
    }

    public function test_bank_project_matrix_filters_and_rejects_cross_project_accounts(): void
    {
        $role = Role::create(['name' => 'Dashboard Acceptance', 'slug' => 'dashboard-acceptance']);
        $role->permissions()->attach(Permission::create(['name' => 'Accounting view', 'slug' => 'accounting.view']));
        $user = User::factory()->create(['role_id' => $role->id]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $cash = ChartOfAccount::create(['code' => '1100', 'name' => 'Cash', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bankA = BankAccount::create(['bank_name' => 'Bank A', 'account_number' => 'A-001', 'account_name' => 'A', 'currency_id' => $currency->id, 'gl_account_id' => $cash->id, 'opening_balance' => 100, 'is_active' => true]);
        $bankB = BankAccount::create(['bank_name' => 'Bank B', 'account_number' => 'B-001', 'account_name' => 'B', 'currency_id' => $currency->id, 'gl_account_id' => $cash->id, 'opening_balance' => 200, 'is_active' => true]);
        $projectA = Project::create(['code' => 'PA', 'name' => 'Project A', 'bank_account_id' => $bankA->id, 'is_active' => true]);
        $projectB = Project::create(['code' => 'PB', 'name' => 'Project B', 'bank_account_id' => $bankB->id, 'is_active' => true]);
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/accounting/dashboard?project_id={$projectA->id}&bank_account_id={$bankA->id}")->assertOk()->assertJsonPath('cash_and_bank.0.id', $bankA->id)->assertJsonMissing(['id' => $bankB->id]);
        $this->getJson("/api/v1/accounting/dashboard?project_id={$projectB->id}&bank_account_id={$bankB->id}")->assertOk()->assertJsonPath('cash_and_bank.0.id', $bankB->id);
        $this->getJson("/api/v1/accounting/dashboard?project_id={$projectA->id}&bank_account_id={$bankB->id}")->assertUnprocessable();
        BankAccount::whereKey($bankA->id)->update(['is_active' => false]);
        $this->getJson("/api/v1/accounting/dashboard?project_id={$projectA->id}&bank_account_id={$bankA->id}")->assertOk()->assertJsonCount(0, 'cash_and_bank');
    }

    private function masterFixture(): array
    {
        $role = Role::create(['name' => 'Master Acceptance', 'slug' => 'master-acceptance']);
        $role->permissions()->attach(Permission::create(['name' => 'Master view', 'slug' => 'master-data.view']));
        $role->permissions()->attach(Permission::create(['name' => 'Master manage', 'slug' => 'master-data.manage']));
        return [User::factory()->create(['role_id' => $role->id]), Department::create(['code' => 'D-ACC', 'name' => 'Accounting', 'is_active' => true])];
    }
}
