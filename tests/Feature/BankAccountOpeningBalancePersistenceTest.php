<?php

namespace Tests\Feature;

use App\Models\Master\BankAccount;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountOpeningBalancePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_without_opening_fields_keeps_balance_and_date(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $user->update(['role_id' => $role->id]);

        $bankAccount = BankAccount::query()->withoutGlobalScopes()->firstOrFail();
        $bankAccount->update([
            'opening_balance' => 931861620,
            'opening_balance_date' => '2026-09-15',
        ]);

        $payload = [
            'fiscal_year_id' => $bankAccount->fiscal_year_id,
            'organization_id' => $bankAccount->organization_id,
            'bank_name' => $bankAccount->bank_name,
            'account_number' => $bankAccount->account_number,
            'account_name' => $bankAccount->account_name,
            'swift_code' => $bankAccount->swift_code,
            'description' => $bankAccount->description,
            'currency_id' => $bankAccount->currency_id,
            'gl_account_id' => $bankAccount->gl_account_id,
            'is_active' => $bankAccount->is_active,
        ];

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/master/bank-accounts/{$bankAccount->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.opening_balance_date', '2026-09-15');

        $this->assertSame('931861620.00', $bankAccount->fresh()->opening_balance);
        $this->assertSame('2026-09-15', $bankAccount->fresh()->opening_balance_date->format('Y-m-d'));
    }
}
