<?php

namespace Tests\Feature;

use App\Models\Expense\ExpenseRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalEscalationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_approval_reminder_is_queued_once_per_approver_per_day(): void
    {
        $role = Role::create(['name' => 'Approver', 'slug' => 'approval-reminder-role']);
        $role->permissions()->attach(Permission::create(['name' => 'Approve Expense', 'slug' => 'expense.approve']));
        $approver = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $requester = User::factory()->create();
        $expense = ExpenseRequest::create(['request_number' => 'EXP-REMINDER-001', 'requester_id' => $requester->id, 'expense_type' => 'reimbursement', 'request_date' => now()->subDays(5)->toDateString(), 'description' => 'Overdue approval', 'status' => 'submitted', 'total_amount' => 100]);
        DB::table('expense_requests')->where('id', $expense->id)->update(['updated_at' => now()->subDays(4)]);

        $this->artisan('app:process-approval-escalations --days=3')->assertSuccessful();
        $this->assertDatabaseHas('notifications', ['user_id' => $approver->id, 'type' => 'approval', 'action_url' => '/approvals']);

        $this->artisan('app:process-approval-escalations --days=3')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);
    }
}
