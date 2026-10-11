<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Master\Activity;
use App\Models\Master\BudgetLine;
use App\Models\Master\Donor;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DropdownContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_master_dropdown_resources_return_an_options_contract(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $user->update(['role_id' => $role->id]);

        $fiscalYearId = (int) (\App\Models\Master\FiscalYear::query()->orderBy('year')->value('id') ?? 0);
        $resources = [
            'organizations', 'office-locations', 'departments', 'cost-centers',
            'currencies', 'exchange-rates', 'fiscal-years', 'accounting-periods',
            'chart-of-accounts', 'account-categories', 'taxes', 'customers',
            'bank-accounts', 'petty-cashes', 'payment-methods', 'funding-sources',
            'donors', 'donor-types', 'grant-agreements', 'programs', 'projects',
            'activities', 'beneficiary-partners', 'reporting-dimensions',
            'reporting-categories', 'unit-of-measures', 'budget-categories',
            'budget-lines', 'employees', 'positions', 'vendors', 'vendor-categories',
            'procurement-categories', 'procurement-items', 'expense-categories',
            'document-types', 'asset-categories', 'approval-matrices',
        ];

        foreach ($resources as $resource) {
            $response = $this->actingAs($user, 'sanctum')->getJson(
                "/api/v1/master/{$resource}?options=1&fiscal_year_id={$fiscalYearId}"
            );

            $response->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['data']);
        }
    }

    public function test_dependent_options_stay_within_selected_donor_chain(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $user->update(['role_id' => $role->id]);
        $year = \App\Models\Master\FiscalYear::query()->where('year', 2026)->firstOrFail();
        $donor = Donor::query()->whereHas('grantAgreements.projects')->where('fiscal_year_id', $year->id)->firstOrFail();
        $grant = GrantAgreement::query()->where('donor_id', $donor->id)->where('fiscal_year_id', $year->id)->firstOrFail();
        $project = Project::query()->where('grant_agreement_id', $grant->id)->firstOrFail();
        $activity = Activity::query()->where('project_id', $project->id)->where('fiscal_year_id', $year->id)->firstOrFail();
        $budgetLine = BudgetLine::query()->where('project_id', $project->id)->where('grant_agreement_id', $grant->id)->where('fiscal_year_id', $year->id)->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/master/grant-agreements?options=1&fiscal_year_id={$year->id}&donor_id={$donor->id}")
            ->assertOk()->assertJsonPath('data.0.id', $grant->id);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/master/projects?options=1&fiscal_year_id={$year->id}&donor_id={$donor->id}&grant_agreement_id={$grant->id}")
            ->assertOk()->assertJsonPath('data.0.id', $project->id);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/master/activities?options=1&fiscal_year_id={$year->id}&project_id={$project->id}")
            ->assertOk()->assertJsonFragment(['id' => $activity->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/master/budget-lines?options=1&fiscal_year_id={$year->id}&donor_id={$donor->id}&grant_agreement_id={$grant->id}&project_id={$project->id}")
            ->assertOk()->assertJsonFragment(['id' => $budgetLine->id]);
    }
}
