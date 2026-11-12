<?php

namespace Tests\Feature;

use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Donor;
use App\Models\Master\FiscalYear;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Program;
use App\Models\Master\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BudgetLineImportAndRelationTest extends TestCase
{
    use RefreshDatabase;

    private function createFixture(): array
    {
        $role = Role::firstOrCreate(['slug' => 'finance-manager'], ['name' => 'Finance Manager']);
        foreach (['master-data.view', 'master-data.manage', 'budget.view', 'donor-management.view', 'reports.view'] as $slug) {
            $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }
        $user = User::factory()->create(['role_id' => $role->id]);

        $fy = FiscalYear::create([
            'year' => 2026,
            'name' => 'Fiscal Year 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $idr = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'is_base_currency' => true,
            'is_active' => true,
        ]);

        $program = Program::create([
            'code' => 'PROG-RUU',
            'name' => 'Advokasi Kebijakan',
            'is_active' => true,
        ]);

        $donor = Donor::create([
            'code' => 'DON-RUU',
            'name' => 'Donor RUU MA',
            'type' => 'institutional',
            'default_currency_id' => $idr->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        $relationNo = 'KT-REL-DON-RUU-GR-RUU-001-RUU-MA';
        $grant = GrantAgreement::create([
            'grant_no' => 'GR-RUU-001',
            'relation_no' => $relationNo,
            'donor_id' => $donor->id,
            'fiscal_year_id' => $fy->id,
            'agreement_name' => 'Grant RUU MA FY26',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'currency_id' => $idr->id,
            'grant_value' => 85050000,
            'status' => 'active',
            'is_active' => true,
        ]);

        $project = Project::create([
            'code' => 'RUU-MA',
            'name' => 'RUU MA',
            'program_id' => $program->id,
            'grant_agreement_id' => $grant->id,
            'relation_no' => $relationNo,
            'budget_currency_id' => $idr->id,
            'total_budget' => 85050000,
            'is_active' => true,
        ]);
        $project->fiscalYears()->sync([$fy->id]);

        $bcat100 = BudgetCategory::create(['code' => 'BCAT-100', 'name' => 'Personnel & Consultant Fees', 'is_active' => true]);
        $bcat200 = BudgetCategory::create(['code' => 'BCAT-200', 'name' => 'Direct Activity & Workshop Costs', 'is_active' => true]);
        $bcat300 = BudgetCategory::create(['code' => 'BCAT-300', 'name' => 'Travel & Field Accommodation', 'is_active' => true]);

        $coa5100 = ChartOfAccount::create(['code' => '5100', 'name' => 'Personnel Cost', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_header' => false, 'is_active' => true]);
        $coa5200 = ChartOfAccount::create(['code' => '5200', 'name' => 'Program Activity Cost', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_header' => false, 'is_active' => true]);
        $coa5300 = ChartOfAccount::create(['code' => '5300', 'name' => 'Overhead & Travel Cost', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_header' => false, 'is_active' => true]);

        return [$user, $fy, $idr, $donor, $grant, $project, $relationNo, compact('bcat100', 'bcat200', 'bcat300'), compact('coa5100', 'coa5200', 'coa5300')];
    }

    public function test_budget_line_resolves_project_and_grant_by_relation_no_and_template_downloads(): void
    {
        [$user, $fy, , , $grant, $project, $relationNo, $cats] = $this->createFixture();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/master/budget-lines', [
                'fiscal_year_id' => $fy->id,
                'relation_no' => $relationNo,
                'budget_category_id' => $cats['bcat100']->id,
                'line_code' => 'BL-AUTO-REL-01',
                'description' => 'Resolved from relation_no only',
                'unit_price' => 500000,
                'quantity' => 2,
                'total_amount' => 1000000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.grant_agreement_id', $grant->id)
            ->assertJsonPath('data.relation_no', $relationNo);

        $csvRes = $this->get('/api/v1/master/budget-lines/template?format=csv')->assertOk();
        $csvContent = $csvRes->streamedContent();
        $this->assertStringContainsString('Relation No', $csvContent);
        $this->assertStringContainsString('Coa Code', $csvContent);
        $this->assertStringContainsString('Sub Output Code', $csvContent);

        $this->get('/api/v1/master/budget-lines/template?format=xlsx')->assertOk();
    }

    public function test_standard_template_import_validates_relation_no_and_skips_mismatch(): void
    {
        [$user, $fy, , , $grant, $project, $relationNo] = $this->createFixture();

        $csv = implode("\n", [
            'Relation No,Project Code,Grant No,Fiscal Year,Budget Category,Sub Output Code,CoA Code,Line Code,Description,Proposal Period,Currency,Exchange Rate,Quantity,Unit Price,Total Amount,Active',
            "{$relationNo},RUU-MA,GR-RUU-001,2026,BCAT-200,2.1.1,5200,BL-TPL-01,Valid Relation Item,annual,IDR,1,1,2500000,2500000,1",
            'KT-REL-WRONG,RUU-MA,GR-RUU-001,2026,BCAT-200,2.1.1,5200,BL-TPL-BAD,Mismatched Relation Item,annual,IDR,1,1,1000000,1000000,1',
        ]);

        $file = UploadedFile::fake()->createWithContent('budgetline-template.csv', $csv);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/v1/master/budget-lines/import', [
                'file' => $file,
                'fiscal_year_id' => $fy->id,
            ])
            ->assertOk();

        $response->assertJsonPath('data.created', 1);
        $response->assertJsonPath('data.skipped', 1);
        $this->assertStringContainsString('Nomor Relasi', $response->json('data.errors.0.message'));

        $this->assertDatabaseHas('budget_lines', [
            'line_code' => 'BL-TPL-01',
            'project_id' => $project->id,
            'grant_agreement_id' => $grant->id,
            'relation_no' => $relationNo,
            'total_amount' => 2500000,
        ]);
        $this->assertDatabaseMissing('budget_lines', ['line_code' => 'BL-TPL-BAD']);
    }

    public function test_quickbooks_ruu_ma_budget_overview_xlsx_imports_all_21_items_with_relation_and_monitoring(): void
    {
        [$user, $fy, , , $grant, $project, $relationNo] = $this->createFixture();

        $rows = [
            ['Perkumpulan Kaoem Telapak', null],
            ['Budget Overview: RUU MA - Budget_FY26_P&L - FY26 P&L  Classes', null],
            ['August - December, 2026', null],
            [null, null],
            [null, 'Total'],
            ['18. RUU MA', null],
            ['   1. Personnel', null],
            ['      1.1 Salaries', null],
            ['         1.1.2 Senior Staff', '-16,100,000.00  '],
            ['         1.1.3 Finance Officer', '-7,200,000.00  '],
            ['      Total 1.1 Salaries', 'Rp -23,300,000.00  '],
            ['   Total 1. Personnel', 'Rp -23,300,000.00  '],
            ['   2. Activities', null],
            ['      2.1 Stakeholder mapping for advocacy efforts in the national and regional', null],
            ['         2.1.1 Advocacy dialogue and engagement', null],
            ['            2.1.1.1 Meeting Consumption', '-5,850,000.00  '],
            ['            2.1.1.2 Transportation for Local Participants', '-9,750,000.00  '],
            ['            2.1.1.3 Honor resource person', '-4,500,000.00  '],
            ['            2.1.1.4 Honor Notulensi', '-2,550,000.00  '],
            ['         Total 2.1.1 Advocacy dialogue and engagement', 'Rp -22,650,000.00  '],
            ['         2.1.2 Media Visit', null],
            ['            2.1.2.1 Transportation for Participation', '-3,750,000.00  '],
            ['            2.1.2.2 Meeting Consumption', '-2,250,000.00  '],
            ['            2.1.2.3 Stationaries (Banner, Copy Documents, etc)', '-700,000.00  '],
            ['         Total 2.1.2 Media Visit', 'Rp -6,700,000.00  '],
            ['         2.1.3 Reguler Coordination Meeting', null],
            ['            2.1.3.1 Meals', '-1,000,000.00  '],
            ['            2.1.3.2 Transportation for Participation', '-3,000,000.00  '],
            ['         Total 2.1.3 Reguler Coordination Meeting', 'Rp -4,000,000.00  '],
            ['         2.2.2 Dialog public in select province Sumatra', null],
            ['            2.2.2.1 Meeting meals - fullday', '-5,250,000.00  '],
            ['            2.2.2.2 Airfare', '-4,000,000.00  '],
            ['            2.2.2.3 Local Transportation for participants', '-3,000,000.00  '],
            ['            2.2.2.4 Local Transportation for staff KT', '-2,000,000.00  '],
            ['            2.2.2.5 Accomodation', '-700,000.00  '],
            ['            2.2.2.6 Transportation to Airport (in return)', '-1,000,000.00  '],
            ['            2.2.2.7 Stationaries (Banner, Copy Documents, etc)', '-1,000,000.00  '],
            ['         Total 2.2.2 Dialog public in select province Sumatra', 'Rp -16,950,000.00  '],
            ['         2.2.3 Publication materials', null],
            ['            2.2.3.1 Printing cost', '-2,550,000.00  '],
            ['            2.2.3.2 Design cost', '-2,900,000.00  '],
            ['         Total 2.2.3 Publication materials', 'Rp -5,450,000.00  '],
            ['      Total 2.1 Stakeholder mapping for advocacy efforts in the national and regional', 'Rp -55,750,000.00  '],
            ['   Total 2. Activities', 'Rp -55,750,000.00  '],
            ['   3. Overhead Cost', null],
            ['      3.1 Office Rent', '-6,000,000.00  '],
            ['   Total 3. Overhead Cost', 'Rp -6,000,000.00  '],
            ['Total 18. RUU MA', 'Rp -85,050,000.00  '],
            ['TOTAL', 'Rp -85,050,000.00  '],
            ['Wednesday, Nov 11, 2026 04:30:06 PM GMT+7 - Accrual Basis', null],
        ];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $tmpPath = tempnam(sys_get_temp_dir(), 'ruu-ma-').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        $uploadedFile = new UploadedFile(
            $tmpPath,
            'Budget_RUU_MA_2026.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/v1/master/budget-lines/import', [
                'file' => $uploadedFile,
                'project_id' => $project->id,
                'grant_agreement_id' => $grant->id,
                'relation_no' => $relationNo,
                'fiscal_year_id' => $fy->id,
            ])
            ->assertOk();

        $response->assertJsonPath('data.created', 21);
        $response->assertJsonPath('data.skipped', 0);
        $response->assertJsonPath('data.format', 'quickbooks_budget_overview');
        $this->assertCount(0, $response->json('data.errors'));

        $this->assertEquals(21, BudgetLine::query()->where('relation_no', $relationNo)->count());
        $this->assertEquals(85050000.0, (float) BudgetLine::query()->where('relation_no', $relationNo)->sum('total_amount'));

        $this->assertDatabaseHas('budget_lines', [
            'line_code' => '1.1.2',
            'description' => 'Senior Staff',
            'relation_no' => $relationNo,
            'project_id' => $project->id,
            'grant_agreement_id' => $grant->id,
            'total_amount' => 16100000,
        ]);
        $this->assertDatabaseHas('budget_lines', [
            'line_code' => '2.1.2.3',
            'description' => 'Stationaries (Banner, Copy Documents, etc)',
            'total_amount' => 700000,
        ]);
        $this->assertDatabaseHas('budget_lines', [
            'line_code' => '3.1',
            'description' => 'Office Rent',
            'total_amount' => 6000000,
        ]);

        // Re-importing the same file skips all 21 duplicates safely
        $secondUpload = new UploadedFile($tmpPath, 'Budget_RUU_MA_2026.xlsx', null, null, true);
        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/master/budget-lines/import', [
                'file' => $secondUpload,
                'project_id' => $project->id,
                'relation_no' => $relationNo,
                'fiscal_year_id' => $fy->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped', 21);

        @unlink($tmpPath);

        $this->getJson("/api/v1/master/budget-lines?relation_no={$relationNo}&per_page=100")
            ->assertOk()
            ->assertJsonPath('meta.total', 21);

        $this->getJson("/api/v1/budget/monitoring?relation_no={$relationNo}&fiscal_year_id={$fy->id}")
            ->assertOk()
            ->assertJsonPath('totals.approved_budget', 85050000);

        $this->getJson("/api/v1/donors/dashboard?relation_no={$relationNo}")
            ->assertOk()
            ->assertJsonPath('summary.total_budget', 85050000);

        $this->getJson("/api/v1/reports/grant-reporting?relation_no={$relationNo}")
            ->assertOk()
            ->assertJsonPath('summary.approved_budget', 85050000);
    }
}
