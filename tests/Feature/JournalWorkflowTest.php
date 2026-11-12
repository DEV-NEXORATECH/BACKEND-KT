<?php

namespace Tests\Feature;

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
