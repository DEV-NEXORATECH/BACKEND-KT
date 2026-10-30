<?php

namespace Database\Seeders;

use App\Models\Master\AccountingPeriod;
use App\Models\Master\FiscalYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps demo data visibly separated by fiscal year so the year filter can be
 * verified without relying on production records.
 */
class FiscalYearDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $years = [];
        $existingActiveYear = FiscalYear::query()->where('is_active', true)->value('year');
        $activeYear = $existingActiveYear ?: 2026;
        foreach ([2025, 2026, 2027] as $year) {
            $years[$year] = FiscalYear::updateOrCreate(['year' => $year], [
                'name' => "Tahun Fiskal {$year}",
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'open',
                'is_active' => $year === $activeYear,
            ]);

            for ($period = 1; $period <= 12; $period++) {
                $start = sprintf('%d-%02d-01', $year, $period);
                $end = date('Y-m-t', strtotime($start));
                AccountingPeriod::updateOrCreate(
                    ['fiscal_year_id' => $years[$year]->id, 'period_number' => $period],
                    [
                        'name' => 'Periode '.date('F', strtotime($start))." {$year}",
                        'start_date' => $start,
                        'end_date' => $end,
                        'status' => 'open',
                        'is_active' => true,
                    ],
                );
            }
        }

        // Keep one deterministic record in each year for the most visible
        // transaction screens. Existing 2026 fixtures remain unchanged.
        $fy2026 = $years[2026]->id;
        $fy2025 = $years[2025]->id;
        $fy2027 = $years[2027]->id;
        $userId = DB::table('users')->orderBy('id')->value('id');

        // The master-data migration adds fiscal_year_id after the original
        // fixtures already exist. Keep those fixtures in 2026 and create
        // visible counterparts for the other fiscal years so the master-data
        // tabs never appear empty when the global year changes.
        $ensureScopedMasterRows = static function (string $table, array $rows, string $key = 'code'): void {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'fiscal_year_id')) {
                return;
            }

            foreach ($rows as $row) {
                $lookup = [$key => $row[$key]];
                $payload = collect($row)
                    ->filter(fn ($value, $column) => Schema::hasColumn($table, $column))
                    ->all();

                DB::table($table)->updateOrInsert(
                    $lookup,
                    array_merge($payload, [
                        'updated_at' => Schema::hasColumn($table, 'updated_at') ? now() : null,
                        'created_at' => Schema::hasColumn($table, 'created_at') ? now() : null,
                    ])
                );
            }
        };

        $ensureScopedMasterRows('programs', [
            [
                'code' => 'PROG-FOREST', 'fiscal_year_id' => $fy2026,
                'name' => 'Forest Governance & Law Enforcement',
                'objective' => 'Mendorong transparansi rantai pasok kayu dan perlindungan hutan adat di Indonesia.',
                'manager_name' => 'Hendri Wijaya', 'start_date' => '2026-01-01', 'end_date' => '2028-12-31',
                'is_active' => true,
            ],
            [
                'code' => 'PROG-FOREST-2025', 'fiscal_year_id' => $fy2025,
                'name' => 'Forest Governance & Law Enforcement (2025)',
                'objective' => 'Program tata kelola hutan dan penegakan hukum untuk tahun fiskal 2025.',
                'manager_name' => 'Hendri Wijaya', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
                'is_active' => true,
            ],
            [
                'code' => 'PROG-FOREST-2027', 'fiscal_year_id' => $fy2027,
                'name' => 'Forest Governance & Law Enforcement (2027)',
                'objective' => 'Program tata kelola hutan dan penegakan hukum untuk tahun fiskal 2027.',
                'manager_name' => 'Hendri Wijaya', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
                'is_active' => true,
            ],
        ]);

        $ensureScopedMasterRows('funding_sources', [
            [
                'code' => 'FUND-BILATERAL', 'fiscal_year_id' => $fy2026,
                'name' => 'Bilateral Government Grant', 'funding_type' => 'Bilateral',
                'restriction_type' => 'temporarily_restricted', 'is_active' => true,
            ],
            [
                'code' => 'FUND-FOUNDATION', 'fiscal_year_id' => $fy2026,
                'name' => 'International Philanthropic Foundation', 'funding_type' => 'Foundation',
                'restriction_type' => 'temporarily_restricted', 'is_active' => true,
            ],
            [
                'code' => 'FUND-BILATERAL-2025', 'fiscal_year_id' => $fy2025,
                'name' => 'Bilateral Government Grant (2025)', 'funding_type' => 'Bilateral',
                'restriction_type' => 'temporarily_restricted', 'is_active' => true,
            ],
            [
                'code' => 'FUND-BILATERAL-2027', 'fiscal_year_id' => $fy2027,
                'name' => 'Bilateral Government Grant (2027)', 'funding_type' => 'Bilateral',
                'restriction_type' => 'temporarily_restricted', 'is_active' => true,
            ],
        ]);

        $ensureScopedMasterRows('donor_types', [
            ['code' => 'GOV', 'fiscal_year_id' => $fy2026, 'name' => 'Government', 'description' => 'Government donor', 'is_active' => true],
            ['code' => 'NGO', 'fiscal_year_id' => $fy2026, 'name' => 'NGO', 'description' => 'Non-governmental organization donor', 'is_active' => true],
            ['code' => 'FOUNDATION', 'fiscal_year_id' => $fy2026, 'name' => 'Foundation', 'description' => 'Foundation donor', 'is_active' => true],
            ['code' => 'GOV-2025', 'fiscal_year_id' => $fy2025, 'name' => 'Government (2025)', 'description' => 'Government donor for fiscal year 2025', 'is_active' => true],
            ['code' => 'GOV-2027', 'fiscal_year_id' => $fy2027, 'name' => 'Government (2027)', 'description' => 'Government donor for fiscal year 2027', 'is_active' => true],
        ]);

        // Seeders that predate fiscal-year scoping do not always set the new
        // column. Treat their dated demo rows as 2026 before making the
        // 2025/2027 counterparts below.
        foreach ([
            'budget_lines', 'approval_matrices', 'exchange_rates',
            'grant_agreements', 'expense_requests', 'purchase_requests',
            'purchase_orders', 'goods_receipts', 'rfqs',
            'supplier_contract_notifications', 'supplier_invoices',
            'fixed_assets', 'journals', 'timesheet_entries',
            'tax_transactions', 'payments', 'bank_transactions',
            'customer_invoices', 'cash_advance_returns',
            'cash_advance_reimbursements', 'project_workplans',
            'project_logframes', 'activities', 'procurement_contracts',
            'procurement_waivers',
        ] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'fiscal_year_id')) {
                DB::table($table)->whereNull('fiscal_year_id')->update(['fiscal_year_id' => $fy2026]);
            }
        }

        // Every fiscal year must have the reusable approval chain. This is
        // intentionally repeated here as well as in MasterDataSeeder so an
        // operator can safely run only ProcessDataSeeder after deployment.
        if (Schema::hasTable('approval_matrices')) {
            $approvalTemplates = DB::table('approval_matrices')
                ->where('fiscal_year_id', $fy2026)
                ->whereNull('project_id')
                ->get();
            foreach ($approvalTemplates as $template) {
                $payload = (array) $template;
                unset($payload['id'], $payload['created_at'], $payload['updated_at']);
                foreach ([$fy2025, $fy2027] as $targetFiscalYear) {
                    $payload['fiscal_year_id'] = $targetFiscalYear;
                    DB::table('approval_matrices')->updateOrInsert([
                        'module' => $template->module,
                        'level' => $template->level,
                        'min_amount' => $template->min_amount,
                        'max_amount' => $template->max_amount,
                        'fiscal_year_id' => $targetFiscalYear,
                        'donor_id' => $template->donor_id,
                        'department_id' => $template->department_id,
                        'user_id' => $template->user_id,
                        'employee_id' => $template->employee_id,
                    ], array_merge($payload, ['created_at' => $now, 'updated_at' => $now]));
                }
            }
        }

        $clone = static function (string $table, array $sourceKeys, array $targetKeys, array $overrides = []) use ($now): ?int {
            if (! Schema::hasTable($table)) return null;
            $source = DB::table($table)->where($sourceKeys)->first();
            if (! $source) return null;
            $payload = (array) $source;
            unset($payload['id'], $payload['created_at'], $payload['updated_at'], $payload['deleted_at']);
            $payload = collect(array_merge($payload, $overrides))
                ->filter(fn ($value, $column) => Schema::hasColumn($table, $column))
                ->all();
            $keys = collect($targetKeys)->filter(fn ($value, $column) => Schema::hasColumn($table, $column))->all();
            if (! $keys) return null;
            DB::table($table)->updateOrInsert($keys, array_merge($payload, [
                'created_at' => Schema::hasColumn($table, 'created_at') ? $now : null,
                'updated_at' => Schema::hasColumn($table, 'updated_at') ? $now : null,
            ]));
            return (int) DB::table($table)->where($keys)->value('id');
        };

        // Donor & Grant planning data for 2025. Projects are linked through
        // the project_fiscal_year pivot because projects can span years.
        $grant2025 = $clone('grant_agreements', ['grant_no' => 'GRT-2026-FORD-01'], ['grant_no' => 'GRT-2025-FORD-01'], [
            'grant_no' => 'GRT-2025-FORD-01', 'fiscal_year_id' => $fy2025,
            'agreement_name' => 'Strengthening Indigenous Rights and Forest Governance 2025',
            'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
        ]);
        $program2025 = DB::table('programs')->where('code', 'PROG-FOREST-2025')->value('id');
        $project2025 = $clone('projects', ['code' => 'PRJ-2026-FORD-01'], ['code' => 'PRJ-2025-FORD-01'], [
            'code' => 'PRJ-2025-FORD-01', 'program_id' => $program2025,
            'grant_agreement_id' => $grant2025, 'name' => 'Community Forest Mapping & Legal Recognition 2025',
            'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
        ]);
        if ($project2025 && Schema::hasTable('project_fiscal_year')) {
            DB::table('project_fiscal_year')->insertOrIgnore(['project_id' => $project2025, 'fiscal_year_id' => $fy2025]);
        }
        $sourceProjectId = DB::table('projects')->where('code', 'PRJ-2026-FORD-01')->value('id');
        if ($project2025 && $sourceProjectId) {
            $clone('project_logframes', ['project_id' => $sourceProjectId, 'code' => 'OUTPUT-01'], ['project_id' => $project2025, 'code' => 'OUTPUT-2025-01'], [
                'project_id' => $project2025, 'code' => 'OUTPUT-2025-01', 'fiscal_year_id' => $fy2025,
                'description' => 'Community mapping outputs delivered in fiscal year 2025.',
                'period_start' => '2025-01-01', 'period_end' => '2025-12-31',
            ]);
            $clone('project_workplans', ['project_id' => $sourceProjectId, 'activity_code' => 'ACT-2026-01-01'], ['project_id' => $project2025, 'activity_code' => 'ACT-2025-01-01'], [
                'project_id' => $project2025, 'activity_code' => 'ACT-2025-01-01', 'fiscal_year_id' => $fy2025,
                'output_code' => 'OUTPUT-2025-01', 'start_date' => '2025-01-15', 'end_date' => '2025-02-15',
                'baseline_start_date' => '2025-01-15', 'baseline_end_date' => '2025-02-15',
                'activity' => 'Facilitasi Konsultasi Publik dengan Pemda Kabupaten (2025)',
            ]);
        }
        $project2027 = DB::table('projects')->where('code', 'PRJ-2027-FORD-01')->value('id');
        $program2027 = DB::table('programs')->where('code', 'PROG-FOREST-2027')->value('id');
        if ($project2027 && $program2027) {
            DB::table('projects')->where('id', $project2027)->update(['program_id' => $program2027]);
        }
        // Keep the 2026 demo queue at four visible expense records. The old
        // EXP-2026-002 fixture was replaced by EXP-2026-005.
        DB::table('expense_requests')->where('request_number', 'EXP-2026-002')->update(['deleted_at' => $now]);
        // Keep the 2026 approval-center fixture in fiscal year 2026. A prior
        // demo version moved EXP-2026-004 to 2027, which made it disappear
        // from Requests & Approvals when the global year was set to 2026.
        if ($sourceProjectId) {
            DB::table('expense_requests')->where('request_number', 'EXP-2026-004')->update([
                'fiscal_year_id' => $fy2026,
                'project_id' => $sourceProjectId,
                'request_date' => '2026-03-14',
            ]);
        }
        if (Schema::hasTable('budget_lines')) {
            $sourceBudgetLine = DB::table('budget_lines')->where('line_code', 'BL-FORD-1.1')->first()
                ?? DB::table('budget_lines')->orderBy('id')->first();
            if ($sourceBudgetLine) {
                $budgetPayload = (array) $sourceBudgetLine;
                unset($budgetPayload['id'], $budgetPayload['created_at'], $budgetPayload['updated_at'], $budgetPayload['deleted_at']);
                foreach ([[$project2025, 'BL-FOREST-2025-1'], [$project2027, 'BL-FOREST-2027-1']] as [$budgetProjectId, $budgetCode]) {
                    if (! $budgetProjectId) continue;
                    $budgetPayload['project_id'] = $budgetProjectId;
                    $budgetPayload['line_code'] = $budgetCode;
                    $budgetPayload['fiscal_year_id'] = str_contains($budgetCode, '2025') ? $fy2025 : $fy2027;
                    $budgetPayload['description'] = 'Field mapping and community engagement allocation '.substr($budgetCode, -4);
                    $budgetPayload['created_at'] = $now;
                    $budgetPayload['updated_at'] = $now;
                    DB::table('budget_lines')->updateOrInsert(['line_code' => $budgetCode], $budgetPayload);
                }
            }
        }

        // Core operational records for 2025. The source rows remain in 2026;
        // each target uses a year-specific business reference and date.
        $clone('expense_requests', ['request_number' => 'EXP-2026-001'], ['request_number' => 'EXP-2025-001'], [
            'request_number' => 'EXP-2025-001', 'fiscal_year_id' => $fy2025, 'request_date' => '2025-03-10',
            'project_id' => $project2025,
            'external_request_id' => 'SEED-EXP-2025-001', 'description' => 'Per diem and local transport for mapping workshop (2025).',
        ]);
        foreach ([
            ['source' => 'EXP-2026-004', 'target' => 'EXP-2025-002', 'date' => '2025-03-11', 'description' => 'Settlement advance awaiting approval (2025).'],
            ['source' => 'EXP-2026-005', 'target' => 'EXP-2025-003', 'date' => '2025-03-12', 'description' => 'Approved field coordination reimbursement (2025).'],
            ['source' => 'EXP-2026-003', 'target' => 'EXP-2025-004', 'date' => '2025-03-13', 'description' => 'Paid field activity cash advance (2025).'],
        ] as $expenseFixture) {
            $clone('expense_requests', ['request_number' => $expenseFixture['source']], ['request_number' => $expenseFixture['target']], [
                'request_number' => $expenseFixture['target'], 'fiscal_year_id' => $fy2025, 'project_id' => $project2025,
                'request_date' => $expenseFixture['date'], 'external_request_id' => 'SEED-'.$expenseFixture['target'],
                'description' => $expenseFixture['description'],
            ]);
        }
        $pr2025 = $clone('purchase_requests', ['pr_number' => 'PR-2026-001'], ['pr_number' => 'PR-2025-001'], [
            'pr_number' => 'PR-2025-001', 'fiscal_year_id' => $fy2025, 'request_date' => '2025-02-05',
            'project_id' => $project2025, 'justification' => 'Procure field mapping equipment and workshop supplies for 2025.',
        ]);
        $rfq2025 = $clone('rfqs', ['rfq_number' => 'RFQ-2026-001'], ['rfq_number' => 'RFQ-2025-001'], [
            'rfq_number' => 'RFQ-2025-001', 'fiscal_year_id' => $fy2025, 'purchase_request_id' => $pr2025,
            'rfq_date' => '2025-02-06', 'submission_deadline' => '2025-02-12',
        ]);
        $quotation2025 = $clone('vendor_quotations', ['quotation_number' => 'QTN-2026-001'], ['quotation_number' => 'QTN-2025-001'], [
            'quotation_number' => 'QTN-2025-001', 'rfq_id' => $rfq2025, 'quotation_date' => '2025-02-10',
        ]);
        $cba2025 = $clone('comparative_bid_analyses', ['cba_number' => 'CBA-2026-001'], ['cba_number' => 'CBA-2025-001'], [
            'cba_number' => 'CBA-2025-001', 'rfq_id' => $rfq2025, 'selected_quotation_id' => $quotation2025,
            'analysis_date' => '2025-02-12',
        ]);
        $po2025 = $clone('purchase_orders', ['po_number' => 'PO-2026-001'], ['po_number' => 'PO-2025-001'], [
            'po_number' => 'PO-2025-001', 'fiscal_year_id' => $fy2025, 'purchase_request_id' => $pr2025, 'rfq_id' => $rfq2025, 'cba_id' => $cba2025,
            'po_date' => '2025-02-08',
            'contract_number' => 'CTR-2025-001', 'contract_date' => '2025-02-08',
        ]);
        $clone('goods_receipts', ['grn_number' => 'GRN-2026-001'], ['grn_number' => 'GRN-2025-001'], [
            'grn_number' => 'GRN-2025-001', 'fiscal_year_id' => $fy2025, 'purchase_order_id' => $po2025, 'receipt_date' => '2025-02-20',
        ]);
        $clone('supplier_invoices', ['invoice_number' => 'INV-VND-2026-001'], ['invoice_number' => 'INV-VND-2025-001'], [
            'invoice_number' => 'INV-VND-2025-001', 'fiscal_year_id' => $fy2025, 'purchase_order_id' => $po2025, 'invoice_date' => '2025-02-21', 'due_date' => '2025-03-23',
        ]);
        $clone('supplier_contract_notifications', ['scn_number' => 'SCN-2026-001'], ['scn_number' => 'SCN-2025-001'], [
            'scn_number' => 'SCN-2025-001', 'fiscal_year_id' => $fy2025, 'purchase_request_id' => $pr2025, 'purchase_order_id' => $po2025, 'notification_date' => '2025-02-25',
            'subject' => 'Supplier contract notification for 2025.', 'receiving_location' => 'Head Office Bogor',
            'donor_name' => 'Ford Foundation', 'acceptable_for_payment' => true, 'receipt_by_name' => 'Riki Suryandi',
            'receipt_by_position' => 'Procurement & Adm Officer', 'requester_name' => 'Syaifani Auliana Havid',
            'requester_position' => 'Finance Manager', 'status' => 'acknowledged', 'issued_at' => $now,
        ]);
        $clone('journals', ['journal_number' => 'JV-2026-DEMO-01'], ['journal_number' => 'JV-2025-DEMO-01'], [
            'journal_number' => 'JV-2025-DEMO-01', 'fiscal_year_id' => $fy2025, 'journal_date' => '2025-03-10', 'reference' => 'EXP-2025-001',
        ]);
        $clone('tax_transactions', ['reference' => 'TAX-2026-001'], ['reference' => 'TAX-2025-001'], [
            'reference' => 'TAX-2025-001', 'fiscal_year_id' => $fy2025, 'transaction_date' => '2025-03-15',
            'e_bupot_reference' => 'BUPOT-2025-001', 'e_bupot_number' => 'BPU-2503-0001',
        ]);
        $clone('customer_invoices', ['invoice_number' => 'AR-2026-001'], ['invoice_number' => 'AR-2025-001'], [
            'invoice_number' => 'AR-2025-001', 'fiscal_year_id' => $fy2025, 'invoice_date' => '2025-03-20', 'due_date' => '2025-04-19',
        ]);
        $clone('fixed_assets', ['asset_code' => 'AST-2026-001'], ['asset_code' => 'AST-2025-001'], [
            'asset_code' => 'AST-2025-001', 'fiscal_year_id' => $fy2025, 'acquisition_date' => '2025-02-10', 'asset_name' => 'Laptop Field Mapping 2025',
        ]);
        $timesheetUser = DB::table('timesheet_entries')->where('description', 'Participatory mapping preparation')->value('user_id');
        if ($timesheetUser) {
            $clone('timesheet_entries', [
                'entry_date' => '2026-03-03', 'description' => 'Participatory mapping preparation', 'user_id' => $timesheetUser,
            ], [
                'entry_date' => '2025-03-03', 'description' => 'Participatory mapping preparation (2025)', 'user_id' => $timesheetUser,
            ], [
                'fiscal_year_id' => $fy2025, 'entry_date' => '2025-03-03',
                'description' => 'Participatory mapping preparation (2025)',
            ]);
        }
        $seedVendorId = DB::table('vendors')->orderBy('id')->value('id');
        $seedDonorId = DB::table('donors')->orderBy('id')->value('id');
        if ($project2025 && Schema::hasTable('procurement_contracts')) {
            DB::table('procurement_contracts')->updateOrInsert(['contract_number' => 'CTR-2025-DEMO-01'], [
                'fiscal_year_id' => $fy2025, 'contract_type' => 'goods', 'title' => 'Field Mapping Supplies 2025',
                'vendor_id' => $seedVendorId, 'project_id' => $project2025, 'donor_id' => $seedDonorId,
                'start_date' => '2025-02-01', 'end_date' => '2025-12-31', 'total_value' => 8200000,
                'currency' => 'IDR', 'status' => 'active', 'payment_terms' => 'Payment within 30 days.',
                'scope_of_work' => 'Supply and delivery of field mapping equipment.', 'created_by' => $userId ?? null,
                'updated_at' => now(), 'created_at' => now(),
            ]);
        }
        if ($project2025 && Schema::hasTable('procurement_waivers')) {
            DB::table('procurement_waivers')->updateOrInsert(['waiver_number' => 'WVR-2025-001'], [
                'fiscal_year_id' => $fy2025, 'project_id' => $project2025, 'vendor_id' => $seedVendorId,
                'total_amount' => 1250000, 'description' => 'Emergency field logistics waiver 2025',
                'justification' => 'Urgent delivery is required to meet the approved field schedule.', 'date' => '2025-03-18',
                'donor_name' => 'Ford Foundation', 'prepared_by' => 'Riki Suryandi', 'prepared_title' => 'Procurement & Adm Officer',
                'authorised_by' => 'Denny Bhatara', 'authorised_title' => 'Program & Operational Manager',
                'verified_by' => 'Syaifani Auliana Havid', 'verified_title' => 'Finance Manager', 'remarks' => 'Seeded approval data for PDF testing.',
                'status' => 'approved', 'approved_by' => $userId ?? null, 'approved_at' => now(), 'created_by' => $userId ?? null, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        DB::table('timesheet_entries')
            ->where('description', 'Financial monitoring and reporting')
            ->update(['fiscal_year_id' => $fy2027, 'entry_date' => '2027-03-05']);

        // Expense flow: keep the 2026 record and create a linked 2027 copy.
        $expense2027 = $clone('expense_requests', ['request_number' => 'EXP-2026-003'], ['request_number' => 'EXP-2027-001'], [
            'request_number' => 'EXP-2027-001', 'fiscal_year_id' => $fy2027, 'request_date' => '2027-03-12',
            'project_id' => $project2027,
            'external_request_id' => 'SEED-EXP-2027-001', 'description' => 'Field activity cash advance for East Kalimantan (2027).',
        ]);
        if ($expense2027) {
            $sourceLine = DB::table('expense_request_lines')->where('expense_request_id', DB::table('expense_requests')->where('request_number', 'EXP-2026-003')->value('id'))->first();
            if ($sourceLine) {
                $line = (array) $sourceLine;
                unset($line['id'], $line['created_at'], $line['updated_at']);
                $line['expense_request_id'] = $expense2027;
                DB::table('expense_request_lines')->updateOrInsert(
                    ['expense_request_id' => $expense2027, 'description' => $line['description']],
                    $line,
                );
            }
        }
        foreach ([
            ['source' => 'EXP-2026-004', 'target' => 'EXP-2027-002', 'date' => '2027-03-13', 'description' => 'Settlement advance awaiting approval (2027).'],
            ['source' => 'EXP-2026-005', 'target' => 'EXP-2027-003', 'date' => '2027-03-14', 'description' => 'Approved field coordination reimbursement (2027).'],
            ['source' => 'EXP-2026-001', 'target' => 'EXP-2027-004', 'date' => '2027-03-15', 'description' => 'Submitted mapping workshop reimbursement (2027).'],
        ] as $expenseFixture) {
            $clone('expense_requests', ['request_number' => $expenseFixture['source']], ['request_number' => $expenseFixture['target']], [
                'request_number' => $expenseFixture['target'], 'fiscal_year_id' => $fy2027, 'project_id' => $project2027,
                'request_date' => $expenseFixture['date'], 'external_request_id' => 'SEED-'.$expenseFixture['target'],
                'description' => $expenseFixture['description'],
            ]);
        }

        // Procurement flow: PR -> PO -> GRN -> invoice -> SCN, all separated.
        $pr2027 = $clone('purchase_requests', ['pr_number' => 'PR-2026-001'], ['pr_number' => 'PR-2027-001'], [
            'pr_number' => 'PR-2027-001', 'fiscal_year_id' => $fy2027, 'request_date' => '2027-02-05',
            'justification' => 'Procure field mapping equipment and workshop supplies for 2027.',
        ]);
        $rfq2027 = $clone('rfqs', ['rfq_number' => 'RFQ-2026-001'], ['rfq_number' => 'RFQ-2027-001'], [
            'rfq_number' => 'RFQ-2027-001', 'fiscal_year_id' => $fy2027, 'purchase_request_id' => $pr2027,
            'rfq_date' => '2027-02-06', 'submission_deadline' => '2027-02-12',
        ]);
        $quotation2027 = $clone('vendor_quotations', ['quotation_number' => 'QTN-2026-001'], ['quotation_number' => 'QTN-2027-001'], [
            'quotation_number' => 'QTN-2027-001', 'rfq_id' => $rfq2027, 'quotation_date' => '2027-02-10',
        ]);
        $cba2027 = $clone('comparative_bid_analyses', ['cba_number' => 'CBA-2026-001'], ['cba_number' => 'CBA-2027-001'], [
            'cba_number' => 'CBA-2027-001', 'rfq_id' => $rfq2027, 'selected_quotation_id' => $quotation2027,
            'analysis_date' => '2027-02-12',
        ]);
        $sourcePrId = DB::table('purchase_requests')->where('pr_number', 'PR-2026-001')->value('id');
        $sourcePrLine = $sourcePrId ? DB::table('purchase_request_lines')->where('purchase_request_id', $sourcePrId)->first() : null;
        $prLine2027 = null;
        if ($pr2027 && $sourcePrLine) {
            $line = (array) $sourcePrLine;
            unset($line['id'], $line['created_at'], $line['updated_at']);
            $line['purchase_request_id'] = $pr2027;
            $line['item_description'] = 'Field mapping and workshop supplies (2027)';
            DB::table('purchase_request_lines')->updateOrInsert(
                ['purchase_request_id' => $pr2027, 'item_description' => $line['item_description']],
                $line,
            );
            $prLine2027 = DB::table('purchase_request_lines')->where(['purchase_request_id' => $pr2027, 'item_description' => $line['item_description']])->value('id');
        }
        $po2027 = $clone('purchase_orders', ['po_number' => 'PO-2026-001'], ['po_number' => 'PO-2027-001'], [
            'po_number' => 'PO-2027-001', 'fiscal_year_id' => $fy2027, 'purchase_request_id' => $pr2027, 'rfq_id' => $rfq2027, 'cba_id' => $cba2027,
            'po_date' => '2027-02-08', 'contract_number' => 'CTR-2027-001', 'contract_date' => '2027-02-08',
        ]);
        $sourcePoId = DB::table('purchase_orders')->where('po_number', 'PO-2026-001')->value('id');
        if ($po2027 && $sourcePoId) {
            $sourcePoLine = DB::table('purchase_order_lines')->where('purchase_order_id', $sourcePoId)->first();
            if ($sourcePoLine) {
                $line = (array) $sourcePoLine;
                unset($line['id'], $line['created_at'], $line['updated_at']);
                $line['purchase_order_id'] = $po2027;
                $line['purchase_request_line_id'] = $prLine2027;
                DB::table('purchase_order_lines')->updateOrInsert(['purchase_order_id' => $po2027, 'line_order' => $line['line_order'] ?? 1], $line);
            }
        }
        $grn2027 = $clone('goods_receipts', ['grn_number' => 'GRN-2026-001'], ['grn_number' => 'GRN-2027-001'], [
            'grn_number' => 'GRN-2027-001', 'fiscal_year_id' => $fy2027, 'purchase_order_id' => $po2027, 'receipt_date' => '2027-02-20',
        ]);
        $invoice2027 = $clone('supplier_invoices', ['invoice_number' => 'INV-VND-2026-001'], ['invoice_number' => 'INV-VND-2027-001'], [
            'invoice_number' => 'INV-VND-2027-001', 'fiscal_year_id' => $fy2027, 'purchase_order_id' => $po2027,
            'goods_receipt_id' => $grn2027, 'invoice_date' => '2027-02-21', 'due_date' => '2027-03-23',
        ]);
        $clone('supplier_contract_notifications', ['scn_number' => 'SCN-2026-001'], ['scn_number' => 'SCN-2027-001'], [
            'scn_number' => 'SCN-2027-001', 'fiscal_year_id' => $fy2027, 'purchase_request_id' => $pr2027,
            'purchase_order_id' => $po2027, 'notification_date' => '2027-02-25', 'subject' => 'Supplier contract notification for 2027.',
            'receiving_location' => 'Head Office Bogor', 'donor_name' => 'Ford Foundation', 'acceptable_for_payment' => true,
            'receipt_by_name' => 'Riki Suryandi', 'receipt_by_position' => 'Procurement & Adm Officer',
            'requester_name' => 'Syaifani Auliana Havid', 'requester_position' => 'Finance Manager',
            'status' => 'acknowledged', 'issued_at' => $now,
        ]);

        // Accounting and tax rows also have a distinct 2027 counterpart.
        $journal2027 = $clone('journals', ['journal_number' => 'JV-2026-DEMO-01'], ['journal_number' => 'JV-2027-DEMO-01'], [
            'journal_number' => 'JV-2027-DEMO-01', 'fiscal_year_id' => $fy2027, 'journal_date' => '2027-03-10',
            'reference' => 'EXP-2027-001', 'description' => 'Field workshop travel and accommodation expense for 2027.',
        ]);
        if ($journal2027) {
            $sourceJournalId = DB::table('journals')->where('journal_number', 'JV-2026-DEMO-01')->value('id');
            foreach ($sourceJournalId ? DB::table('journal_lines')->where('journal_id', $sourceJournalId)->get() : [] as $sourceLine) {
                $line = (array) $sourceLine;
                unset($line['id'], $line['created_at'], $line['updated_at']);
                $line['journal_id'] = $journal2027;
                DB::table('journal_lines')->updateOrInsert(['journal_id' => $journal2027, 'line_order' => $line['line_order'] ?? 1], $line);
            }
        }
        $clone('tax_transactions', ['reference' => 'TAX-2026-001'], ['reference' => 'TAX-2027-001'], [
            'reference' => 'TAX-2027-001', 'fiscal_year_id' => $fy2027, 'transaction_date' => '2027-03-15',
            'e_bupot_reference' => 'BUPOT-2027-001', 'e_bupot_number' => 'BPU-2703-0001',
        ]);

        // Planning and budget views get a separate 2027 activity/output too.
        $projectId = DB::table('projects')->where('code', 'PRJ-2026-FORD-01')->value('id');
        if ($projectId) {
            $clone('project_logframes', ['project_id' => $projectId, 'code' => 'OUTPUT-01'], ['project_id' => $projectId, 'code' => 'OUTPUT-2027-01'], [
                'code' => 'OUTPUT-2027-01', 'fiscal_year_id' => $fy2027, 'description' => 'Community mapping outputs delivered in fiscal year 2027.',
                'period_start' => '2027-01-01', 'period_end' => '2027-12-31', 'target' => 15,
            ]);
            $clone('project_workplans', ['project_id' => $projectId, 'activity_code' => 'ACT-2026-01-01'], ['project_id' => $projectId, 'activity_code' => 'ACT-2027-01-01'], [
                'activity_code' => 'ACT-2027-01-01', 'fiscal_year_id' => $fy2027, 'output_code' => 'OUTPUT-2027-01',
                'activity' => 'Facilitasi Konsultasi Publik dengan Pemda Kabupaten (2027)',
                'start_date' => '2027-01-15', 'end_date' => '2027-02-15', 'baseline_start_date' => '2027-01-15',
                'baseline_end_date' => '2027-02-15', 'status' => 'planned', 'progress' => 0,
            ]);
        }

        // The remaining year-scoped feature pages also receive 2027 rows.
        $vendorId = DB::table('vendors')->orderBy('id')->value('id');
        $donorId = DB::table('donors')->orderBy('id')->value('id');
        $programId = DB::table('programs')->orderBy('id')->value('id');
        $purchaseRequest2027 = DB::table('purchase_requests')->where('pr_number', 'PR-2027-001')->value('id');
        if ($projectId && Schema::hasTable('procurement_contracts')) {
            DB::table('procurement_contracts')->updateOrInsert(['contract_number' => 'CTR-2027-DEMO-01'], [
                'fiscal_year_id' => $fy2027, 'contract_type' => 'goods', 'title' => 'Field Mapping Supplies 2027',
                'vendor_id' => $vendorId, 'project_id' => $projectId, 'donor_id' => $donorId,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-31', 'total_value' => 8200000,
                'currency' => 'IDR', 'status' => 'active', 'payment_terms' => 'Payment within 30 days.',
                'scope_of_work' => 'Supply and delivery of field mapping equipment.', 'created_by' => $userId ?? null,
                'updated_at' => now(), 'created_at' => now(),
            ]);
        }
        if ($projectId && Schema::hasTable('procurement_waivers')) {
            DB::table('procurement_waivers')->updateOrInsert(['waiver_number' => 'WVR-2027-001'], [
                'fiscal_year_id' => $fy2027, 'purchase_request_id' => $purchaseRequest2027, 'project_id' => $projectId,
                'vendor_id' => $vendorId, 'total_amount' => 1250000, 'description' => 'Emergency field logistics waiver 2027',
                'justification' => 'Urgent delivery is required to meet the approved field schedule.', 'date' => '2027-03-18',
                'donor_name' => 'Ford Foundation', 'prepared_by' => 'Riki Suryandi', 'prepared_title' => 'Procurement & Adm Officer',
                'authorised_by' => 'Denny Bhatara', 'authorised_title' => 'Program & Operational Manager',
                'verified_by' => 'Syaifani Auliana Havid', 'verified_title' => 'Finance Manager', 'remarks' => 'Seeded approval data for PDF testing.',
                'status' => 'approved', 'approved_by' => $userId ?? null, 'approved_at' => now(), 'created_by' => $userId ?? null, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        // Secondary screens also need one connected, year-specific fixture so
        // Budget Reallocation, Cash Advance Return, Reimbursement, PO
        // Amendment, and Vendor Evaluation never render an empty state when
        // the global fiscal-year selector changes.
        $secondaryYears = [
            2025 => ['fiscal_year_id' => $fy2025, 'project_id' => $project2025, 'expense_number' => 'EXP-2025-004', 'po_number' => 'PO-2025-001', 'budget_code' => 'BL-FOREST-2025-1'],
            2026 => ['fiscal_year_id' => $fy2026, 'project_id' => $sourceProjectId, 'expense_number' => 'EXP-2026-003', 'po_number' => 'PO-2026-001', 'budget_code' => 'BL-FORD-1.1'],
            2027 => ['fiscal_year_id' => $fy2027, 'project_id' => $projectId, 'expense_number' => 'EXP-2027-001', 'po_number' => 'PO-2027-001', 'budget_code' => 'BL-FOREST-2027-1'],
        ];
        $secondaryBankId = DB::table('bank_accounts')->orderBy('id')->value('id');
        $secondaryEmployeeId = DB::table('employees')->orderBy('id')->value('id');
        foreach ($secondaryYears as $year => $context) {
            $expenseId = DB::table('expense_requests')->where('request_number', $context['expense_number'])->value('id');
            $poId = DB::table('purchase_orders')->where('po_number', $context['po_number'])->value('id');
            $budgetId = DB::table('budget_lines')->where('line_code', $context['budget_code'])->value('id');
            if ($expenseId && Schema::hasTable('cash_advance_returns')) {
                $returnId = DB::table('cash_advance_returns')->updateOrInsert(['reference_no' => 'CAR-'.$year.'-001'], [
                    'expense_request_id' => $expenseId, 'employee_id' => $secondaryEmployeeId, 'bank_account_id' => $secondaryBankId,
                    'return_amount' => 750000, 'payment_method' => 'bank_transfer', 'return_date' => $year.'-04-10',
                    'status' => 'received', 'notes' => 'Unused cash advance returned after field activity.', 'created_by' => $userId ?? null,
                    'updated_at' => $now, 'created_at' => $now,
                ]);
                $returnId = DB::table('cash_advance_returns')->where('reference_no', 'CAR-'.$year.'-001')->value('id');
                if ($returnId && Schema::hasTable('bank_transactions') && $secondaryBankId) {
                    DB::table('bank_transactions')->updateOrInsert(['reference' => 'CAR-'.$year.'-001'], [
                        'bank_account_id' => $secondaryBankId, 'cash_advance_return_id' => $returnId,
                        'fiscal_year_id' => $context['fiscal_year_id'], 'transaction_date' => $year.'-04-10',
                        'description' => 'Cash advance return CAR-'.$year.'-001', 'debit' => 0, 'credit' => 750000,
                        'status' => 'reconciled', 'created_by' => $userId ?? null, 'updated_at' => $now, 'created_at' => $now,
                    ]);
                }
                if (Schema::hasTable('cash_advance_reimbursements')) {
                    DB::table('cash_advance_reimbursements')->updateOrInsert(['expense_request_id' => $expenseId, 'amount' => 325000], [
                        'employee_id' => $secondaryEmployeeId, 'project_id' => $context['project_id'], 'budget_line_id' => $budgetId,
                        'status' => 'pending', 'notes' => 'Additional eligible expense from settlement.', 'created_by' => $userId ?? null,
                        'updated_at' => $now, 'created_at' => $now,
                    ]);
                }
            }
            if ($poId && Schema::hasTable('po_amendments')) {
                DB::table('po_amendments')->updateOrInsert(['amendment_number' => 'POA-'.$year.'-001'], [
                    'purchase_order_id' => $poId, 'version' => 1,
                    'changes' => json_encode(['delivery_date' => [$year.'-03-20', $year.'-03-25']]),
                    'reason' => 'Delivery schedule adjusted by supplier.', 'status' => 'approved',
                    'approved_by' => $userId ?? null, 'approved_at' => $now, 'created_by' => $userId ?? null,
                    'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            $vendorId = DB::table('vendors')->orderBy('id')->value('id');
            $grnId = $poId ? DB::table('goods_receipts')->where('purchase_order_id', $poId)->value('id') : null;
            if ($vendorId && $poId && Schema::hasTable('vendor_evaluations')) {
                DB::table('vendor_evaluations')->updateOrInsert(['vendor_id' => $vendorId, 'purchase_order_id' => $poId], [
                    'goods_receipt_id' => $grnId, 'quality_score' => 4, 'delivery_score' => 4, 'price_score' => 5,
                    'service_score' => 4, 'overall_score' => 4.3, 'evaluator_notes' => 'Supplier delivered according to specification.',
                    'evaluated_by' => $userId ?? null, 'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            if ($context['project_id'] && $budgetId && Schema::hasTable('budget_reallocations')) {
                DB::table('budget_reallocations')->updateOrInsert(['reference' => 'REALLOC-'.$year.'-001'], [
                    'project_id' => $context['project_id'], 'from_budget_line_id' => $budgetId, 'to_budget_line_id' => $budgetId,
                    'amount' => 500000, 'reason' => 'Move unused workshop allocation to community facilitation.',
                    'status' => 'submitted', 'submitted_by' => $userId ?? null, 'submitted_at' => $now,
                    'created_by' => $userId ?? null, 'updated_at' => $now, 'created_at' => $now,
                ]);
            }
        }
        $clone('customer_invoices', ['invoice_number' => 'AR-2026-001'], ['invoice_number' => 'AR-2027-001'], [
            'invoice_number' => 'AR-2027-001', 'fiscal_year_id' => $fy2027, 'invoice_date' => '2027-03-20',
            'due_date' => '2027-04-19', 'description' => 'Reimbursement claim for 2027 community mapping programme.',
        ]);
        $clone('fixed_assets', ['asset_code' => 'AST-2026-001'], ['asset_code' => 'AST-2027-001'], [
            'asset_code' => 'AST-2027-001', 'fiscal_year_id' => $fy2027, 'asset_name' => 'Laptop Field Mapping 2027',
            'acquisition_date' => '2027-02-10', 'notes' => 'Seeded fixed asset for fiscal year 2027.',
        ]);

        // Keep one active year, but do not silently overwrite an administrator's
        // existing active-year choice when the demo seeder runs again.
        $activeFiscalYearId = $years[$activeYear]->id ?? $fy2026;
        DB::table('fiscal_years')->where('id', '!=', $activeFiscalYearId)->update(['is_active' => false]);
        DB::table('fiscal_years')->where('id', $activeFiscalYearId)->update(['is_active' => true]);
    }
}
