<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflowRun;
use App\Models\Expense\ExpenseRequest;
use App\Models\Master\ApprovalMatrix;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_follows_configured_two_level_approval_matrix(): void
    {
        $requesterRole = $this->role('Requester', ['expense.submit']);
        $budgetHolderRole = $this->role('Budget Holder', ['expense.approve']);
        $financeRole = $this->role('Finance', ['expense.approve']);
        $requester = User::factory()->create(['role_id' => $requesterRole->id]);
        $budgetHolder = User::factory()->create(['role_id' => $budgetHolderRole->id]);
        $finance = User::factory()->create(['role_id' => $financeRole->id]);

        ApprovalMatrix::create(['module' => 'expense', 'level' => 1, 'min_amount' => 0, 'role_id' => $budgetHolderRole->id, 'is_active' => true]);
        ApprovalMatrix::create(['module' => 'expense', 'level' => 2, 'min_amount' => 0, 'role_id' => $financeRole->id, 'is_active' => true]);
        $expense = ExpenseRequest::create(['request_number' => 'EXP-WF-001', 'requester_id' => $requester->id, 'expense_type' => 'reimbursement', 'request_date' => '2026-09-23', 'description' => 'Two-stage approval', 'attachments' => ['receipt.pdf'], 'total_amount' => 500, 'status' => 'draft']);

        $this->actingAs($requester, 'sanctum')->postJson("/api/v1/expenses/requests/{$expense->id}/submit")->assertOk();
        $this->actingAs($finance, 'sanctum')->postJson("/api/v1/expenses/requests/{$expense->id}/approve")->assertUnprocessable();
        $this->actingAs($budgetHolder, 'sanctum')->postJson("/api/v1/expenses/requests/{$expense->id}/approve")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->actingAs($finance, 'sanctum')->postJson("/api/v1/expenses/requests/{$expense->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', ApprovalWorkflowRun::query()->first()->status);
        $this->assertDatabaseCount('approval_workflow_actions', 2);
    }

    private function role(string $name, array $permissions): Role
    {
        $role = Role::create(['name' => $name, 'slug' => str($name)->slug()->toString()]);
        foreach ($permissions as $permission) $role->permissions()->attach(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]));
        return $role;
    }
}
