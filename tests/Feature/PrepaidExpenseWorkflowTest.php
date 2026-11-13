<?php

namespace Tests\Feature;

use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Donor;
use App\Models\Master\FiscalYear;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Program;
use App\Models\Master\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrepaidExpenseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepaid_recognition_and_amortization_keep_relation_and_bank_links(): void
    {
        $role = Role::create(['name' => 'Prepaid Role', 'slug' => 'prepaid-role']);
        foreach (['accounting.prepaid.view', 'accounting.prepaid.create', 'accounting.prepaid.update', 'accounting.prepaid.delete'] as $slug) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        FiscalYear::create(['year' => 2026, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open', 'is_active' => true]);

        $prepaidAccount = ChartOfAccount::create(['code' => '1300', 'name' => 'Prepaid Expense', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $expenseAccount = ChartOfAccount::create(['code' => '5300', 'name' => 'Rent Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bankGl = ChartOfAccount::create(['code' => '1100', 'name' => 'Grant Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'is_base_currency' => true, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Grant Bank', 'account_number' => 'PREPAID-001', 'account_name' => 'Grant Account', 'currency_id' => $currency->id, 'gl_account_id' => $bankGl->id, 'is_active' => true]);
        $donor = Donor::create(['code' => 'DNR-PRE', 'name' => 'Prepaid Donor', 'type' => 'institution', 'is_active' => true]);
        $grant = GrantAgreement::create(['grant_no' => 'GRT-PRE-001', 'relation_no' => 'REL-PRE-001', 'donor_id' => $donor->id, 'agreement_name' => 'Prepaid Grant', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'currency_id' => $currency->id, 'grant_value' => 12000, 'bank_account_id' => $bank->id, 'status' => 'active', 'is_active' => true]);
        $program = Program::create(['code' => 'PRG-PRE', 'name' => 'Prepaid Program', 'is_active' => true]);
        $project = Project::create(['code' => 'PRJ-PRE', 'relation_no' => 'REL-PRE-001', 'program_id' => $program->id, 'grant_agreement_id' => $grant->id, 'bank_account_id' => $bank->id, 'name' => 'Prepaid Project', 'is_active' => true]);

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/v1/accounting/prepaid-expenses', [
            'reference' => 'PRE-2026-001', 'description' => 'Annual office rent', 'start_date' => '2026-01-01',
            'periods' => 12, 'total_amount' => 1200, 'prepaid_account_id' => $prepaidAccount->id,
            'expense_account_id' => $expenseAccount->id, 'project_id' => $project->id, 'bank_account_id' => $bank->id,
        ])->assertCreated()->assertJsonPath('data.relation_no', 'REL-PRE-001')->assertJsonPath('data.grant_agreement.grant_no', 'GRT-PRE-001');
        $id = $create->json('data.id');

        $this->postJson("/api/v1/accounting/prepaid-expenses/{$id}/recognize", ['payment_date' => '2026-01-01'])
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('bank_transactions', ['bank_account_id' => $bank->id, 'project_id' => $project->id, 'credit' => 1200, 'status' => 'unmatched']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $prepaidAccount->id, 'grant_agreement_id' => $grant->id, 'relation_no' => 'REL-PRE-001', 'debit' => 1200]);

        $this->postJson("/api/v1/accounting/prepaid-expenses/{$id}/amortize", ['amortization_date' => '2026-01-31'])
            ->assertOk()->assertJsonPath('data.amortized_amount', '100.00');
        $this->assertDatabaseHas('prepaid_expense_amortizations', ['prepaid_expense_id' => $id, 'period' => 1, 'amount' => 100]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $expenseAccount->id, 'project_id' => $project->id, 'relation_no' => 'REL-PRE-001', 'debit' => 100]);

        $this->getJson("/api/v1/accounting/prepaid-expenses/{$id}/schedule")
            ->assertOk()->assertJsonPath('data.0.status', 'posted')->assertJsonPath('data.1.status', 'scheduled');
    }
}
