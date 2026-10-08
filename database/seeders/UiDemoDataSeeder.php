<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stable demo data for UI pages that need list, dashboard, workflow and
 * reporting records. This seeder is safe to run more than once.
 */
class UiDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $user = DB::table('users')->where('email', 'admin@kaoemtelapak.test')->first()
            ?? DB::table('users')->orderBy('id')->first();
        $finance = DB::table('users')->where('email', 'syaifani.havid@kaoemtelapak.org')->first() ?? $user;
        $fy = DB::table('fiscal_years')->where('year', 2026)->first();
        $project = DB::table('projects')->where('code', 'PRJ-2026-FORD-01')->first()
            ?? DB::table('projects')->orderBy('id')->first();
        $grant = DB::table('grant_agreements')->where('grant_no', 'GRT-2026-FORD-01')->first()
            ?? DB::table('grant_agreements')->orderBy('id')->first();
        $donor = DB::table('donors')->where('code', 'DONOR-FORD')->first()
            ?? DB::table('donors')->orderBy('id')->first();
        $program = DB::table('programs')->where('code', 'PROG-FOREST')->first()
            ?? DB::table('programs')->orderBy('id')->first();
        $budgetLine = DB::table('budget_lines')->where('line_code', 'BL-FORD-1.1')->first()
            ?? DB::table('budget_lines')->orderBy('id')->first();
        $vendor = DB::table('vendors')->where('code', 'VND-HOTEL-01')->first()
            ?? DB::table('vendors')->orderBy('id')->first();
        $employee = DB::table('employees')->orderBy('id')->first();
        $bank = DB::table('bank_accounts')->orderBy('id')->first();
        $account = DB::table('chart_of_accounts')->orderBy('id')->first();

        if (! $user || ! $project) {
            $this->command?->warn('UiDemoDataSeeder skipped: users or projects are not seeded yet.');
            return;
        }

        $put = static function (string $table, array $keys, array $values) use ($now): ?int {
            if (! Schema::hasTable($table)) return null;
            $payload = array_merge($keys, $values);
            $payload = collect($payload)->filter(fn ($value, $column) => Schema::hasColumn($table, $column))->all();
            $keyPayload = collect($keys)->filter(fn ($value, $column) => Schema::hasColumn($table, $column))->all();
            if (! $keyPayload) return null;
            $valuesPayload = collect($values)->filter(fn ($value, $column) => Schema::hasColumn($table, $column))->all();
            $exists = DB::table($table)->where($keyPayload)->first();
            if ($exists) {
                DB::table($table)->where($keyPayload)->update(array_merge($valuesPayload, Schema::hasColumn($table, 'updated_at') ? ['updated_at' => $now] : []));
                return (int) $exists->id;
            }
            $timestamps = [];
            if (Schema::hasColumn($table, 'created_at')) $timestamps['created_at'] = $now;
            if (Schema::hasColumn($table, 'updated_at')) $timestamps['updated_at'] = $now;
            return (int) DB::table($table)->insertGetId(array_merge($payload, $timestamps));
        };

        // Make project workplans and activities visible for the selected year.
        if ($fy && Schema::hasTable('project_fiscal_year')) {
            DB::table('project_fiscal_year')->updateOrInsert(
                ['project_id' => $project->id, 'fiscal_year_id' => $fy->id],
                []
            );
        }

        $activityRows = [
            ['code' => 'ACT-2026-01-01', 'name' => 'Facilitasi Konsultasi Publik dengan Pemda Kabupaten', 'pic_name' => 'Kwee Viena Lestari Tanjung', 'start_date' => '2026-01-15', 'end_date' => '2026-02-15'],
            ['code' => 'ACT-2026-01-02', 'name' => 'Pelatihan Pemetaan Partisipatif Wilayah Adat', 'pic_name' => 'Agetha Tri Lestari', 'start_date' => '2026-02-01', 'end_date' => '2026-04-30'],
            ['code' => 'ACT-2026-01-03', 'name' => 'Verifikasi Data dan Penyusunan Policy Brief', 'pic_name' => 'Zufar Fauzan Erimant', 'start_date' => '2026-05-01', 'end_date' => '2026-08-31'],
            ['code' => 'ACT-2026-01-04', 'name' => 'Diseminasi Hasil dan Evaluasi Program', 'pic_name' => 'Sarah Megumi', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30'],
        ];
        $activityIds = [];
        foreach ($activityRows as $row) {
            $activityIds[] = $put('activities', ['project_id' => $project->id, 'code' => $row['code']], $row + ['is_active' => true]);
        }

        $workplanRows = [
            ['activity_code' => 'ACT-2026-01-01', 'output_code' => 'OUTPUT-01', 'activity' => $activityRows[0]['name'], 'responsible' => $activityRows[0]['pic_name'], 'start_date' => '2026-01-15', 'end_date' => '2026-02-15', 'status' => 'completed', 'progress' => 100],
            ['activity_code' => 'ACT-2026-01-02', 'output_code' => 'OUTPUT-01', 'activity' => $activityRows[1]['name'], 'responsible' => $activityRows[1]['pic_name'], 'start_date' => '2026-02-01', 'end_date' => '2026-04-30', 'status' => 'in_progress', 'progress' => 65],
            ['activity_code' => 'ACT-2026-01-03', 'output_code' => 'OUTPUT-02', 'activity' => $activityRows[2]['name'], 'responsible' => $activityRows[2]['pic_name'], 'start_date' => '2026-05-01', 'end_date' => '2026-08-31', 'status' => 'planned', 'progress' => 20],
            ['activity_code' => 'ACT-2026-01-04', 'output_code' => 'OUTPUT-03', 'activity' => $activityRows[3]['name'], 'responsible' => $activityRows[3]['pic_name'], 'start_date' => '2026-09-01', 'end_date' => '2026-11-30', 'status' => 'planned', 'progress' => 0],
        ];
        foreach ($workplanRows as $index => $row) {
            $activityId = $activityIds[$index] ?? null;
            $put('project_workplans', ['project_id' => $project->id, 'activity_code' => $row['activity_code']], $row + [
                'fiscal_year_id' => $fy?->id,
                'activity_id' => $activityId,
                'baseline_start_date' => $row['start_date'],
                'baseline_end_date' => $row['end_date'],
                'notes' => 'Seeded demo workplan data.',
                'periods' => json_encode(['Q1' => true, 'Q2' => true, 'Q3' => true, 'Q4' => true]),
                'is_active' => true,
            ]);
        }

        if ($budgetLine) {
            foreach ([
                ['reference' => 'COMMIT-2026-001', 'source_type' => 'purchase_order', 'amount' => 8200000, 'status' => 'open'],
                ['reference' => 'COMMIT-2026-002', 'source_type' => 'expense_request', 'amount' => 1850000, 'status' => 'converted'],
            ] as $row) {
                $put('budget_commitments', ['reference' => $row['reference']], $row + ['budget_line_id' => $budgetLine->id, 'source_id' => $budgetLine->id, 'created_by' => $user->id]);
            }
        }

        // Receivable, asset and cash-advance pages need at least one complete row.
        if (Schema::hasTable('customers')) {
            $customer = $put('customers', ['code' => 'CUST-SEED-01'], ['name' => 'Donor Partnership Desk', 'email' => 'partnerships@kaoemtelapak.org', 'phone' => '0251-8312345', 'is_active' => true, 'created_by' => $user->id]);
            if ($customer) {
                $invoice = $put('customer_invoices', ['invoice_number' => 'AR-2026-001'], ['customer_id' => $customer, 'project_id' => $project->id, 'donor_id' => $donor?->id, 'program_id' => $program?->id, 'invoice_date' => '2026-03-20', 'due_date' => '2026-04-19', 'currency_code' => 'IDR', 'exchange_rate' => 1, 'status' => 'partially_received', 'total_amount' => 12500000, 'received_amount' => 5000000, 'description' => 'Reimbursement claim for community mapping program.', 'created_by' => $user->id]);
                $put('customer_invoice_lines', ['customer_invoice_id' => $invoice, 'description' => 'Community mapping program reimbursement'], ['revenue_account_id' => $account?->id, 'budget_line_id' => $budgetLine?->id, 'quantity' => 1, 'unit_price' => 12500000, 'total_amount' => 12500000, 'line_order' => 1]);
            }
        }

        if (Schema::hasTable('fixed_assets')) {
            $assetCategory = DB::table('asset_categories')->orderBy('id')->first();
            $asset = $put('fixed_assets', ['asset_code' => 'AST-2026-001'], ['asset_name' => 'Laptop Field Mapping', 'asset_category_id' => $assetCategory?->id, 'acquisition_date' => '2026-02-10', 'acquisition_cost' => 14500000, 'vendor_id' => $vendor?->id, 'donor_id' => $donor?->id, 'program_id' => $program?->id, 'project_id' => $project->id, 'location' => 'Head Office Bogor', 'custodian_id' => $employee?->id, 'useful_life_months' => 36, 'depreciation_method' => 'straight_line', 'accumulated_depreciation' => 1208333, 'net_book_value' => 13291667, 'status' => 'active', 'notes' => 'Seeded fixed asset for asset and depreciation screens.', 'created_by' => $user->id]);
            if ($asset) $put('asset_depreciations', ['fixed_asset_id' => $asset, 'depreciation_date' => '2026-03-31'], ['amount' => 402778, 'accumulated_depreciation' => 1208333, 'net_book_value' => 13291667, 'created_by' => $user->id]);
        }

        if (Schema::hasTable('notifications')) {
            foreach ([
                ['title' => 'Workplan review required', 'message' => 'Project workplan activity is ready for review.', 'type' => 'approval', 'action_url' => '/project-timeline-workplan'],
                ['title' => 'Grant report deadline approaching', 'message' => 'The quarterly grant report is due soon.', 'type' => 'warning', 'action_url' => '/donor-grant/reporting'],
                ['title' => 'Payment completed', 'message' => 'Supplier payment PAY-2026-001 has been completed.', 'type' => 'success', 'action_url' => '/accounts-payable/payment-register'],
            ] as $row) {
                $put('notifications', ['user_id' => $user->id, 'title' => $row['title']], $row);
            }
        }

        // A complete RFQ/CBA trail makes procurement tabs and dashboards useful.
        $purchaseRequest = DB::table('purchase_requests')->where('pr_number', 'PR-2026-001')->first();
        if ($purchaseRequest && $vendor && Schema::hasTable('rfqs')) {
            $rfq = $put('rfqs', ['rfq_number' => 'RFQ-2026-001'], ['purchase_request_id' => $purchaseRequest->id, 'rfq_date' => '2026-02-06', 'submission_deadline' => '2026-02-12', 'terms' => 'Submit technical and financial quotation.', 'status' => 'closed', 'created_by' => $user->id]);
            $put('rfq_vendors', ['rfq_id' => $rfq, 'vendor_id' => $vendor->id], ['status' => 'responded']);
            $quotation = $put('vendor_quotations', ['rfq_id' => $rfq, 'vendor_id' => $vendor->id], ['quotation_number' => 'QTN-2026-001', 'quotation_date' => '2026-02-10', 'total_amount' => 8200000, 'currency_code' => 'IDR', 'terms' => '30 days payment term', 'delivery_terms' => 'Delivery within 14 days', 'technical_score' => 88, 'financial_score' => 92, 'total_score' => 90, 'status' => 'selected', 'notes' => 'Selected demo quotation.', 'created_by' => $user->id]);
            $put('comparative_bid_analyses', ['cba_number' => 'CBA-2026-001'], ['rfq_id' => $rfq, 'analysis_date' => '2026-02-12', 'selected_vendor_id' => $vendor->id, 'selected_quotation_id' => $quotation, 'selection_reason' => 'Best combined technical and financial score.', 'status' => 'approved', 'approved_by' => $user->id, 'approved_at' => $now, 'created_by' => $user->id]);
        }

        // Tax screens: provide multiple tax types and realistic transaction states.
        if (Schema::hasTable('tax_transactions')) {
            $taxRows = [
                ['code' => 'PPh 23', 'reference' => 'TAX-2026-001', 'direction' => 'withholding_out', 'taxable_amount' => 4200000, 'status' => 'reported', 'e_bupot_reference' => 'BUPOT-2026-001', 'e_bupot_number' => 'BPU-2603-0001'],
                ['code' => 'PPh 4(2)', 'reference' => 'TAX-2026-002', 'direction' => 'withholding_out', 'taxable_amount' => 7500000, 'status' => 'draft', 'e_bupot_reference' => 'BUPOT-2026-002', 'e_bupot_number' => 'BPU-2603-0002'],
                ['code' => 'PPH21-TER-STAFF', 'reference' => 'TAX-2026-003', 'direction' => 'withholding_out', 'taxable_amount' => 9800000, 'status' => 'reported', 'e_bupot_reference' => null, 'e_bupot_number' => null],
                ['code' => 'PPh 23', 'reference' => 'TAX-2026-004', 'direction' => 'purchase', 'taxable_amount' => 2150000, 'status' => 'draft', 'e_bupot_reference' => null, 'e_bupot_number' => null],
            ];
            foreach ($taxRows as $row) {
                $taxRecord = DB::table('taxes')->where('code', $row['code'])->first();
                if (! $taxRecord) continue;
                $taxAmount = round((float) $row['taxable_amount'] * ((float) $taxRecord->rate_percent / 100), 2);
                $put('tax_transactions', ['reference' => $row['reference']], [
                    'tax_id' => $taxRecord->id,
                    'transaction_type' => 'manual',
                    'source_type' => 'expense_request',
                    'source_id' => null,
                    'transaction_date' => '2026-03-'.str_pad((string) (15 + (int) substr($row['reference'], -3) - 1), 2, '0', STR_PAD_LEFT),
                    'direction' => $row['direction'],
                    'taxable_amount' => $row['taxable_amount'],
                    'tax_rate' => $taxRecord->rate_percent,
                    'tax_amount' => $taxAmount,
                    'net_amount' => $row['taxable_amount'],
                    'gross_amount' => $row['taxable_amount'] + $taxAmount,
                    'e_bupot_reference' => $row['e_bupot_reference'],
                    'e_bupot_number' => $row['e_bupot_number'],
                    'npwp' => '01.234.567.8-403.000',
                    'status' => $row['status'],
                    'notes' => 'Seeded tax transaction for UI demonstration.',
                    'created_by' => $user->id,
                    'reported_by' => $row['status'] === 'reported' ? ($finance?->id ?? $user->id) : null,
                    'reported_at' => $row['status'] === 'reported' ? $now : null,
                ]);
            }
        }

        // Procurement screens: add several records so list, dashboard and
        // status filters show a real multi-stage purchasing pipeline.
        if ($budgetLine && $vendor && Schema::hasTable('purchase_requests')) {
            $department = DB::table('departments')->where('code', 'PRC')->first();
            $paymentMethod = DB::table('payment_methods')->orderBy('id')->first();
            $procurementRows = [
                ['suffix' => '002', 'item' => 'Portable GPS and mapping accessories', 'amount' => 6800000, 'pr_status' => 'submitted', 'po_status' => 'draft', 'invoice_status' => 'draft'],
                ['suffix' => '003', 'item' => 'Workshop venue and participant materials', 'amount' => 12500000, 'pr_status' => 'approved', 'po_status' => 'approved', 'invoice_status' => 'matched'],
                ['suffix' => '004', 'item' => 'Communication and campaign production', 'amount' => 5400000, 'pr_status' => 'approved', 'po_status' => 'closed', 'invoice_status' => 'paid'],
            ];
            foreach ($procurementRows as $row) {
                $pr = $put('purchase_requests', ['pr_number' => 'PR-2026-'.$row['suffix']], [
                    'request_date' => '2026-03-'.(10 + (int) $row['suffix']),
                    'requester_id' => $finance?->id ?? $user->id,
                    'department_id' => $department?->id,
                    'project_id' => $project->id,
                    'vendor_id' => $vendor->id,
                    'justification' => $row['item'].' for the 2026 programme.',
                    'status' => $row['pr_status'],
                    'submitted_by' => $finance?->id ?? $user->id,
                    'submitted_at' => $now,
                    'approved_by' => in_array($row['pr_status'], ['approved'], true) ? $user->id : null,
                    'approved_at' => in_array($row['pr_status'], ['approved'], true) ? $now : null,
                    'created_by' => $user->id,
                ]);
                $prLine = $put('purchase_request_lines', ['purchase_request_id' => $pr, 'item_description' => $row['item']], ['budget_line_id' => $budgetLine->id, 'quantity' => 1, 'unit_price' => $row['amount'], 'total_amount' => $row['amount'], 'line_order' => 1]);
                $po = $put('purchase_orders', ['po_number' => 'PO-2026-'.$row['suffix']], ['purchase_request_id' => $pr, 'vendor_id' => $vendor->id, 'po_date' => '2026-03-'.(12 + (int) $row['suffix']), 'contract_number' => 'CTR-2026-'.$row['suffix'], 'contract_date' => '2026-03-'.(12 + (int) $row['suffix']), 'terms' => 'Payment within 30 days after receipt.', 'status' => $row['po_status'], 'approved_by' => $row['po_status'] !== 'draft' ? $user->id : null, 'approved_at' => $row['po_status'] !== 'draft' ? $now : null, 'created_by' => $user->id]);
                $poLine = $put('purchase_order_lines', ['purchase_order_id' => $po, 'item_description' => $row['item']], ['purchase_request_line_id' => $prLine, 'budget_line_id' => $budgetLine->id, 'quantity' => 1, 'unit_price' => $row['amount'], 'total_amount' => $row['amount'], 'line_order' => 1]);
                $grnStatus = $row['po_status'] === 'closed' ? 'received' : ($row['po_status'] === 'approved' ? 'received' : 'draft');
                $grn = $put('goods_receipts', ['grn_number' => 'GRN-2026-'.$row['suffix']], ['purchase_order_id' => $po, 'receipt_date' => '2026-03-'.(18 + (int) $row['suffix']), 'notes' => 'Seeded goods receipt for procurement demo.', 'status' => $grnStatus, 'received_by' => $grnStatus === 'received' ? ($finance?->id ?? $user->id) : null, 'received_at' => $grnStatus === 'received' ? $now : null, 'created_by' => $user->id]);
                $put('goods_receipt_lines', ['goods_receipt_id' => $grn, 'purchase_order_line_id' => $poLine], ['received_quantity' => $grnStatus === 'received' ? 1 : 0]);
                $invoice = $put('supplier_invoices', ['invoice_number' => 'INV-VND-2026-'.$row['suffix']], ['purchase_order_id' => $po, 'goods_receipt_id' => $grn, 'vendor_id' => $vendor->id, 'invoice_date' => '2026-03-'.(20 + (int) $row['suffix']), 'due_date' => '2026-04-'.(20 + (int) $row['suffix']), 'status' => $row['invoice_status'], 'match_status' => $row['invoice_status'] === 'draft' ? 'unchecked' : 'matched', 'total_amount' => $row['amount'], 'paid_amount' => $row['invoice_status'] === 'paid' ? $row['amount'] : 0, 'notes' => 'Seeded supplier invoice for procurement demo.', 'created_by' => $user->id]);
                $put('supplier_invoice_lines', ['supplier_invoice_id' => $invoice, 'item_description' => $row['item']], ['purchase_order_line_id' => $poLine, 'quantity' => 1, 'unit_price' => $row['amount'], 'total_amount' => $row['amount']]);
                if ($row['invoice_status'] === 'paid' && $bank) {
                    $payment = $put('payments', ['payment_number' => 'PAY-2026-'.$row['suffix']], ['supplier_invoice_id' => $invoice, 'vendor_id' => $vendor->id, 'bank_account_id' => $bank->id, 'payment_method_id' => $paymentMethod?->id, 'payment_date' => '2026-04-'.(1 + (int) $row['suffix']), 'amount' => $row['amount'], 'reference' => 'TRF-202604-'.$row['suffix'], 'status' => 'paid', 'created_by' => $user->id]);
                    $put('bank_transactions', ['reference' => 'TRF-202604-'.$row['suffix']], ['bank_account_id' => $bank->id, 'payment_id' => $payment, 'transaction_date' => '2026-04-'.(1 + (int) $row['suffix']), 'description' => 'Payment supplier invoice INV-VND-2026-'.$row['suffix'], 'debit' => $row['amount'], 'credit' => 0, 'status' => 'reconciled', 'created_by' => $user->id]);
                }
            }
        }
    }
}
