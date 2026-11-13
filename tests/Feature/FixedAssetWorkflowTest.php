<?php

namespace Tests\Feature;

use App\Models\Master\AssetCategory;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Donor;
use App\Models\Master\Employee;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Program;
use App\Models\Master\Project;
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
        [$user, $category, $custodian, $project, $bank] = $this->fixture();

        $create = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assets/fixed-assets', [
                'asset_code' => 'FA-001',
                'asset_name' => 'Laptop Field Team',
                'asset_category_id' => $category->id,
                'acquisition_date' => '2026-09-20',
                'acquisition_cost' => 1200,
                'custodian_id' => $custodian->id,
                'project_id' => $project->id,
                'location' => 'Bogor Office',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.relation_no', 'REL-ASSET-001')
            ->assertJsonPath('data.grant_agreement.grant_no', 'GRANT-ASSET-001')
            ->assertJsonPath('data.net_book_value', '1200.00');

        $assetId = $create->json('data.id');

        $this->postJson("/api/v1/assets/fixed-assets/{$assetId}/capitalize")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('journals', ['reference' => 'FA-001', 'status' => 'posted']);
        $this->assertDatabaseHas('journal_lines', ['project_id' => $project->id, 'grant_agreement_id' => $project->grant_agreement_id, 'relation_no' => 'REL-ASSET-001']);

        $this->postJson("/api/v1/assets/fixed-assets/{$assetId}/depreciate", [
            'depreciation_date' => '2026-09-30',
        ])
            ->assertOk()
            ->assertJsonPath('data.accumulated_depreciation', '100.00')
            ->assertJsonPath('data.net_book_value', '1100.00');

        $this->assertDatabaseHas('asset_depreciations', ['fixed_asset_id' => $assetId, 'amount' => 100]);
        $this->assertDatabaseHas('journal_lines', ['project_id' => $project->id, 'relation_no' => 'REL-ASSET-001', 'line_description' => 'Depreciation expense']);

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
            'disposal_reason' => 'Sold after replacement',
            'disposal_type' => 'sale',
            'disposal_proceeds' => 600,
            'disposal_bank_account_id' => $bank->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disposed');

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $bank->id,
            'project_id' => $project->id,
            'debit' => 600,
            'status' => 'unmatched',
        ]);
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
        $bankAccountGl = ChartOfAccount::create(['code' => '1110', 'name' => 'Grant Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        ChartOfAccount::create(['code' => '2100', 'name' => 'Asset Clearing', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);

        $currency = Currency::create(['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'is_base_currency' => true, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Grant Bank', 'account_number' => 'ASSET-001', 'account_name' => 'Asset Grant', 'currency_id' => $currency->id, 'gl_account_id' => $bankAccountGl->id, 'is_active' => true]);
        $donor = Donor::create(['code' => 'DNR-ASSET', 'name' => 'Asset Donor', 'type' => 'institution', 'is_active' => true]);
        $grant = GrantAgreement::create(['grant_no' => 'GRANT-ASSET-001', 'relation_no' => 'REL-ASSET-001', 'donor_id' => $donor->id, 'agreement_name' => 'Asset Grant', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'currency_id' => $currency->id, 'grant_value' => 100000, 'bank_account_id' => $bank->id, 'status' => 'active', 'is_active' => true]);
        $program = Program::create(['code' => 'PRG-ASSET', 'name' => 'Asset Program', 'is_active' => true]);
        $project = Project::create(['code' => 'PRJ-ASSET', 'relation_no' => 'REL-ASSET-001', 'program_id' => $program->id, 'grant_agreement_id' => $grant->id, 'bank_account_id' => $bank->id, 'name' => 'Asset Project', 'is_active' => true]);

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

        return [$user, $category, $custodian, $project, $bank];
    }
}
