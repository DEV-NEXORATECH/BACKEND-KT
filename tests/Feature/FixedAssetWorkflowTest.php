<?php

namespace Tests\Feature;

use App\Models\Master\AssetCategory;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_asset_lifecycle_posts_capitalization_and_depreciation(): void
    {
        [$user, $category, $custodian] = $this->fixture();

        $create = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assets/fixed-assets', [
                'asset_code' => 'FA-001',
                'asset_name' => 'Laptop Field Team',
                'asset_category_id' => $category->id,
                'acquisition_date' => '2026-09-20',
                'acquisition_cost' => 1200,
                'custodian_id' => $custodian->id,
                'location' => 'Bogor Office',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.net_book_value', '1200.00');

        $assetId = $create->json('data.id');

        $this->postJson("/api/v1/assets/fixed-assets/{$assetId}/capitalize")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('journals', ['reference' => 'FA-001', 'status' => 'posted']);

        $this->postJson("/api/v1/assets/fixed-assets/{$assetId}/depreciate", [
            'depreciation_date' => '2026-09-30',
        ])
            ->assertOk()
            ->assertJsonPath('data.accumulated_depreciation', '100.00')
            ->assertJsonPath('data.net_book_value', '1100.00');

        $this->assertDatabaseHas('asset_depreciations', ['fixed_asset_id' => $assetId, 'amount' => 100]);

        $this->postJson('/api/v1/assets/fixed-assets/bulk-depreciate', [
            'depreciation_date' => '2026-09-30',
        ])
            ->assertOk()
            ->assertJsonPath('processed_count', 0)
            ->assertJsonPath('skipped_count', 1);

        $this->assertDatabaseCount('asset_depreciations', 1);

        $this->postJson("/api/v1/assets/fixed-assets/{$assetId}/transfer", [
            'location' => 'Jakarta Field Office',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'transferred')
            ->assertJsonPath('data.location', 'Jakarta Field Office');

        $this->postJson("/api/v1/assets/fixed-assets/{$assetId}/dispose", [
            'disposed_date' => '2026-10-31',
            'disposal_reason' => 'Damaged beyond repair',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disposed');
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'Asset Role', 'slug' => 'asset-role']);
        foreach (['asset.view', 'asset.create', 'asset.capitalize', 'asset.depreciate', 'asset.transfer', 'asset.dispose'] as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);

        $assetAccount = ChartOfAccount::create(['code' => '1210', 'name' => 'Fixed Asset', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $accumulatedAccount = ChartOfAccount::create(['code' => '1290', 'name' => 'Accumulated Depreciation', 'account_type' => 'asset', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $depreciationAccount = ChartOfAccount::create(['code' => '5500', 'name' => 'Depreciation Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        ChartOfAccount::create(['code' => '2100', 'name' => 'Asset Clearing', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);

        $category = AssetCategory::create([
            'code' => 'IT',
            'name' => 'IT Equipment',
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'asset_gl_account_id' => $assetAccount->id,
            'depreciation_gl_account_id' => $depreciationAccount->id,
            'accumulated_gl_account_id' => $accumulatedAccount->id,
            'is_active' => true,
        ]);
        $custodian = Employee::create(['employee_id_number' => 'EMP-ASSET', 'name' => 'Asset Custodian', 'is_active' => true]);

        return [$user, $category, $custodian];
    }
}
