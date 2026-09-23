<?php

namespace Tests\Feature;

use App\Models\Expense\ExpenseRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApprovalCenterAndAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_center_lists_pending_items_and_supports_batch_action(): void
    {
        $role = Role::create(['name' => 'Finance Manager', 'slug' => 'finance-manager']);
        $permissions = collect(['expenses-approvals.view', 'expense.approve', 'expense.create', 'expense.view', 'expense.submit'])->map(fn ($slug) => Permission::create(['name' => $slug, 'slug' => $slug]));
        $role->permissions()->sync($permissions->pluck('id'));
        $user = User::factory()->create(['role_id' => $role->id]);

        $expense = ExpenseRequest::create([
            'request_number' => 'EXP-TEST-001',
            'requester_id' => $user->id,
            'expense_type' => 'reimbursement',
            'request_date' => '2026-09-23',
            'description' => 'Test pending expense',
            'status' => 'submitted',
            'total_amount' => 500,
        ]);

        $this->actingAs($user, 'sanctum');

        // Test pending items endpoint
        $response = $this->getJson('/api/v1/approval-center/pending');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.module', 'expense')
            ->assertJsonPath('data.0.id', $expense->id);

        // Test batch approve action
        $batchResp = $this->postJson('/api/v1/approval-center/batch-action', [
            'action' => 'approve',
            'notes' => 'Batch approved from test',
            'items' => [
                ['module' => 'expense', 'id' => $expense->id],
            ],
        ]);

        $batchResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('processed_count', 1);

        $this->assertEquals('approved', $expense->fresh()->status);
    }

    public function test_universal_attachment_upload_and_download(): void
    {
        Storage::fake('local');

        $role = Role::create(['name' => 'Admin', 'slug' => 'super-admin']);
        $role->permissions()->attach(Permission::create(['name' => 'View expense', 'slug' => 'expense.view']));
        $role->permissions()->attach(Permission::create(['name' => 'Create expense', 'slug' => 'expense.create']));
        $user = User::factory()->create(['role_id' => $role->id]);

        $expense = ExpenseRequest::create([
            'request_number' => 'EXP-ATT-001',
            'requester_id' => $user->id,
            'expense_type' => 'reimbursement',
            'request_date' => '2026-09-23',
            'description' => 'Attachment test expense',
            'status' => 'draft',
            'total_amount' => 200,
        ]);

        $this->actingAs($user, 'sanctum');

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $uploadResp = $this->postJson('/api/v1/attachments/upload', [
            'module' => 'expense',
            'entity_id' => $expense->id,
            'file' => $file,
        ]);

        $uploadResp->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_attachments', 1);

        $this->assertNotEmpty($expense->fresh()->attachments);

        // Test download
        $downloadResp = $this->getJson("/api/v1/attachments/expense/{$expense->id}/0");
        $downloadResp->assertOk();
    }
}
