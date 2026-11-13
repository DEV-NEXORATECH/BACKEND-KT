<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_requires_balanced_lines(): void
    {
        [$user, $cash, $expense, $currency] = $this->journalFixture(['accounting.journal.create']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/accounting/journals', [
                'journal_date' => '2026-09-20',
                'journal_type' => 'manual',
                'description' => 'Unbalanced journal',
                'currency_id' => $currency->id,
                'lines' => [
                    ['account_id' => $expense->id, 'line_description' => 'Expense', 'debit' => 100, 'credit' => 0],
                    ['account_id' => $cash->id, 'line_description' => 'Cash', 'debit' => 0, 'credit' => 90],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Total debits must equal total credits.');
    }

    public function test_incomplete_expense_can_be_reviewed_but_cannot_be_posted(): void
    {
        [$user, $cash, $expense, $currency] = $this->journalFixture([
            'accounting.journal.create',
            'accounting.journal.submit',
            'accounting.journal.review',
            'accounting.journal.post',
            'accounting.journal.view',
        ]);

        $journalId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/accounting/journals', [
                'journal_date' => '2026-09-20',
                'journal_type' => 'manual',
                'description' => 'Balanced journal',
                'currency_id' => $currency->id,
                'lines' => [
                    ['account_id' => $expense->id, 'line_description' => 'Expense', 'debit' => 100, 'credit' => 0],
                    ['account_id' => $cash->id, 'line_description' => 'Cash', 'debit' => 0, 'credit' => 100],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/accounting/journals/{$journalId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->postJson("/api/v1/accounting/journals/{$journalId}/review")
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');

        $this->postJson("/api/v1/accounting/journals/{$journalId}/post")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.budget_line_id');

        $this->assertDatabaseHas('journals', [
            'id' => $journalId,
            'status' => 'reviewed',
            'posted_by' => null,
        ]);
    }

    public function test_journal_rejects_header_and_inactive_coa(): void
    {
        [$user, $cash, $expense, $currency] = $this->journalFixture(['accounting.journal.create']);
        $cash->update(['is_header' => true]);
        $expense->update(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/accounting/journals', [
                'journal_date' => '2026-09-20',
                'description' => 'Invalid COA journal',
                'currency_id' => $currency->id,
                'lines' => [
                    ['account_id' => $expense->id, 'line_description' => 'Expense', 'debit' => 100, 'credit' => 0],
                    ['account_id' => $cash->id, 'line_description' => 'Cash', 'debit' => 0, 'credit' => 100],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.account_id', 'lines.1.account_id']);
    }

    public function test_posted_journal_and_reversal_are_linked_to_bank_transactions(): void
    {
        [$user, $cash, $expense, $currency] = $this->journalFixture([
            'accounting.journal.view',
            'accounting.journal.reverse',
        ]);
        $bank = BankAccount::create([
            'bank_name' => 'Bank Test',
            'account_number' => 'TEST-001',
            'account_name' => 'Operating Account',
            'currency_id' => $currency->id,
            'gl_account_id' => $cash->id,
            'is_active' => true,
        ]);
        $fiscalYear = \App\Models\Master\FiscalYear::firstOrFail();
        $journal = Journal::withoutGlobalScope('fiscal_year_context')->create([
            'fiscal_year_id' => $fiscalYear->id,
            'journal_number' => 'JV-BANK-001',
            'journal_date' => '2026-09-20',
            'journal_type' => 'manual',
            'description' => 'Bank journal',
            'currency_id' => $currency->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $journal->lines()->createMany([
            ['account_id' => $expense->id, 'line_description' => 'Expense', 'debit' => 100, 'credit' => 0, 'line_order' => 1],
            ['account_id' => $cash->id, 'line_description' => 'Bank payment', 'debit' => 0, 'credit' => 50, 'line_order' => 2],
            ['account_id' => $cash->id, 'line_description' => 'Bank payment', 'debit' => 0, 'credit' => 50, 'line_order' => 3],
        ]);
        $journal->update(['status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);

        $this->assertDatabaseHas('bank_transactions', [
            'journal_id' => $journal->id,
            'bank_account_id' => $bank->id,
            'reference' => 'JV-BANK-001',
            'debit' => 0,
            'credit' => 50,
            'status' => 'unmatched',
        ]);
        $this->assertSame(2, $journal->bankTransactions()->count());
        $this->assertSame(2, $journal->bankTransactions()->distinct()->count('journal_line_id'));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/accounting/journals/'.$journal->id)
            ->assertOk()
            ->assertJsonPath('data.bank_transactions.0.bank_account_id', $bank->id)
            ->assertJsonPath('data.bank_transactions.0.bank_name', 'Bank Test');

        $reversalId = $this->postJson('/api/v1/accounting/journals/'.$journal->id.'/reverse')
            ->assertCreated()
            ->assertJsonPath('data.bank_transactions.0.bank_account_id', $bank->id)
            ->json('data.id');

        $this->assertDatabaseHas('bank_transactions', [
            'journal_id' => $reversalId,
            'bank_account_id' => $bank->id,
            'debit' => 50,
            'credit' => 0,
            'status' => 'unmatched',
        ]);
        $this->assertDatabaseCount('bank_transactions', 4);
    }

    private function journalFixture(array $permissions): array
    {
        \App\Models\Master\FiscalYear::create(['year' => 2026, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $role = Role::create(['name' => 'Journal Role', 'slug' => 'journal-role']);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::create([
                'name' => $permission,
                'slug' => $permission,
            ]));
        }

        $user = User::factory()->create(['role_id' => $role->id]);
        $currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'decimal_places' => 2,
            'is_base_currency' => true,
            'is_active' => true,
        ]);
        $cash = ChartOfAccount::create([
            'code' => '1000',
            'name' => 'Cash',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'level' => 1,
            'is_header' => false,
            'is_active' => true,
        ]);
        $expense = ChartOfAccount::create([
            'code' => '5000',
            'name' => 'Program Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'level' => 1,
            'is_header' => false,
            'is_active' => true,
        ]);

        return [$user, $cash, $expense, $currency];
    }
}
