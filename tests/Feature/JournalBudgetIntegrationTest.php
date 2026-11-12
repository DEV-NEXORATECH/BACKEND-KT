<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Master\{BudgetLine, BudgetCategory, ChartOfAccount, Currency, Donor, FiscalYear, GrantAgreement, Project};
use App\Models\{Role, Permission, User};
use App\Services\Budget\BudgetMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalBudgetIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $role = Role::create(['name' => 'Finance', 'slug' => 'finance']);
        foreach (['budget.view', 'accounting.journal.create', 'accounting.journal.update', 'accounting.journal.post', 'accounting.journal.import'] as $slug) {
            $role->permissions()->attach(Permission::create(['name' => $slug, 'slug' => $slug]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_active' => true]);
        $year = FiscalYear::create(['year' => 2026, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $otherYear = FiscalYear::create(['year' => 2027, 'name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_active' => false]);
        $donor = Donor::create(['code' => 'D1', 'name' => 'Donor', 'type' => 'institutional', 'is_active' => true]);
        $grant = GrantAgreement::create(['grant_no' => 'G1', 'relation_no' => 'REL-1', 'donor_id' => $donor->id, 'agreement_name' => 'Grant', 'start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'currency_id' => $currency->id, 'grant_value' => 1000, 'status' => 'active']);
        $project = Project::create(['code' => 'P1', 'name' => 'Project', 'grant_agreement_id' => $grant->id, 'relation_no' => 'REL-1']);
        $project->fiscalYears()->attach([$year->id, $otherYear->id]);
        $category = BudgetCategory::create(['code' => 'B1', 'name' => 'Budget']);
        $budget = BudgetLine::create(['line_code' => 'AI-REAL-1', 'description' => 'Real budget', 'project_id' => $project->id, 'grant_agreement_id' => $grant->id, 'relation_no' => 'REL-1', 'budget_category_id' => $category->id, 'unit_price' => 1000, 'quantity' => 1, 'total_amount' => 1000, 'fiscal_year_id' => $year->id]);
        $expense = ChartOfAccount::create(['code' => '5100', 'name' => 'Expense', 'account_type' => 'expense', 'normal_balance' => 'debit']);
        $bank = ChartOfAccount::create(['code' => '1100', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit']);
        $payable = ChartOfAccount::create(['code' => '2100', 'name' => 'Payable', 'account_type' => 'liability', 'normal_balance' => 'credit']);
        $this->actingAs($user, 'sanctum');
        return compact('user', 'year', 'otherYear', 'donor', 'grant', 'project', 'budget', 'expense', 'bank', 'payable');
    }

    private function journal(array $f, string $number, string $status, array $entries, ?int $year = null, ?string $relation = 'REL-1'): Journal
    {
        $journal = Journal::create(['journal_number' => $number, 'journal_date' => '2026-11-12', 'description' => $number, 'journal_type' => 'manual', 'status' => $status, 'fiscal_year_id' => $year ?? $f['year']->id]);
        foreach ($entries as $index => [$account, $debit, $credit]) {
            $journal->lines()->create(['account_id' => $account->id, 'budget_line_id' => $f['budget']->id, 'relation_no' => $relation, 'debit' => $debit, 'credit' => $credit, 'line_order' => $index + 1]);
        }
        return $journal;
    }

    public function test_posted_expense_only_and_accrual_payment_does_not_duplicate_actual(): void
    {
        $f = $this->fixture();
        foreach (['draft', 'submitted', 'reviewed'] as $status) $this->journal($f, $status, $status, [[$f['expense'], 999, 0]]);
        $this->journal($f, 'accrual', 'posted', [[$f['expense'], 300, 0], [$f['payable'], 0, 300]]);
        $this->journal($f, 'payment', 'posted', [[$f['payable'], 300, 0], [$f['bank'], 0, 300]]);
        $this->journal($f, 'cash-expense', 'posted', [[$f['expense'], 100, 0], [$f['bank'], 0, 100]]);
        $this->journal($f, 'correction', 'posted', [[$f['expense'], 0, 50], [$f['bank'], 50, 0]]);
        $this->journal($f, 'other-year', 'posted', [[$f['expense'], 900, 0]], $f['otherYear']->id);
        $this->journal($f, 'wrong-relation', 'posted', [[$f['expense'], 900, 0]], null, 'REL-OTHER');
        $this->getJson('/api/v1/budget/monitoring?relation_no=REL-1&fiscal_year_id='.$f['year']->id)
            ->assertOk()->assertJsonCount(1, 'by_line')->assertJsonPath('totals.actual', 350)->assertJsonPath('totals.available', 650);
    }

    public function test_budget_without_transactions_and_empty_access_scope(): void
    {
        $f = $this->fixture();
        $this->getJson('/api/v1/budget/monitoring')->assertOk()->assertJsonCount(1, 'by_line')->assertJsonPath('by_line.0.actual', 0);
        $this->assertCount(0, app(BudgetMonitoringService::class)->summary(['budget_line_ids' => []]));
        $this->getJson('/api/v1/budget/monitoring?fiscal_year_id='.$f['otherYear']->id)->assertOk()->assertJsonCount(0, 'by_line');
    }

    private function payload(array $f): array
    {
        return ['journal_date' => '2026-11-12', 'description' => 'Draft import completed', 'lines' => [
            ['account_id' => $f['expense']->id, 'budget_line_id' => $f['budget']->id, 'project_id' => $f['project']->id, 'line_description' => 'Expense', 'debit' => 100],
            ['account_id' => $f['bank']->id, 'line_description' => 'Bank', 'credit' => 100],
        ]];
    }

    public function test_invalid_dimensions_return_422_and_draft_can_be_completed_in_place(): void
    {
        $f = $this->fixture();
        $otherDonor = Donor::create(['code' => 'D2', 'name' => 'Other donor', 'type' => 'institutional']);
        $otherGrant = $f['grant']->replicate(); $otherGrant->grant_no = 'G2'; $otherGrant->relation_no = 'REL-2'; $otherGrant->save();
        foreach (['relation_no' => 'WRONG', 'donor_id' => $otherDonor->id, 'grant_agreement_id' => $otherGrant->id] as $field => $value) {
            $payload = $this->payload($f); $payload['lines'][0][$field] = $value;
            $this->postJson('/api/v1/accounting/journals', $payload)->assertUnprocessable();
        }
        $other = Project::create(['code' => 'P2', 'name' => 'Other', 'relation_no' => 'REL-OTHER', 'grant_agreement_id' => $f['grant']->id]);
        $payload = $this->payload($f); $payload['lines'][0]['project_id'] = $other->id;
        $this->postJson('/api/v1/accounting/journals', $payload)->assertUnprocessable();
        $draft = $this->journal($f, 'IMPORTED-001', 'draft', [[$f['expense'], 100, 0], [$f['bank'], 0, 100]], null, null);
        $draft->lines()->update(['budget_line_id' => null]);
        $this->putJson('/api/v1/accounting/journals/'.$draft->id, $this->payload($f))->assertOk()->assertJsonPath('data.id', $draft->id);
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseHas('journal_lines', ['journal_id' => $draft->id, 'account_id' => $f['expense']->id, 'relation_no' => 'REL-1', 'donor_id' => $f['donor']->id, 'deleted_at' => null]);
    }
    public function test_csv_import_stays_draft_and_reimport_does_not_duplicate(): void
    {
        $f = $this->fixture();
        $csv = "journal_number,journal_date,account_id,debit,credit\nCSV-001,2026-11-12,{$f['expense']->id},100,0\nCSV-001,2026-11-12,{$f['bank']->id},0,100\n";
        $upload = fn () => \Illuminate\Http\UploadedFile::fake()->createWithContent('journal.csv', $csv);
        $this->post('/api/v1/accounting/journals/import', ['file' => $upload()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('created', 1);
        $draft = Journal::firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->putJson('/api/v1/accounting/journals/'.$draft->id, $this->payload($f))->assertOk();
        $this->post('/api/v1/accounting/journals/import', ['file' => $upload()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('created', 0)->assertJsonPath('already_exists.0', 'CSV-001');
        $this->assertDatabaseCount('journals', 1);
    }

    public function test_csv_import_allows_same_journal_number_in_different_fiscal_years(): void
    {
        $f = $this->fixture();
        $csv = "journal_number,journal_date,fiscal_year_id,account_id,debit,credit\n"
            ."CSV-SAME,2026-11-12,{$f['year']->id},{$f['expense']->id},100,0\n"
            ."CSV-SAME,2026-11-12,{$f['year']->id},{$f['bank']->id},0,100\n"
            ."CSV-SAME,2027-01-12,{$f['otherYear']->id},{$f['expense']->id},200,0\n"
            ."CSV-SAME,2027-01-12,{$f['otherYear']->id},{$f['bank']->id},0,200\n";
        $upload = fn () => \Illuminate\Http\UploadedFile::fake()->createWithContent('journal.csv', $csv);

        $this->post('/api/v1/accounting/journals/import', ['file' => $upload()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('already_exists', []);

        $this->assertDatabaseHas('journals', ['journal_number' => 'CSV-SAME', 'fiscal_year_id' => $f['year']->id]);
        $this->assertDatabaseHas('journals', ['journal_number' => 'CSV-SAME', 'fiscal_year_id' => $f['otherYear']->id]);
    }

    public function test_backfill_dry_run_idempotency_and_conflict_reporting(): void
    {
        $f = $this->fixture();
        $journal = $this->journal($f, 'OLD-1', 'draft', [[$f['expense'], 100, 0]], null, null);
        $this->artisan('journals:backfill-dimensions', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($journal->lines()->first()->relation_no);
        $this->artisan('journals:backfill-dimensions', ['--apply' => true])->assertSuccessful();
        $this->assertSame('REL-1', $journal->lines()->first()->relation_no);
        $this->artisan('journals:backfill-dimensions', ['--apply' => true])->assertSuccessful();
        $journal->lines()->update(['relation_no' => 'CONFLICT']);
        $this->artisan('journals:backfill-dimensions', ['--apply' => true])->assertFailed();
        $this->assertSame('CONFLICT', $journal->lines()->first()->relation_no);
        $this->assertEquals(100, $journal->lines()->first()->debit);
    }

    public function test_all_years_and_legacy_relation_fallback(): void
    {
        $f = $this->fixture();
        $this->journal($f, 'LEGACY', 'posted', [[$f['expense'], 100, 0]], null, null);
        $this->journal($f, 'NEXT', 'posted', [[$f['expense'], 200, 0]], $f['otherYear']->id, null);
        $this->getJson('/api/v1/budget/monitoring?relation_no=REL-1')->assertOk()->assertJsonPath('totals.actual', 300)->assertJsonPath('scope.fiscal_year_id', null);
        $this->getJson('/api/v1/budget/monitoring?relation_no=REL-1&fiscal_year_id='.$f['year']->id)->assertOk()->assertJsonPath('totals.actual', 100);
    }
    public function test_valid_expense_posts_and_scope_does_not_expose_unassigned_budget(): void
    {
        $f = $this->fixture();
        $id = $this->postJson('/api/v1/accounting/journals', $this->payload($f))->assertCreated()->json('data.id');
        Journal::findOrFail($id)->update(['status' => 'reviewed']);
        $this->postJson('/api/v1/accounting/journals/'.$id.'/post')->assertOk()->assertJsonPath('data.status', 'posted');
        $this->getJson('/api/v1/budget/monitoring?relation_no=REL-1')->assertOk()->assertJsonPath('totals.actual', 100);
        $role = Role::create(['name' => 'Restricted', 'slug' => 'restricted']);
        $role->permissions()->attach(Permission::where('slug', 'budget.view')->firstOrFail());
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/budget/monitoring')->assertOk()->assertJsonCount(0, 'by_line')->assertJsonCount(0, 'activities')->assertJsonPath('totals.actual', 0);
    }
    public function test_conflicting_source_relations_are_rejected_and_other_budget_is_filtered(): void
    {
        $f = $this->fixture();
        $otherGrant = $f['grant']->replicate(); $otherGrant->grant_no = 'G-OTHER'; $otherGrant->relation_no = 'OTHER'; $otherGrant->save();
        $otherProject = Project::create(['code' => 'P-OTHER', 'name' => 'Other', 'grant_agreement_id' => $otherGrant->id, 'relation_no' => 'OTHER']);
        $otherBudget = $f['budget']->replicate(); $otherBudget->line_code = 'B-OTHER'; $otherBudget->project_id = $otherProject->id; $otherBudget->grant_agreement_id = $otherGrant->id; $otherBudget->relation_no = 'OTHER'; $otherBudget->save();
        foreach (['relation_no' => 'OTHER', 'grant_agreement_id' => $otherGrant->id] as $field => $value) {
            $original = $f['budget']->$field;
            $f['budget']->update([$field => $value]);
            $this->postJson('/api/v1/accounting/journals', $this->payload($f))->assertUnprocessable();
            $f['budget']->update([$field => $original]);
        }
        $f['project']->update(['relation_no' => 'OTHER']);
        $this->postJson('/api/v1/accounting/journals', $this->payload($f))->assertUnprocessable();
        $f['project']->update(['relation_no' => 'REL-1']);
        foreach (['relation_no=REL-1', 'grant_agreement_id='.$f['grant']->id, 'project_id='.$f['project']->id, 'budget_line_id='.$f['budget']->id] as $filter) {
            $this->getJson('/api/v1/budget/monitoring?'.$filter)->assertOk()->assertJsonCount(1, 'by_line')->assertJsonPath('by_line.0.budget_line_id', $f['budget']->id);
        }
        $f['budget']->delete();
        $this->getJson('/api/v1/budget/monitoring?relation_no=REL-1')->assertOk()->assertJsonCount(0, 'by_line');
    }
}
