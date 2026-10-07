<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo records for transactional screens. Every record uses a stable business
 * reference so `db:seed` can be run repeatedly without duplicating fixtures.
 */
class FeatureDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $user = DB::table('users')->where('email', 'admin@kaoemtelapak.test')->first()
            ?? DB::table('users')->orderBy('id')->first();
        $finance = DB::table('users')->where('email', 'syaifani.havid@kaoemtelapak.org')->first() ?? $user;
        $project = DB::table('projects')->where('code', 'PRJ-2026-FORD-01')->first();
        $project2 = DB::table('projects')->where('code', 'PRJ-2027-FORD-01')->first() ?? $project;
        $grant = DB::table('grant_agreements')->where('grant_no', 'GRT-2026-FORD-01')->first();
        $donor = DB::table('donors')->where('code', 'DONOR-FORD')->first();
        $program = DB::table('programs')->where('code', 'PROG-FOREST')->first();
        $fy = DB::table('fiscal_years')->where('year', 2026)->first();
        $dept = DB::table('departments')->where('code', 'Fin')->first();
        $idr = DB::table('currencies')->where('code', 'IDR')->first();
        $vendor = DB::table('vendors')->where('code', 'VND-HOTEL-01')->first();
        $vendor2 = DB::table('vendors')->where('code', 'VND-PRINT-02')->first() ?? $vendor;
        $bank = DB::table('bank_accounts')->orderBy('id')->first();
        $paymentMethod = DB::table('payment_methods')->orderBy('id')->first();
        $employee = DB::table('employees')->orderBy('id')->first();
        $activity = DB::table('activities')->orderBy('id')->first();
        $budgetLine = DB::table('budget_lines')->where('line_code', 'BL-FORD-1.1')->first()
            ?? DB::table('budget_lines')->orderBy('id')->first();
        $expenseCategory = DB::table('expense_categories')->orderBy('id')->first();
        $tax = DB::table('taxes')->where('code', 'PPh 23')->first() ?? DB::table('taxes')->orderBy('id')->first();

        if (!$user || !$project || !$grant || !$idr) {
            $this->command?->warn('FeatureDataSeeder skipped: base master data is incomplete. Run MasterDataSeeder first.');
            return;
        }

        $put = static function (string $table, array $keys, array $values) use ($now): int {
            $payload = array_merge($values, ['updated_at' => $now]);
            $exists = DB::table($table)->where($keys)->exists();
            if ($exists) {
                DB::table($table)->where($keys)->update($payload);
                return (int) DB::table($table)->where($keys)->value('id');
            }
            $id = DB::table($table)->insertGetId(array_merge($keys, $values, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            return (int) $id;
        };

        // Donor/grant tracking screens.
        $deadlines = [
            ['report_type' => 'Quarterly Financial Report', 'due_date' => '2026-04-30', 'status' => 'submitted'],
            ['report_type' => 'Semi-Annual Narrative Report', 'due_date' => '2026-07-31', 'status' => 'in_progress'],
            ['report_type' => 'Annual Grant Report', 'due_date' => '2027-01-31', 'status' => 'upcoming'],
        ];
        foreach ($deadlines as $row) {
            $put('grant_reporting_deadlines', [
                'grant_agreement_id' => $grant->id,
                'report_type' => $row['report_type'],
            ], $row + ['notes' => 'Seeded reporting milestone for demo data.']);
        }

        $logframes = [
            ['code' => 'IMPACT-01', 'level' => 'impact', 'description' => 'Forest governance strengthened in priority landscapes.', 'indicator' => 'Communities with improved forest governance', 'baseline' => 4, 'target' => 12, 'actual' => 7, 'unit' => 'communities'],
            ['code' => 'OUT-01', 'level' => 'outcome', 'description' => 'Local partners use evidence in advocacy and policy dialogue.', 'indicator' => 'Policy engagements supported', 'baseline' => 2, 'target' => 8, 'actual' => 5, 'unit' => 'engagements'],
            ['code' => 'OUT-02', 'level' => 'outcome', 'description' => 'Participatory mapping data is available to stakeholders.', 'indicator' => 'Mapping outputs published', 'baseline' => 0, 'target' => 6, 'actual' => 3, 'unit' => 'outputs'],
            ['code' => 'OUTPUT-01', 'level' => 'output', 'description' => 'Community mapping and legal recognition clinics delivered.', 'indicator' => 'Clinics delivered', 'baseline' => 0, 'target' => 10, 'actual' => 6, 'unit' => 'clinics'],
        ];
        foreach ($logframes as $row) {
            $put('project_logframes', ['project_id' => $project->id, 'code' => $row['code']], $row + ['period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'is_active' => true]);
        }

        $workplans = [
            ['activity_code' => 'ACT-2026-01-01', 'output_code' => 'OUTPUT-01', 'activity' => 'Facilitasi Konsultasi Publik dengan Pemda Kabupaten', 'responsible' => 'Kwee Viena Lestari Tanjung', 'start_date' => '2026-01-15', 'end_date' => '2026-02-15', 'status' => 'completed', 'progress' => 100],
            ['activity_code' => 'ACT-2026-01-02', 'output_code' => 'OUTPUT-01', 'activity' => 'Pelatihan Pemetaan Partisipatif Wilayah Adat', 'responsible' => 'Agetha Tri Lestari', 'start_date' => '2026-02-01', 'end_date' => '2026-04-30', 'status' => 'in_progress', 'progress' => 65],
            ['activity_code' => 'ACT-2026-01-03', 'output_code' => 'OUTPUT-01', 'activity' => 'Verifikasi Data dan Penyusunan Policy Brief', 'responsible' => 'Zufar Fauzan Erimant', 'start_date' => '2026-05-01', 'end_date' => '2026-08-31', 'status' => 'planned', 'progress' => 20],
        ];
        foreach ($workplans as $row) {
            $put('project_workplans', ['project_id' => $project->id, 'activity_code' => $row['activity_code']], $row + ['activity_id' => $activity?->id, 'notes' => 'Seeded project workplan activity.', 'periods' => json_encode(['Q1' => true, 'Q2' => true, 'Q3' => false, 'Q4' => false]), 'is_active' => true]);
        }

        foreach ([$user, $finance] as $idx => $assignedUser) {
            if (!$assignedUser) continue;
            $put('project_assignments', ['user_id' => $assignedUser->id, 'project_id' => $project->id], ['role' => $idx === 0 ? 'Project Manager' : 'Finance Officer', 'assigned_from' => '2026-01-01', 'is_active' => true, 'created_by' => $user->id]);
        }

        // Expense requests and their detail lines.
        $expenseRows = [
            ['ref' => 'EXP-2026-001', 'type' => 'reimbursement', 'status' => 'submitted', 'amount' => 1850000, 'description' => 'Per diem and local transport for mapping workshop.'],
            ['ref' => 'EXP-2026-002', 'type' => 'supplier_payment', 'status' => 'approved', 'amount' => 4200000, 'description' => 'Printing and workshop materials.'],
            ['ref' => 'EXP-2026-003', 'type' => 'cash_advance', 'status' => 'paid', 'amount' => 6000000, 'description' => 'Field activity cash advance for West Kalimantan.'],
            ['ref' => 'EXP-2026-004', 'type' => 'settlement_advance', 'status' => 'verified', 'amount' => 2750000, 'description' => 'Settlement of field travel advance.'],
        ];
        $expenseIds = [];
        foreach ($expenseRows as $row) {
            $expenseIds[] = $put('expense_requests', ['request_number' => $row['ref']], [
                'external_request_id' => 'SEED-'.$row['ref'], 'requester_id' => $finance->id ?? $user->id, 'donor_id' => $donor?->id, 'grant_agreement_id' => $grant->id, 'program_id' => $program?->id, 'project_id' => $project->id, 'department_id' => $dept?->id, 'funding_source_id' => $grant->funding_source_id ?? null, 'expense_type' => $row['type'], 'request_date' => '2026-03-'.str_pad((string) (10 + count($expenseIds)), 2, '0', STR_PAD_LEFT), 'currency_code' => 'IDR', 'exchange_rate' => 1, 'description' => $row['description'], 'status' => $row['status'], 'paid_amount' => in_array($row['status'], ['approved', 'paid'], true) ? $row['amount'] : 0, 'settled_amount' => $row['type'] === 'settlement_advance' ? $row['amount'] : 0, 'settlement_status' => $row['type'] === 'cash_advance' ? 'outstanding' : ($row['type'] === 'settlement_advance' ? 'settled' : 'not_applicable'), 'settlement_due_date' => '2026-05-31', 'submitted_by' => $finance->id ?? $user->id, 'submitted_at' => $now, 'approved_by' => in_array($row['status'], ['approved', 'paid'], true) ? $user->id : null, 'approved_at' => in_array($row['status'], ['approved', 'paid'], true) ? $now : null, 'verified_by' => $finance->id ?? $user->id, 'verified_at' => $now, 'created_by' => $user->id,
            ]);
        }
        foreach ($expenseIds as $index => $expenseId) {
            $row = $expenseRows[$index];
            $put('expense_request_lines', ['expense_request_id' => $expenseId, 'description' => $row['description']], ['expense_category_id' => $expenseCategory?->id, 'budget_line_id' => $budgetLine?->id, 'amount' => $row['amount'], 'line_order' => 1]);
        }

        // Procurement chain: PR -> PO -> GRN -> supplier invoice -> payment -> bank movement -> SCN.
        if ($vendor && $budgetLine) {
            $pr = $put('purchase_requests', ['pr_number' => 'PR-2026-001'], ['request_date' => '2026-02-05', 'requester_id' => $finance->id ?? $user->id, 'department_id' => $dept?->id, 'project_id' => $project->id, 'vendor_id' => $vendor->id, 'justification' => 'Procure field mapping equipment and workshop supplies.', 'status' => 'approved', 'submitted_by' => $finance->id ?? $user->id, 'submitted_at' => $now, 'approved_by' => $user->id, 'approved_at' => $now, 'created_by' => $user->id]);
            $prLine = $put('purchase_request_lines', ['purchase_request_id' => $pr, 'item_description' => 'Field mapping and workshop supplies'], ['budget_line_id' => $budgetLine->id, 'quantity' => 1, 'unit_price' => 8200000, 'total_amount' => 8200000, 'line_order' => 1]);
            $po = $put('purchase_orders', ['po_number' => 'PO-2026-001'], ['purchase_request_id' => $pr, 'vendor_id' => $vendor->id, 'po_date' => '2026-02-08', 'terms' => 'Payment within 30 days after receipt.', 'status' => 'approved', 'approved_by' => $user->id, 'approved_at' => $now, 'created_by' => $user->id]);
            $poLine = $put('purchase_order_lines', ['purchase_order_id' => $po, 'item_description' => 'Field mapping and workshop supplies'], ['purchase_request_line_id' => $prLine, 'budget_line_id' => $budgetLine->id, 'quantity' => 1, 'unit_price' => 8200000, 'total_amount' => 8200000, 'line_order' => 1]);
            $grn = $put('goods_receipts', ['grn_number' => 'GRN-2026-001'], ['purchase_order_id' => $po, 'receipt_date' => '2026-02-20', 'notes' => 'All items received in good condition.', 'status' => 'received', 'received_by' => $finance->id ?? $user->id, 'received_at' => $now, 'created_by' => $user->id]);
            $put('goods_receipt_lines', ['goods_receipt_id' => $grn, 'purchase_order_line_id' => $poLine], ['received_quantity' => 1]);
            $invoice = $put('supplier_invoices', ['invoice_number' => 'INV-VND-2026-001'], ['purchase_order_id' => $po, 'goods_receipt_id' => $grn, 'vendor_id' => $vendor->id, 'invoice_date' => '2026-02-21', 'due_date' => '2026-03-23', 'status' => 'paid', 'match_status' => 'matched', 'total_amount' => 8200000, 'paid_amount' => 8200000, 'notes' => 'Matched to PO and GRN.', 'created_by' => $user->id]);
            $put('supplier_invoice_lines', ['supplier_invoice_id' => $invoice, 'item_description' => 'Field mapping and workshop supplies'], ['purchase_order_line_id' => $poLine, 'quantity' => 1, 'unit_price' => 8200000, 'total_amount' => 8200000]);
            if ($bank) {
                $payment = $put('payments', ['payment_number' => 'PAY-2026-001'], ['supplier_invoice_id' => $invoice, 'vendor_id' => $vendor->id, 'bank_account_id' => $bank->id, 'payment_method_id' => $paymentMethod?->id, 'payment_date' => '2026-03-01', 'amount' => 8200000, 'reference' => 'TRF-20260301-001', 'status' => 'paid', 'created_by' => $user->id]);
                $put('bank_transactions', ['bank_account_id' => $bank->id, 'reference' => 'TRF-20260301-001'], ['payment_id' => $payment, 'fiscal_year_id' => $fy?->id, 'transaction_date' => '2026-03-01', 'description' => 'Payment supplier invoice INV-VND-2026-001', 'debit' => 8200000, 'credit' => 0, 'status' => 'reconciled', 'created_by' => $user->id]);
            }
            $put('supplier_contract_notifications', ['scn_number' => 'SCN-2026-001'], ['purchase_request_id' => $pr, 'purchase_order_id' => $po, 'vendor_id' => $vendor->id, 'notification_date' => '2026-02-25', 'subject' => 'Supplier contract notification for field workshop', 'notes' => 'Contract trail seeded for procurement demo.', 'status' => 'issued', 'created_by' => $user->id, 'issued_at' => $now]);
        }

        // Accounting, tax and timesheet screens.
        $cashAccount = DB::table('chart_of_accounts')->where('code', '10210')->first() ?? DB::table('chart_of_accounts')->orderBy('id')->first();
        $expenseAccount = DB::table('chart_of_accounts')->where('code', '51410')->first() ?? DB::table('chart_of_accounts')->where('account_type', 'expense')->first();
        if ($cashAccount && $expenseAccount) {
            $journal = $put('journals', ['journal_number' => 'JV-2026-DEMO-01'], ['fiscal_year_id' => $fy?->id, 'journal_date' => '2026-03-10', 'journal_type' => 'manual', 'reference' => 'EXP-2026-001', 'description' => 'Field workshop travel and accommodation expense.', 'currency_id' => $idr->id, 'exchange_rate' => 1, 'status' => 'posted', 'posted_by' => $user->id, 'posted_at' => $now, 'created_by' => $user->id]);
            $put('journal_lines', ['journal_id' => $journal, 'line_order' => 1], ['account_id' => $expenseAccount->id, 'donor_id' => $donor?->id, 'program_id' => $program?->id, 'project_id' => $project->id, 'budget_line_id' => $budgetLine?->id, 'department_id' => $dept?->id, 'line_description' => 'Workshop expense', 'debit' => 1850000, 'credit' => 0]);
            $put('journal_lines', ['journal_id' => $journal, 'line_order' => 2], ['account_id' => $cashAccount->id, 'line_description' => 'Paid from operating bank account', 'debit' => 0, 'credit' => 1850000]);
        }
        if ($tax) {
            $put('tax_transactions', ['reference' => 'TAX-2026-001'], ['tax_id' => $tax->id, 'transaction_type' => 'manual', 'source_type' => 'expense_request', 'source_id' => $expenseIds[1] ?? null, 'transaction_date' => '2026-03-15', 'direction' => 'withholding_out', 'taxable_amount' => 4200000, 'tax_rate' => $tax->rate_percent, 'tax_amount' => round(4200000 * ((float) $tax->rate_percent / 100), 2), 'net_amount' => 4200000, 'gross_amount' => 4200000, 'e_bupot_reference' => 'BUPOT-2026-001', 'status' => 'reported', 'notes' => 'Seeded withholding transaction.', 'created_by' => $user->id, 'reported_by' => $finance->id ?? $user->id, 'reported_at' => $now]);
        }
        if ($employee || $finance) {
            foreach ([['TS-2026-001', '2026-03-03', 8, 'Participatory mapping preparation', 'submitted'], ['TS-2026-002', '2026-03-04', 7.5, 'Community consultation facilitation', 'approved'], ['TS-2026-003', '2026-03-05', 6, 'Financial monitoring and reporting', 'draft']] as $row) {
                $put('timesheet_entries', ['entry_date' => $row[1], 'description' => $row[3], 'user_id' => $finance->id ?? $user->id], ['employee_id' => $employee?->id, 'worker_type' => 'internal', 'hours' => $row[2], 'donor_id' => $donor?->id, 'program_id' => $program?->id, 'project_id' => $project2->id, 'activity_id' => $activity?->id, 'department_id' => $dept?->id, 'is_billable' => true, 'task_type' => 'project_activity', 'rate_scheme' => 'standard', 'applied_rate' => 150000, 'billable_hours' => $row[2], 'calculated_amount' => $row[2] * 150000, 'work_area' => 'Field Operations', 'workstream' => 'Forest Governance', 'time_category' => 'working_time', 'status' => $row[4], 'submitted_by' => $finance->id ?? $user->id, 'submitted_at' => $now, 'approved_by' => $row[4] === 'approved' ? $user->id : null, 'approved_at' => $row[4] === 'approved' ? $now : null, 'created_by' => $user->id]);
            }
        }

        // Reports, audit trail and workflow queues.
        foreach ([
            ['slug' => 'financial-position', 'label' => 'Statement of Financial Position', 'source' => 'journals', 'dimensions' => ['account', 'project'], 'metrics' => ['debit', 'credit', 'balance']],
            ['slug' => 'grant-utilization', 'label' => 'Grant Budget Utilization', 'source' => 'budget_lines', 'dimensions' => ['donor', 'project'], 'metrics' => ['budget', 'committed', 'actual']],
            ['slug' => 'expense-aging', 'label' => 'Expense Request Aging', 'source' => 'expense_requests', 'dimensions' => ['status', 'department'], 'metrics' => ['count', 'amount']],
        ] as $row) {
            $put('report_definitions', ['slug' => $row['slug']], ['label' => $row['label'], 'source' => $row['source'], 'dimensions' => json_encode($row['dimensions']), 'metrics' => json_encode($row['metrics']), 'enabled' => true]);
        }
        foreach ([['value' => 'bar', 'label' => 'Bar Chart'], ['value' => 'line', 'label' => 'Line Chart'], ['value' => 'donut', 'label' => 'Donut Chart'], ['value' => 'table', 'label' => 'Data Table']] as $row) {
            $put('report_chart_types', ['value' => $row['value']], ['label' => $row['label'], 'enabled' => true]);
        }
        $entity = 'App\\Models\\Expense\\ExpenseRequest';
        $run = $put('approval_workflow_runs', ['module' => 'expense', 'approvable_type' => $entity, 'approvable_id' => $expenseIds[0] ?? 1], ['status' => 'in_progress', 'current_level' => 1, 'submitted_by' => $finance->id ?? $user->id]);
        $matrix = DB::table('approval_matrices')->where('module', 'expense')->orderBy('level')->first();
        $role = DB::table('roles')->where('slug', 'finance-manager')->first();
        $put('approval_workflow_actions', ['approval_workflow_run_id' => $run, 'level' => 1], ['approval_matrix_id' => $matrix?->id, 'role_id' => $role?->id, 'user_id' => $finance->id ?? $user->id, 'status' => 'pending', 'notes' => 'Awaiting Finance Manager review.']);
        foreach ([['module' => 'Procurement', 'action' => 'created', 'entity_type' => 'PurchaseRequest', 'entity_id' => 1], ['module' => 'Expenses', 'action' => 'submitted', 'entity_type' => 'ExpenseRequest', 'entity_id' => $expenseIds[0] ?? 1], ['module' => 'Accounting', 'action' => 'posted', 'entity_type' => 'Journal', 'entity_id' => 1], ['module' => 'Timesheet', 'action' => 'approved', 'entity_type' => 'TimesheetEntry', 'entity_id' => 2]] as $row) {
            DB::table('audit_logs')->updateOrInsert(
                ['module' => $row['module'], 'action' => $row['action'], 'entity_type' => $row['entity_type'], 'entity_id' => $row['entity_id']],
                ['user_id' => $user->id, 'platform' => 'web', 'previous_values' => json_encode([]), 'new_values' => json_encode(['seeded' => true]), 'ip_address' => '127.0.0.1', 'user_agent' => 'Kaoem Telapak demo seeder', 'created_at' => $now],
            );
        }
    }
}
