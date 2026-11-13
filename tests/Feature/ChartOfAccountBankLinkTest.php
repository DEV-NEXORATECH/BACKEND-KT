<?php

namespace Tests\Feature;

use App\Models\Finance\BankTransaction;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountBankLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_of_accounts_returns_linked_banks_and_live_bank_balance(): void
    {
        $role = Role::create(['name' => 'COA Viewer', 'slug' => 'coa-viewer']);
        $role->permissions()->attach(Permission::create(['name' => 'View master data', 'slug' => 'master-data.view']));
        $user = User::factory()->create(['role_id' => $role->id]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $coa = ChartOfAccount::create(['code' => '1101', 'name' => 'Bank Operating', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Bank Test', 'account_number' => 'COA-001', 'account_name' => 'Operating', 'currency_id' => $currency->id, 'gl_account_id' => $coa->id, 'opening_balance' => 1000, 'is_active' => true]);
        BankTransaction::create(['bank_account_id' => $bank->id, 'transaction_date' => '2026-09-10', 'reference' => 'IN', 'description' => 'Receipt', 'debit' => 500, 'credit' => 0, 'status' => 'matched']);
        BankTransaction::create(['bank_account_id' => $bank->id, 'transaction_date' => '2026-09-11', 'reference' => 'OUT', 'description' => 'Payment', 'debit' => 0, 'credit' => 100, 'status' => 'matched']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/master/chart-of-accounts?paginate=false')
            ->assertOk()
            ->assertJsonPath('data.0.id', $coa->id)
            ->assertJsonPath('data.0.is_bank_account', true)
            ->assertJsonPath('data.0.bank_account_count', 1)
            ->assertJsonPath('data.0.bank_accounts.0.id', $bank->id)
            ->assertJsonPath('data.0.bank_accounts.0.account_number', 'COA-001')
            ->assertJsonPath('data.0.bank_balance', 1400);
    }
}
