<?php

namespace Tests\Feature;

use App\Models\Master\BudgetCategory;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\FiscalYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegratedDonorGrantFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrated_donor_grant_propagates_to_all_donor_grant_menus_and_journal(): void
    {
        $role = Role::create(['name' => 'Finance', 'slug' => 'finance']);
        foreach ([
            'master-data.view',
            'master-data.manage',
            'master-data.export',
            'budget.view',
            'budget.manage',
            'reports.view',
            'accounting.journal.view',
            'accounting.journal.create',
            'accounting.journal.post',
        ] as $slug) {
            $role->permissions()->attach(Permission::create(['name' => $slug, 'slug' => $slug]));
        }

        $user = User::factory()->create(['role_id' => $role->id]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $fiscalYear = FiscalYear::create([
            'year' => 2026,
            'name' => 'Fiscal Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
        $category = BudgetCategory::create(['code' => 'BCAT-100', 'name' => 'Program Cost', 'is_active' => true]);
        $expenseCoa = ChartOfAccount::create([
            'code' => '5100',
            'name' => 'Program Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'level' => 1,
            'is_header' => false,
            'is_active' => true,
        ]);
        $bankCoa = ChartOfAccount::create([
            'code' => '1100',
            'name' => 'Cash / Bank',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'level' => 1,
            'is_header' => false,
            'is_active' => true,
        ]);
        ChartOfAccount::create([
            'code' => '4100',
            'name' => 'Grant Revenue',
            'account_type' => 'revenue',
            'normal_balance' => 'credit',
            'level' => 1,
            'is_header' => false,
            'is_active' => true,
        ]);

        $relationNo = 'KT-REL-FLOW-001';
        $payload = [
            'relation_no' => $relationNo,
            'fiscal_year_id' => $fiscalYear->id,
            'donor' => ['code' => 'DNR-FLW', 'name' => 'Flow Donor', 'type' => 'Foundation'],
            'grant' => [
                'grant_no' => 'GRT-FLW-01',
                'agreement_name' => 'Flow Grant Agreement',
                'currency_id' => $currency->id,
                'grant_value' => 10000000,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'status' => 'active',
            ],
            'project' => [
                'code' => 'PRJ-FLW-01',
                'name' => 'Flow Project',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'total_budget' => 10000000,
            ],
            'frameworks' => [
                ['level' => 'output', 'code' => 'OUT-1', 'description' => 'Output One', 'indicator' => '1 report', 'target' => '1'],
            ],
            'workplans' => [
                ['output_code' => 'OUT-1', 'activity' => 'Field Workshop', 'start_date' => '2026-02-01', 'end_date' => '2026-02-28', 'status' => 'planned'],
            ],
            'budgets' => [
                ['line_code' => 'BL-FLW-01', 'description' => 'Workshop Cost', 'budget_category_id' => $category->id, 'total_amount' => 5000000],
            ],
            'deadlines' => [
                ['report_type' => 'Financial Report', 'period_label' => 'Q1 2026', 'due_date' => '2026-04-30', 'status' => 'upcoming'],
            ],
        ];

        $this->actingAs($user, 'sanctum');
        $created = $this->postJson('/api/v1/master/donor-grant-integrated', $payload)->assertCreated()->json('data');
        $donorId = $created['id'];
        $grantId = $created['grant_agreements'][0]['id'];
        $projectId = $created['grant_agreements'][0]['projects'][0]['id'];
        $budgetLineId = $created['grant_agreements'][0]['projects'][0]['budget_lines'][0]['id'];

        // 1. Project dropdown appears with fiscal_year_id + relation_no
        $this->getJson("/api/v1/master/projects?options=true&fiscal_year_id={$fiscalYear->id}&relation_no={$relationNo}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $projectId);

        // 2. Project Frameworks & Workplan
        $this->getJson("/api/v1/master/project-logframes?project_id={$projectId}&relation_no={$relationNo}&fiscal_year_id={$fiscalYear->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/master/project-workplans?project_id={$projectId}&relation_no={$relationNo}&fiscal_year_id={$fiscalYear->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 3. Project Budget
        $this->getJson("/api/v1/master/budget-lines?project_id={$projectId}&relation_no={$relationNo}&fiscal_year_id={$fiscalYear->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $budgetLineId);

        // 4. Forecast Baseline
        $this->getJson("/api/v1/master/grant-disbursements/baseline?grant_agreement_id={$grantId}&relation_no={$relationNo}&project_id={$projectId}&fiscal_year_id={$fiscalYear->id}&quarter=Q1")
            ->assertOk()
            ->assertJsonPath('grant.relation_no', $relationNo)
            ->assertJsonPath('items.0.cost_category', '5100 - Program Expense')
            ->assertJsonPath('items.0.historical_expense', 0)
            ->assertJsonCount(1, 'items');

        // Forecast -> request -> funds received remains connected to fiscal
        // year, grant, project, COA, journal, and bank transaction.
        $bank = BankAccount::create([
            'fiscal_year_id' => $fiscalYear->id,
            'bank_name' => 'Flow Bank',
            'account_number' => 'FLOW-001',
            'account_name' => 'Flow Grant Account',
            'currency_id' => $currency->id,
            'gl_account_id' => $bankCoa->id,
            'is_active' => true,
        ]);
        \App\Models\Master\GrantAgreement::withoutGlobalScope('fiscal_year_context')->whereKey($grantId)->update(['bank_account_id' => $bank->id]);
        $requestId = $this->postJson('/api/v1/master/grant-disbursements', [
            'grant_agreement_id' => $grantId,
            'project_id' => $projectId,
            'relation_no' => $relationNo,
            'period_label' => 'Q1 2026',
            'request_date' => '2026-02-01',
            'requested_amount' => 1500000,
            'bank_account_id' => $bank->id,
        ])->assertCreated()
            ->assertJsonPath('data.fiscal_year_id', $fiscalYear->id)
            ->json('data.id');
        $this->postJson("/api/v1/master/grant-disbursements/{$requestId}/receive", [
            'received_date' => '2026-02-10',
            'received_amount' => 1500000,
            'actual_fx_rate' => 1,
            'received_bank_account_id' => $bank->id,
        ])->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('bank_transaction.reference', 'DR-GRTFLW01-Q12026-1');
        $this->assertDatabaseHas('journals', ['source_type' => 'grant_disbursement_receipt', 'source_id' => $requestId, 'reference' => 'DR-GRTFLW01-Q12026-1']);
        $this->assertDatabaseHas('bank_transactions', ['bank_account_id' => $bank->id, 'reference' => 'DR-GRTFLW01-Q12026-1']);

        // 5. Updating integrated record preserves budgetLineId
        $this->putJson("/api/v1/master/donor-grant-integrated/{$donorId}", $payload)->assertOk();
        $this->assertDatabaseHas('budget_lines', ['id' => $budgetLineId, 'line_code' => 'BL-FLW-01', 'deleted_at' => null]);

        // 6. Posting Journal feeds Budget Monitoring & Donor Dashboard & Donor Reporting
        $journalId = $this->postJson('/api/v1/accounting/journals', [
            'journal_date' => '2026-02-15',
            'fiscal_year_id' => $fiscalYear->id,
            'description' => 'Workshop expense',
            'lines' => [
                ['account_id' => $expenseCoa->id, 'project_id' => $projectId, 'budget_line_id' => $budgetLineId, 'debit' => 1000000, 'credit' => 0, 'line_description' => 'Workshop'],
                ['account_id' => $bankCoa->id, 'debit' => 0, 'credit' => 1000000, 'line_description' => 'Bank'],
            ],
        ])->assertCreated()->json('data.id');
        \App\Models\Accounting\Journal::findOrFail($journalId)->update(['status' => 'reviewed']);
        $this->postJson("/api/v1/accounting/journals/{$journalId}/post")->assertOk();

        $this->getJson("/api/v1/master/grant-disbursements/baseline?grant_agreement_id={$grantId}&relation_no={$relationNo}&project_id={$projectId}&fiscal_year_id={$fiscalYear->id}&quarter=Q1")
            ->assertOk()
            ->assertJsonPath('items.0.historical_expense', 1000000);

        $this->getJson("/api/v1/budget/monitoring?relation_no={$relationNo}&project_id={$projectId}&fiscal_year_id={$fiscalYear->id}")
            ->assertOk()
            ->assertJsonPath('totals.approved_budget', 5000000)
            ->assertJsonPath('totals.actual', 1000000)
            ->assertJsonPath('totals.available', 4000000);

        $this->getJson("/api/v1/donors/dashboard?relation_no={$relationNo}&project_id={$projectId}&grant_agreement_id={$grantId}&fiscal_year_id={$fiscalYear->id}")
            ->assertOk()
            ->assertJsonPath('summary.total_grants', 1)
            ->assertJsonPath('summary.total_actual', 1000000);

        $this->getJson("/api/v1/master/grant-reporting-deadlines?relation_no={$relationNo}&project_id={$projectId}&grant_agreement_id={$grantId}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 7. Static export route must not be shadowed by donor-grant-integrated/{donor}
        $this->getJson('/api/v1/master/donor-grant-integrated/export?format=json')
            ->assertOk()
            ->assertJsonPath('template_version', 'donor-grant-integrated-v1');

        // 8. Cross-fiscal-year X-Fiscal-Year-Id must preserve eager relations and avoid 404 on show/update/delete
        $otherYear = FiscalYear::create([
            'year' => 2027,
            'name' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_active' => false,
            'is_closed' => false,
        ]);
        \App\Models\Master\Donor::query()->withoutGlobalScope('fiscal_year_context')->whereKey($donorId)->update(['fiscal_year_id' => $fiscalYear->id]);
        \App\Models\Master\GrantAgreement::query()->withoutGlobalScope('fiscal_year_context')->whereKey($grantId)->update(['fiscal_year_id' => $fiscalYear->id]);

        $this->getJson("/api/v1/master/grant-disbursements?fiscal_year_id={$fiscalYear->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/master/grant-disbursements?fiscal_year_id={$otherYear->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withHeader('X-Fiscal-Year-Id', (string) $otherYear->id)
            ->getJson('/api/v1/master/donors')
            ->assertOk()
            ->assertJsonPath('data.0.grant_agreements.0.relation_no', $relationNo)
            ->assertJsonPath('data.0.grant_agreements.0.projects.0.id', $projectId);

        $this->withHeader('X-Fiscal-Year-Id', (string) $otherYear->id)
            ->getJson("/api/v1/master/donors/{$donorId}")
            ->assertOk()
            ->assertJsonPath('data.grant_agreements.0.relation_no', $relationNo);

        $this->withHeader('X-Fiscal-Year-Id', (string) $otherYear->id)
            ->getJson("/api/v1/master/donor-grant-integrated/{$donorId}")
            ->assertOk()
            ->assertJsonPath('data.grant_agreements.0.projects.0.id', $projectId);

        $this->withHeader('X-Fiscal-Year-Id', (string) $otherYear->id)
            ->getJson("/api/v1/donors/dashboard?relation_no={$relationNo}&project_id={$projectId}&grant_agreement_id={$grantId}")
            ->assertOk()
            ->assertJsonPath('summary.total_grants', 1);

        $this->withHeader('X-Fiscal-Year-Id', (string) $otherYear->id)
            ->getJson("/api/v1/master/grant-disbursements/baseline?grant_agreement_id={$grantId}&relation_no={$relationNo}&project_id={$projectId}&quarter=Q1")
            ->assertOk()
            ->assertJsonPath('items.0.relation_no', $relationNo);

        $this->withHeader('X-Fiscal-Year-Id', (string) $fiscalYear->id)
            ->getJson("/api/v1/budget/monitoring?relation_no={$relationNo}&project_id={$projectId}&fiscal_year_id={$fiscalYear->id}")
            ->assertOk()
            ->assertJsonPath('logframes.0.relation_no', $relationNo);

        $this->withHeader('X-Fiscal-Year-Id', (string) $otherYear->id)
            ->deleteJson("/api/v1/master/donor-grant-integrated/{$donorId}")
            ->assertOk();
    }
}
