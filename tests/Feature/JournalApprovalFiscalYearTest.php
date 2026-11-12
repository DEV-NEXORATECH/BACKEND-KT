<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\ApprovalWorkflowRun;
use App\Models\Master\{ApprovalMatrix, ChartOfAccount, Employee, FiscalYear};
use App\Models\{Permission, Role, User};
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class JournalApprovalFiscalYearTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $year = FiscalYear::create(['year' => 2026, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $nextYear = FiscalYear::create(['year' => 2027, 'name' => 'FY 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_active' => false]);
        $role = Role::firstOrCreate(['slug' => 'finance-manager'], ['name' => 'Finance Manager']);
        foreach (['accounting.journal.view', 'accounting.journal.create', 'accounting.journal.update', 'accounting.journal.submit', 'accounting.journal.post'] as $slug) {
            $role->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }
        $requester = User::factory()->create(['role_id' => $role->id]);
        $employee = Employee::create(['employee_id_number' => 'TEST-APPROVER', 'name' => 'Syaifani Auliana Havid', 'email' => 'test-approver@example.test']);
        $approver = User::factory()->create(['role_id' => $role->id, 'employee_id' => $employee->id, 'name' => 'Syaifani Auliana Havid']);
        $next = User::factory()->create(['role_id' => $role->id]);
        $outsider = User::factory()->create(['role_id' => $role->id, 'name' => $approver->name]);
        $budgetRole = Role::firstOrCreate(['slug' => 'budget-holder'], ['name' => 'Budget Holder']);
        $bank = ChartOfAccount::create(['code' => '1110', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit']);
        $cash = ChartOfAccount::create(['code' => '1120', 'name' => 'Cash', 'account_type' => 'asset', 'normal_balance' => 'debit']);
        return compact('year', 'nextYear', 'role', 'requester', 'employee', 'approver', 'next', 'outsider', 'budgetRole', 'bank', 'cash');
    }

    private function payload(array $f, array $extra = []): array
    {
        return array_replace(['journal_date' => '2026-11-12', 'description' => 'Approval test', 'fiscal_year_id' => $f['year']->id, 'lines' => [
            ['account_id' => $f['bank']->id, 'line_description' => 'Bank', 'debit' => 100],
            ['account_id' => $f['cash']->id, 'line_description' => 'Cash', 'credit' => 100],
        ]], $extra);
    }

    private function workflow(array $f, bool $twoStages = true): array
    {
        $this->actingAs($f['requester'], 'sanctum');
        $id = $this->postJson('/api/v1/accounting/journals', $this->payload($f))->assertCreated()->json('data.id');
        $journal = Journal::withoutGlobalScope('fiscal_year_context')->findOrFail($id);
        $matrix = ApprovalMatrix::create(['module' => 'journal', 'level' => 1, 'min_amount' => 0, 'role_id' => $f['budgetRole']->id, 'user_id' => $f['approver']->id, 'employee_id' => $f['employee']->id, 'approver_title' => 'Project Manager / Budget Holder', 'is_active' => true, 'fiscal_year_id' => $f['year']->id]);
        if ($twoStages) ApprovalMatrix::create(['module' => 'journal', 'level' => 2, 'min_amount' => 0, 'user_id' => $f['next']->id, 'approver_title' => 'Final approver', 'is_active' => true, 'fiscal_year_id' => $f['year']->id]);
        $run = app(ApprovalWorkflowService::class)->start('journal', $journal, 100, $f['requester']->id);
        $journal->update(['status' => 'reviewed', 'submitted_by' => $f['requester']->id]);
        return [$journal, $run, $matrix];
    }

    public function test_assigned_identity_matches_history_and_intermediate_approval_does_not_post(): void
    {
        $f = $this->fixture(); [$journal, $run] = $this->workflow($f);
        $this->actingAs($f['approver'], 'sanctum');
        $this->getJson('/api/v1/accounting/journals/'.$journal->id)->assertOk()
            ->assertJsonPath('data.approval_progress.can_approve', true)->assertJsonPath('data.approval_progress.can_post', false)
            ->assertJsonPath('data.approval_progress.steps.0.approvers.0.user_id', $f['approver']->id);
        $this->getJson('/api/v1/approval-center/history/journal/'.$journal->id)->assertOk()
            ->assertJsonPath('data.can_approve', true)->assertJsonPath('data.steps.0.approver_name', $f['approver']->name);
        $this->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertOk()
            ->assertJsonPath('data.status', 'reviewed')->assertJsonPath('data.approval_progress.status', 'in_progress')->assertJsonPath('data.approval_progress.current_level', 2);
        $this->assertSame('in_progress', $run->fresh()->status);
        $this->actingAs($f['next'], 'sanctum')->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertOk()
            ->assertJsonPath('data.status', 'posted')->assertJsonPath('data.approval_progress.status', 'approved');
    }

    public function test_same_name_role_outsider_requester_and_noncurrent_approver_cannot_approve(): void
    {
        $f = $this->fixture(); [$journal, $run] = $this->workflow($f);
        foreach ([$f['outsider'], $f['requester'], $f['next']] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/accounting/journals/'.$journal->id)->assertOk()->assertJsonPath('data.approval_progress.can_approve', false);
            $this->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertUnprocessable();
        }
        $run->actions()->where('level', 1)->update(['user_id' => $f['requester']->id, 'employee_id' => null]);
        $this->actingAs($f['requester'], 'sanctum')->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertUnprocessable();
        $this->assertSame('pending', $run->actions()->first()->status);
    }

    public function test_action_snapshot_not_mutable_matrix_controls_display_and_authorization(): void
    {
        $f = $this->fixture(); [$journal, $run, $matrix] = $this->workflow($f, false);
        $matrix->update(['user_id' => $f['outsider']->id, 'employee_id' => null]);
        $this->actingAs($f['approver'], 'sanctum')->getJson('/api/v1/accounting/journals/'.$journal->id)->assertOk()
            ->assertJsonPath('data.approval_progress.steps.0.approvers.0.user_id', $f['approver']->id)->assertJsonPath('data.approval_progress.can_approve', true);
        $this->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertOk()->assertJsonPath('data.status', 'posted');
    }

    public function test_invalid_status_and_failed_final_save_leave_no_partial_workflow_mutation(): void
    {
        $f = $this->fixture(); [$journal, $run] = $this->workflow($f, false);
        $events = $run->events()->count();
        $journal->update(['status' => 'submitted']);
        $this->actingAs($f['approver'], 'sanctum')->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertUnprocessable();
        $this->assertSame('pending', $run->actions()->first()->status);
        $journal->update(['status' => 'reviewed']);
        Journal::updating(function (Journal $saving) { if ($saving->status === 'posted') throw new \RuntimeException('Simulated ledger save failure'); });
        try {
            $this->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertStatus(500);
        } finally { Event::forget('eloquent.updating: '.Journal::class); }
        $this->assertSame('in_progress', $run->fresh()->status);
        $this->assertSame('pending', $run->actions()->first()->status);
        $this->assertSame($events, $run->events()->count());
        $this->assertSame('reviewed', $journal->fresh()->status);
    }

    public function test_fiscal_year_is_stored_returned_and_edit_ignores_page_filter(): void
    {
        $f = $this->fixture(); $this->actingAs($f['requester'], 'sanctum');
        $payload = $this->payload($f); unset($payload['fiscal_year_id']);
        $id = $this->postJson('/api/v1/accounting/journals?fiscal_year_id='.$f['nextYear']->id, $payload)->assertCreated()
            ->assertJsonPath('data.fiscal_year_id', $f['year']->id)->assertJsonPath('data.fiscal_year.start_date', '2026-01-01')->json('data.id');
        $this->putJson('/api/v1/accounting/journals/'.$id.'?fiscal_year_id='.$f['nextYear']->id, $payload)->assertOk()->assertJsonPath('data.fiscal_year_id', $f['year']->id);
        $this->getJson('/api/v1/accounting/journals/'.$id.'?fiscal_year_id='.$f['nextYear']->id)->assertOk()->assertJsonPath('data.fiscal_year.year', 2026);
        $this->getJson('/api/v1/accounting/journals?fiscal_year_id='.$f['nextYear']->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/accounting/journals')->assertOk()->assertJsonCount(1, 'data');
        $this->putJson('/api/v1/accounting/journals/'.$id, $this->payload($f, ['journal_date' => '2027-02-01', 'fiscal_year_id' => $f['nextYear']->id]))->assertOk()->assertJsonPath('data.fiscal_year_id', $f['nextYear']->id);
    }

    public function test_invalid_closed_and_ambiguous_fiscal_years_are_rejected(): void
    {
        $f = $this->fixture(); $this->actingAs($f['requester'], 'sanctum');
        $this->postJson('/api/v1/accounting/journals', $this->payload($f, ['fiscal_year_id' => $f['nextYear']->id]))->assertUnprocessable()->assertJsonValidationErrors('journal_date');
        $f['year']->update(['status' => 'closed']);
        $this->postJson('/api/v1/accounting/journals', $this->payload($f))->assertUnprocessable();
        $f['year']->update(['status' => 'open']);
        $f['nextYear']->update(['start_date' => '2026-06-01']);
        $payload = $this->payload($f); unset($payload['fiscal_year_id']);
        $this->postJson('/api/v1/accounting/journals', $payload)->assertUnprocessable()->assertJsonValidationErrors('fiscal_year_id');
        $this->postJson('/api/v1/accounting/journals', $this->payload($f))->assertCreated();
    }

    public function test_posted_year_cannot_be_changed_directly(): void
    {
        $f = $this->fixture(); [$journal] = $this->workflow($f, false);
        $this->actingAs($f['approver'], 'sanctum')->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertOk();
        $this->putJson('/api/v1/accounting/journals/'.$journal->id, $this->payload($f, ['fiscal_year_id' => $f['nextYear']->id, 'journal_date' => '2027-02-01']))->assertUnprocessable();
        $this->assertEquals($f['year']->id, $journal->fresh()->fiscal_year_id);
    }
    public function test_employee_only_assignment_uses_canonical_identity_despite_page_year(): void
    {
        $f = $this->fixture(); [$journal, $run] = $this->workflow($f, false);
        $run->actions()->update(['user_id' => null]);
        $this->actingAs($f['approver'], 'sanctum')->getJson('/api/v1/accounting/journals/'.$journal->id.'?fiscal_year_id='.$f['nextYear']->id)
            ->assertOk()->assertJsonPath('data.approval_progress.can_approve', true)
            ->assertJsonPath('data.approval_progress.steps.0.approvers.0.employee_id', $f['employee']->id);
        $this->postJson('/api/v1/accounting/journals/'.$journal->id.'/post?fiscal_year_id='.$f['nextYear']->id)->assertOk()->assertJsonPath('data.status', 'posted');
    }

    public function test_scoped_matrix_and_permission_are_enforced_for_explicit_person(): void
    {
        $f = $this->fixture(); [$journal, $run, $matrix] = $this->workflow($f, false);
        $project = \App\Models\Master\Project::create(['code' => 'OTHER', 'name' => 'Other project']);
        $matrix->update(['project_id' => $project->id]);
        $this->actingAs($f['approver'], 'sanctum')->getJson('/api/v1/accounting/journals/'.$journal->id)->assertOk()->assertJsonPath('data.approval_progress.can_approve', false);
        $this->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertUnprocessable();
        $matrix->update(['project_id' => null]);
        $noPermission = Role::create(['name' => 'Read only', 'slug' => 'journal-read-only']);
        $noPermission->permissions()->attach(Permission::where('slug', 'accounting.journal.view')->firstOrFail());
        $f['approver']->update(['role_id' => $noPermission->id]);
        $f['approver']->unsetRelation('role');
        $this->getJson('/api/v1/accounting/journals/'.$journal->id)->assertOk()->assertJsonPath('data.approval_progress.can_approve', false);
        $this->postJson('/api/v1/accounting/journals/'.$journal->id.'/post')->assertForbidden();
        $this->assertSame('pending', $run->actions()->first()->status);
    }

    public function test_year_backfill_dry_run_does_not_guess_overlapping_period_and_is_idempotent(): void
    {
        $f = $this->fixture(); [$journal] = $this->workflow($f, false);
        \Illuminate\Support\Facades\DB::table('journals')->where('id', $journal->id)->update(['fiscal_year_id' => null]);
        $this->artisan('journals:backfill-fiscal-years', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($journal->fresh()->fiscal_year_id);
        $f['nextYear']->update(['start_date' => '2026-06-01']);
        $this->artisan('journals:backfill-fiscal-years', ['--apply' => true])->assertFailed();
        $this->assertNull($journal->fresh()->fiscal_year_id);
        $f['nextYear']->update(['start_date' => '2027-01-01']);
        $this->artisan('journals:backfill-fiscal-years', ['--apply' => true])->assertSuccessful();
        $this->artisan('journals:backfill-fiscal-years', ['--apply' => true])->assertSuccessful();
        $this->assertEquals($f['year']->id, $journal->fresh()->fiscal_year_id);
        $this->artisan('journals:audit-approval', ['number' => $journal->journal_number, '--user-id' => $f['approver']->id])->assertSuccessful();
        $this->assertSame('reviewed', $journal->fresh()->status);
        $keys = \Illuminate\Support\Facades\Schema::getForeignKeys('journals');
        $this->assertTrue(collect($keys)->contains(fn ($key) => $key['columns'] === ['fiscal_year_id'] && $key['foreign_table'] === 'fiscal_years'));
    }
    public function test_journal_date_and_fiscal_year_remain_separate_on_create_list_detail_and_edit(): void
    {
        $f = $this->fixture(); $this->actingAs($f['requester'], 'sanctum');
        $id = $this->postJson('/api/v1/accounting/journals', $this->payload($f))->assertCreated()
            ->assertJsonPath('data.journal_date', '2026-11-12')->assertJsonPath('data.fiscal_year_id', $f['year']->id)->json('data.id');
        $this->getJson('/api/v1/accounting/journals')->assertOk()
            ->assertJsonPath('data.0.journal_date', '2026-11-12')->assertJsonPath('data.0.fiscal_year_id', $f['year']->id);
        $this->getJson('/api/v1/accounting/journals/'.$id)->assertOk()
            ->assertJsonPath('data.journal_date', '2026-11-12')->assertJsonPath('data.fiscal_year.name', 'FY 2026');
        $dateOnly = $this->payload($f, ['journal_date' => '2026-11-20']); unset($dateOnly['fiscal_year_id']);
        $this->putJson('/api/v1/accounting/journals/'.$id, $dateOnly)->assertOk()
            ->assertJsonPath('data.journal_date', '2026-11-20')->assertJsonPath('data.fiscal_year_id', $f['year']->id);
        // An explicit year can be selected even where calendars overlap; it must
        // not rewrite the transaction date to the year's start date.
        $f['nextYear']->update(['start_date' => '2026-07-01']);
        $this->putJson('/api/v1/accounting/journals/'.$id, $this->payload($f, ['journal_date' => '2026-11-20', 'fiscal_year_id' => $f['nextYear']->id]))->assertOk()
            ->assertJsonPath('data.journal_date', '2026-11-20')->assertJsonPath('data.fiscal_year_id', $f['nextYear']->id);
        // Invalid combinations are rejected, without silently correcting either field.
        $this->putJson('/api/v1/accounting/journals/'.$id, $this->payload($f, ['journal_date' => '2026-03-01', 'fiscal_year_id' => $f['nextYear']->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('journal_date');
        $stored = Journal::withoutGlobalScope('fiscal_year_context')->findOrFail($id);
        $this->assertSame('2026-11-20', $stored->journal_date->toDateString());
        $this->assertEquals($f['nextYear']->id, $stored->fiscal_year_id);
    }

    public function test_explicitly_clearing_fiscal_year_is_rejected_instead_of_silently_restoring_it(): void
    {
        $f = $this->fixture(); $this->actingAs($f['requester'], 'sanctum');
        $this->postJson('/api/v1/accounting/journals', $this->payload($f, ['fiscal_year_id' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('fiscal_year_id');
        $id = $this->postJson('/api/v1/accounting/journals', $this->payload($f))->assertCreated()->json('data.id');
        $this->putJson('/api/v1/accounting/journals/'.$id, $this->payload($f, ['fiscal_year_id' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('fiscal_year_id');
        $stored = Journal::withoutGlobalScope('fiscal_year_context')->findOrFail($id);
        $this->assertSame('2026-11-12', $stored->journal_date->toDateString());
        $this->assertEquals($f['year']->id, $stored->fiscal_year_id);
    }
}