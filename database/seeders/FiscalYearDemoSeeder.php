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
        $fy2027 = $years[2027]->id;
        $userId = DB::table('users')->orderBy('id')->value('id');

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
