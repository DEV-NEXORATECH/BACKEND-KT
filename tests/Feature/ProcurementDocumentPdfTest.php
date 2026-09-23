<?php

namespace Tests\Feature;

use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Permission;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementDocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_request_can_be_downloaded_as_a_pdf_document(): void
    {
        $role = Role::create(['name' => 'Procurement Viewer', 'slug' => 'procurement-viewer']);
        $role->permissions()->attach(Permission::create(['name' => 'View PR', 'slug' => 'procurement.pr.view']));
        $user = User::factory()->create(['role_id' => $role->id]);
        $category = BudgetCategory::create(['code' => 'PROC', 'name' => 'Procurement', 'is_active' => true]);
        $budgetLine = BudgetLine::create([
            'budget_category_id' => $category->id,
            'line_code' => 'PROC-001',
            'description' => 'Procurement budget',
            'total_amount' => 1000,
            'is_active' => true,
        ]);
        $request = PurchaseRequest::create([
            'pr_number' => 'PR-PDF-001',
            'request_date' => '2026-09-23',
            'requester_id' => $user->id,
            'justification' => 'PDF document test',
            'status' => 'draft',
        ]);
        $request->lines()->create([
            'budget_line_id' => $budgetLine->id,
            'item_description' => 'Training materials',
            'quantity' => 2,
            'unit_price' => 100,
            'total_amount' => 200,
            'line_order' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->get("/api/v1/procurement/purchase-requests/{$request->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
