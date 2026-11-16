<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_returns_only_actionable_features_allowed_for_the_user(): void
    {
        $role = Role::create(['name' => 'Procurement Viewer', 'slug' => 'procurement-viewer']);
        $permission = Permission::create(['name' => 'View procurement PR', 'slug' => 'procurement.pr.view']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['role_id' => $role->id]);

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-AI-001',
            'request_date' => now()->subDays(5)->toDateString(),
            'requester_id' => $user->id,
            'justification' => 'Monitoring fixture',
            'status' => 'submitted',
            'created_by' => $user->id,
        ]);
        $pr->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/ai-monitoring')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.source', 'procurement')
            ->assertJsonPath('data.0.action_url', '/procurement/purchase-requests');
    }
}
