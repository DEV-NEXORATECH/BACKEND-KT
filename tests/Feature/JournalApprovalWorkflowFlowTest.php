<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Master\ApprovalMatrix;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Employee;
use App\Models\Master\FiscalYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalApprovalWorkflowFlowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $fy2026 = FiscalYear::create([
            'year' => 2026,
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'is_active' => true,
        ]);
        $fy2027 = FiscalYear::create([
            'year' => 2027,
            'name' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'open',
            'is_active' => false,
        ]);

        $adminRole = Role::firstOrCreate(['slug' => 'finance-admin'], ['name' => 'Finance Admin']);
        foreach ([
            'master-data.view',
            'master-data.manage',
            'settings.manage',
            'accounting.journal.view',
            'accounting.journal.create',
            'accounting.journal.update',
            'accounting.journal.submit',
            'accounting.journal.review',
            'accounting.journal.post',
        ] as $slug) {
            $adminRole->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }

        $readOnlyRole = Role::firstOrCreate(['slug' => 'program-staff-test'], ['name' => 'Program Staff']);
        foreach (['accounting.journal.view', 'accounting.journal.create', 'accounting.journal.submit'] as $slug) {
            $readOnlyRole->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }

        $empApprover = Employee::create([
            'employee_id_number' => 'EMP-APR-01',
            'name' => 'Winda Apriani',
            'email' => 'winda@kaoemtelapak.test',
        ]);
        $empOther = Employee::create([
            'employee_id_number' => 'EMP-OTH-02',
            'name' => 'Sarah Megumi',
            'email' => 'sarah@kaoemtelapak.test',
        ]);

        $admin = User::factory()->create(['role_id' => $adminRole->id, 'name' => 'Admin Setting', 'email' => 'admin@kaoemtelapak.test']);
        $requester = User::factory()->create(['role_id' => $adminRole->id, 'name' => 'Accounting Requester', 'email' => 'requester@kaoemtelapak.test']);
        $approver = User::factory()->create(['role_id' => $adminRole->id, 'employee_id' => $empApprover->id, 'name' => 'Winda Apriani', 'email' => 'winda@kaoemtelapak.test']);
        $ineligibleUser = User::factory()->create(['role_id' => $readOnlyRole->id, 'employee_id' => $empOther->id, 'name' => 'Sarah Megumi', 'email' => 'sarah@kaoemtelapak.test']);

        $bank = ChartOfAccount::create(['code' => '1110', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit']);
        $expense = ChartOfAccount::create(['code' => '1120', 'name' => 'Petty Cash', 'account_type' => 'asset', 'normal_balance' => 'debit']);

        return compact('fy2026', 'fy2027', 'adminRole', 'readOnlyRole', 'empApprover', 'empOther', 'admin', 'requester', 'approver', 'ineligibleUser', 'bank', 'expense');
    }

    private function journalPayload(array $f, string $date = '2026-06-15'): array
    {
        return [
            'journal_date' => $date,
            'description' => 'Journal approval flow integration test',
            'lines' => [
                ['account_id' => $f['expense']->id, 'line_description' => 'Debit expense', 'debit' => 500000],
                ['account_id' => $f['bank']->id, 'line_description' => 'Credit bank', 'credit' => 500000],
            ],
        ];
    }

    public function test_approval_matrix_settings_preserves_global_rules_inactive_rows_and_validates_person_consistency(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'], 'sanctum');

        $globalResp = $this->withHeaders(['X-Fiscal-Year-Id' => (string) $f['fy2026']->id])
            ->postJson('/api/v1/master/approval-matrices', [
                'module' => 'journal',
                'fiscal_year_id' => null,
                'level' => 1,
                'min_amount' => 0,
                'role_id' => $f['readOnlyRole']->id,
                'user_id' => $f['ineligibleUser']->id,
                'employee_id' => $f['empOther']->id,
                'approver_title' => 'Project Manager / Budget Holder',
                'is_active' => false,
            ]);

        $globalResp->assertCreated()
            ->assertJsonPath('data.fiscal_year_id', null)
            ->assertJsonPath('data.fiscal_year_display', 'All Fiscal Years (Global)')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.user_has_journal_post', false);

        // Selecting User A + Employee B in Settings must fail validation instead of silently breaking ApprovalIdentityResolver.
        $this->postJson('/api/v1/master/approval-matrices', [
            'module' => 'journal',
            'fiscal_year_id' => $f['fy2026']->id,
            'level' => 2,
            'min_amount' => 0,
            'user_id' => $f['approver']->id,
            'employee_id' => $f['empOther']->id,
            'approver_title' => 'Mismatch Test',
            'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $scopedResp = $this->postJson('/api/v1/master/approval-matrices', [
            'module' => 'journal',
            'fiscal_year_id' => $f['fy2026']->id,
            'level' => 3,
            'min_amount' => 0,
            'user_id' => $f['approver']->id,
            'employee_id' => $f['empApprover']->id,
            'approver_title' => 'Year Display Check',
            'is_active' => true,
        ])->assertCreated();
        $scopedResp->assertJsonPath('data.fiscal_year_display', '2026');

        // paginate=false on Settings must return both inactive and global rows.
        $listResp = $this->getJson('/api/v1/master/approval-matrices?paginate=false&module=journal&fiscal_year_id=' . $f['fy2026']->id)
            ->assertOk();
        $this->assertTrue(collect($listResp->json('data'))->contains(fn ($row) => $row['id'] === $globalResp->json('data.id') && $row['fiscal_year_id'] === null && $row['is_active'] === false));

        // Non-user-manager admin can still load enriched approver options.
        $this->getJson('/api/v1/master/approval-matrices/approvers?module=journal')
            ->assertOk()
            ->assertJsonFragment(['id' => $f['approver']->id, 'can_approve_journal' => true])
            ->assertJsonFragment(['id' => $f['ineligibleUser']->id, 'can_approve_journal' => false]);
    }

    public function test_journal_assign_approver_reject_and_cross_year_approval_center_flow(): void
    {
        $f = $this->fixture();

        // Assign Level 1 to Sarah (whose role lacks accounting.journal.post), simulating missing/ineligible approver on Journal table.
        ApprovalMatrix::create([
            'module' => 'journal',
            'fiscal_year_id' => $f['fy2026']->id,
            'level' => 1,
            'min_amount' => 0,
            'role_id' => $f['readOnlyRole']->id,
            'user_id' => $f['ineligibleUser']->id,
            'employee_id' => $f['empOther']->id,
            'approver_title' => 'Finance Verification',
            'is_active' => true,
        ]);

        $this->actingAs($f['requester'], 'sanctum');
        $journalId = $this->postJson('/api/v1/accounting/journals', $this->journalPayload($f))
            ->assertCreated()
            ->assertJsonPath('data.approval_progress.status', 'not_started')
            ->assertJsonPath('data.approval_progress.configured_stage', 'Finance Verification')
            ->json('data.id');

        $this->postJson("/api/v1/accounting/journals/{$journalId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.approval_progress.needs_approver_assignment', true);

        $this->actingAs($f['admin'], 'sanctum');
        $this->postJson("/api/v1/accounting/journals/{$journalId}/review")
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.approval_progress.needs_approver_assignment', true);

        // Cannot assign requester or user without accounting.journal.post.
        $this->postJson("/api/v1/accounting/journals/{$journalId}/assign-approver", [
            'user_id' => $f['requester']->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $this->postJson("/api/v1/accounting/journals/{$journalId}/assign-approver", [
            'user_id' => $f['ineligibleUser']->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        // Assigning eligible approver updates the current pending stage & logs audit event.
        $this->postJson("/api/v1/accounting/journals/{$journalId}/assign-approver", [
            'user_id' => $f['approver']->id,
            'notes' => 'Ditugaskan ke Winda karena approver awal belum berizin posting',
        ])->assertOk()
            ->assertJsonPath('data.approval_progress.needs_approver_assignment', false)
            ->assertJsonPath('data.approval_progress.steps.0.approvers.0.user_id', $f['approver']->id);

        $this->getJson("/api/v1/approval-center/history/journal/{$journalId}")
            ->assertOk()
            ->assertJsonFragment(['event' => 'approver_assigned']);

        // Assigned approver rejects the journal, requester fixes & resubmits, then approver posts it.
        $this->actingAs($f['approver'], 'sanctum')
            ->postJson("/api/v1/accounting/journals/{$journalId}/reject", ['notes' => 'Mohon lengkapi deskripsi biaya'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->actingAs($f['requester'], 'sanctum')
            ->putJson("/api/v1/accounting/journals/{$journalId}", $this->journalPayload($f))
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->postJson("/api/v1/accounting/journals/{$journalId}/submit")->assertOk();

        // Cross-year journal should appear in Approval Center and batch reject should reject cleanly.
        $this->actingAs($f['requester'], 'sanctum');
        $crossYearId = $this->postJson('/api/v1/accounting/journals', $this->journalPayload($f, '2027-02-10'))
            ->assertCreated()
            ->json('data.id');
        ApprovalMatrix::create([
            'module' => 'journal',
            'fiscal_year_id' => $f['fy2027']->id,
            'level' => 1,
            'min_amount' => 0,
            'user_id' => $f['approver']->id,
            'approver_title' => 'FY27 Approver',
            'is_active' => true,
        ]);
        $this->postJson("/api/v1/accounting/journals/{$crossYearId}/submit")->assertOk();
        $this->actingAs($f['admin'], 'sanctum')->postJson("/api/v1/accounting/journals/{$crossYearId}/review")->assertOk();

        $this->actingAs($f['approver'], 'sanctum');
        $pending = $this->getJson('/api/v1/approval-center/pending?module=journal&fiscal_year_id=' . $f['fy2026']->id)->assertOk();
        $this->assertTrue(collect($pending->json('data'))->pluck('id')->contains($crossYearId));

        $this->postJson('/api/v1/approval-center/batch-action', [
            'action' => 'reject',
            'notes' => 'Batch reject check',
            'items' => [['id' => $crossYearId, 'module' => 'journal']],
        ])->assertOk()->assertJsonPath('processed_count', 1);

        $this->assertSame('rejected', Journal::withoutGlobalScope('fiscal_year_context')->findOrFail($crossYearId)->status);

        // Finalize first journal with approver after admin review
        $this->actingAs($f['admin'], 'sanctum')->postJson("/api/v1/accounting/journals/{$journalId}/review")->assertOk();
        $this->actingAs($f['admin'], 'sanctum')->postJson("/api/v1/accounting/journals/{$journalId}/assign-approver", ['user_id' => $f['approver']->id])->assertOk();
        $this->actingAs($f['approver'], 'sanctum')->postJson("/api/v1/accounting/journals/{$journalId}/post")->assertOk()->assertJsonPath('data.status', 'posted');
    }
}
