<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementWaiverTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiver_template_fields_are_persisted_and_returned(): void
    {
        $role = Role::create(['name' => 'Waiver User', 'slug' => 'waiver-user']);
        foreach (['procurement.pr.view', 'procurement.pr.create'] as $slug) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/procurement/waivers', [
            'date' => '2026-03-05',
            'donor_name' => 'Example Donor',
            'project_name' => 'Traceability Project',
            'vendor_name' => 'Local Supplier',
            'total_amount' => 3125000,
            'description' => 'Workshop lunch package',
            'justification' => 'The supplier is already known locally and can meet the required delivery schedule.',
            'vendor_rows' => [[
                'vendor_name' => 'Local Supplier',
                'vendor_information' => 'Bogor · 08123456789',
                'description' => 'Lunch package',
                'amount' => 3125000,
            ]],
            'prepared_by' => 'Programme Staff',
            'prepared_title' => 'Campaigner',
            'prepared_date' => '2026-03-05',
            'authorised_by' => 'Budget Holder',
            'authorised_title' => 'Senior Campaigner',
            'authorised_date' => '2026-03-06',
            'verified_by' => 'Procurement Officer',
            'verified_title' => 'Procurement Officer',
            'verified_date' => '2026-03-06',
            'remarks' => 'Supporting evidence reviewed.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.donor_name', 'Example Donor')
            ->assertJsonPath('data.vendor_rows.0.vendor_name', 'Local Supplier')
            ->assertJsonPath('data.prepared_title', 'Campaigner')
            ->assertJsonPath('data.verified_title', 'Procurement Officer');

        $this->assertDatabaseHas('procurement_waivers', [
            'donor_name' => 'Example Donor',
            'prepared_date' => '2026-03-05 00:00:00',
            'authorised_date' => '2026-03-06 00:00:00',
            'verified_date' => '2026-03-06 00:00:00',
        ]);

        $this->getJson('/api/v1/procurement/waivers')
            ->assertOk()
            ->assertJsonPath('data.0.vendor_rows.0.description', 'Lunch package')
            ->assertJsonPath('data.0.remarks', 'Supporting evidence reviewed.');
    }
}
