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
        foreach ([2025, 2026, 2027] as $year) {
            $years[$year] = FiscalYear::updateOrCreate(['year' => $year], [
                'name' => "Tahun Fiskal {$year}",
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'open',
                'is_active' => $year === 2026,
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
            'external_request_id' => 'SEED-EXP-2025-001', 'description' => 'Per diem and local transport for mapping workshop (2025).',
        ]);
        $clone('purchase_requests', ['pr_number' => 'PR-2026-001'], ['pr_number' => 'PR-2025-001'], [
            'pr_number' => 'PR-2025-001', 'fiscal_year_id' => $fy2025, 'request_date' => '2025-02-05',
            'project_id' => $project2025, 'justification' => 'Procure field mapping equipment and workshop supplies for 2025.',
        ]);
        $clone('purchase_orders', ['po_number' => 'PO-2026-001'], ['po_number' => 'PO-2025-001'], [
            'po_number' => 'PO-2025-001', 'fiscal_year_id' => $fy2025, 'po_date' => '2025-02-08',
            'contract_number' => 'CTR-2025-001', 'contract_date' => '2025-02-08',
        ]);
        $clone('goods_receipts', ['grn_number' => 'GRN-2026-001'], ['grn_number' => 'GRN-2025-001'], [
            'grn_number' => 'GRN-2025-001', 'fiscal_year_id' => $fy2025, 'receipt_date' => '2025-02-20',
        ]);
        $clone('supplier_invoices', ['invoice_number' => 'INV-VND-2026-001'], ['invoice_number' => 'INV-VND-2025-001'], [
            'invoice_number' => 'INV-VND-2025-001', 'fiscal_year_id' => $fy2025, 'invoice_date' => '2025-02-21', 'due_date' => '2025-03-23',
        ]);
        $clone('supplier_contract_notifications', ['scn_number' => 'SCN-2026-001'], ['scn_number' => 'SCN-2025-001'], [
            'scn_number' => 'SCN-2025-001', 'fiscal_year_id' => $fy2025, 'notification_date' => '2025-02-25',
            'subject' => 'Supplier contract notification for 2025.',
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
                'status' => 'pending', 'created_by' => $userId ?? null, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        DB::table('expense_requests')
            ->where('request_number', 'EXP-2026-004')
            ->update(['fiscal_year_id' => $fy2027, 'request_date' => '2027-03-14']);

        DB::table('timesheet_entries')
            ->where('description', 'Financial monitoring and reporting')
            ->update(['fiscal_year_id' => $fy2027, 'entry_date' => '2027-03-05']);

        // Expense flow: keep the 2026 record and create a linked 2027 copy.
        $expense2027 = $clone('expense_requests', ['request_number' => 'EXP-2026-003'], ['request_number' => 'EXP-2027-001'], [
            'request_number' => 'EXP-2027-001', 'fiscal_year_id' => $fy2027, 'request_date' => '2027-03-12',
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

        // Procurement flow: PR -> PO -> GRN -> invoice -> SCN, all separated.
        $pr2027 = $clone('purchase_requests', ['pr_number' => 'PR-2026-001'], ['pr_number' => 'PR-2027-001'], [
            'pr_number' => 'PR-2027-001', 'fiscal_year_id' => $fy2027, 'request_date' => '2027-02-05',
            'justification' => 'Procure field mapping equipment and workshop supplies for 2027.',
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
            'po_number' => 'PO-2027-001', 'fiscal_year_id' => $fy2027, 'purchase_request_id' => $pr2027,
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
                'status' => 'pending', 'created_by' => $userId ?? null, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
        $clone('customer_invoices', ['invoice_number' => 'AR-2026-001'], ['invoice_number' => 'AR-2027-001'], [
            'invoice_number' => 'AR-2027-001', 'fiscal_year_id' => $fy2027, 'invoice_date' => '2027-03-20',
            'due_date' => '2027-04-19', 'description' => 'Reimbursement claim for 2027 community mapping programme.',
        ]);
        $clone('fixed_assets', ['asset_code' => 'AST-2026-001'], ['asset_code' => 'AST-2027-001'], [
            'asset_code' => 'AST-2027-001', 'fiscal_year_id' => $fy2027, 'asset_name' => 'Laptop Field Mapping 2027',
            'acquisition_date' => '2027-02-10', 'notes' => 'Seeded fixed asset for fiscal year 2027.',
        ]);

        // Make the intended default explicit after older seeders have run.
        DB::table('fiscal_years')->where('id', '!=', $fy2026)->update(['is_active' => false]);
        DB::table('fiscal_years')->where('id', $fy2026)->update(['is_active' => true]);
    }
}
