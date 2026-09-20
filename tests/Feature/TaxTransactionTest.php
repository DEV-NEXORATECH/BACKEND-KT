<?php

namespace Tests\Feature;

use App\Models\Master\Tax;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_calculation_transaction_and_report_use_master_tax_rate(): void
    {
        [$user, $tax] = $this->fixture();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tax/calculate', [
                'tax_id' => $tax->id,
                'amount' => 111000,
                'is_inclusive' => true,
                'direction' => 'sales',
            ])
            ->assertOk()
            ->assertJsonPath('data.taxable_amount', 100000)
            ->assertJsonPath('data.tax_amount', 11000)
            ->assertJsonPath('data.gross_amount', 111000);

        $this->postJson('/api/v1/tax/transactions', [
            'tax_id' => $tax->id,
            'transaction_date' => '2026-09-20',
            'direction' => 'sales',
            'amount' => 100000,
            'reference' => 'EF-001',
            'e_faktur_reference' => '010.001-26.000001',
            'status' => 'reported',
        ])
            ->assertCreated()
            ->assertJsonPath('data.tax_amount', '11000.00');

        $this->getJson('/api/v1/tax/report?start_date=2026-09-01&end_date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('totals.taxable_amount', 100000)
            ->assertJsonPath('totals.tax_amount', 11000)
            ->assertJsonPath('by_type.0.tax_type', 'PPN');
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'Tax Role', 'slug' => 'tax-role']);
        foreach (['tax.view', 'tax.manage'] as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $tax = Tax::create(['code' => 'PPN-11', 'name' => 'PPN 11%', 'tax_type' => 'PPN', 'rate_percent' => 11, 'is_active' => true]);

        return [$user, $tax];
    }
}
