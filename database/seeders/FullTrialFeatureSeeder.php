<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coverage fixtures for screens that are not part of the primary process
 * chain.  The records are deliberately keyed and idempotent so this seeder
 * can be run repeatedly in a demo environment.
 */
class FullTrialFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $user = DB::table('users')->where('email', 'admin@kaoemtelapak.test')->first()
            ?? DB::table('users')->orderBy('id')->first();
        if (! $user) return;

        $years = DB::table('fiscal_years')->whereIn('year', [2025, 2026, 2027])->pluck('id', 'year');
        $grant = DB::table('grant_agreements')->orderBy('id')->first();
        $project = DB::table('projects')->orderBy('id')->first();
        $budget = DB::table('budget_lines')->orderBy('id')->first();
        $currency = DB::table('currencies')->where('code', 'IDR')->value('id');
        $bank = DB::table('bank_accounts')->orderBy('id')->first();
        $vendor = DB::table('vendors')->orderBy('id')->first();
        $employee = DB::table('employees')->orderBy('id')->first();
        if (Schema::hasTable('employees')) {
            $employee = DB::table('employees')->where('email', $user->email)->first() ?: $employee;
            if (! $employee) {
                $employeePayload = collect([
                    'employee_id_number' => 'TRIAL-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'name' => $user->name ?: 'Trial Staff',
                    'email' => $user->email,
                    'join_date' => '2024-01-15',
                    'employment_type' => 'internal',
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->filter(fn ($value, $column) => Schema::hasColumn('employees', $column))->all();
                $employeeId = DB::table('employees')->insertGetId($employeePayload);
                $employee = DB::table('employees')->where('id', $employeeId)->first();
            }
        }
        $role = DB::table('roles')->orderBy('id')->first();
        $matrix = DB::table('approval_matrices')->orderBy('id')->first();

        // Complete staff profiles so the Administration > Staff table can
        // resolve department, position, contact, and employment fields.
        if (Schema::hasTable('employees')) {
            $staffDepartment = DB::table('departments')->orderBy('id')->first();
            $staffPosition = DB::table('positions')->orderBy('id')->first();
            foreach (DB::table('employees')->orderBy('id')->get() as $index => $staff) {
                $updates = [
                    'department_id' => $staff->department_id ?: $staffDepartment?->id,
                    'fiscal_year_id' => Schema::hasColumn('employees', 'fiscal_year_id') ? ($staff->fiscal_year_id ?: ($years[2026] ?? null)) : null,
                    'position_id' => Schema::hasColumn('employees', 'position_id') ? ($staff->position_id ?: $staffPosition?->id) : null,
                    'position' => $staff->position ?: ($staffPosition?->name ?? 'Programme Officer'),
                    'email' => $staff->email ?: 'staff'.($index + 1).'@kaoemtelapak.org',
                    'employment_type' => Schema::hasColumn('employees', 'employment_type') ? ($staff->employment_type ?: 'internal') : null,
                    'join_date' => $staff->join_date ?: '2024-01-15',
                    'is_active' => true,
                    'updated_by' => $user->id,
                ];
                $updates = collect($updates)->filter(fn ($value, $key) => Schema::hasColumn('employees', $key) && $value !== null)->all();
                if ($updates) DB::table('employees')->where('id', $staff->id)->update(array_merge($updates, ['updated_at' => $now]));
            }
        }

        // Budget monitoring groups by activity/sub-output. Older fixtures
        // created valid budget lines but left these dimensions null, which
        // made the UI show blank activity rows even though the line existed.
        if (Schema::hasTable('activities') && Schema::hasTable('budget_lines')) {
            $activities = DB::table('activities')->orderBy('id')->get();
            foreach (DB::table('budget_lines')->orderBy('id')->get() as $index => $line) {
                $activity = $activities->firstWhere('project_id', $line->project_id) ?? $activities->get($index % max(1, $activities->count()));
                if (! $activity) continue;
                $updates = collect([
                    'activity_id' => $activity->id,
                    'activity_code' => $activity->code,
                    'activity_name' => $activity->name,
                    'sub_output_code' => $activity->target_output ?: 'OUTPUT-01',
                    'sub_output_name' => 'Community mapping and policy engagement',
                ])->filter(fn ($value, $column) => Schema::hasColumn('budget_lines', $column))->all();
                DB::table('budget_lines')->where('id', $line->id)->update($updates);
            }
        }

        $put = function (string $table, array $keys, array $values) use ($now): ?int {
            if (! Schema::hasTable($table)) return null;
            $keys = collect($keys)->filter(fn ($v, $k) => Schema::hasColumn($table, $k))->all();
            if (! $keys) return null;
            $values = collect($values)->filter(fn ($v, $k) => Schema::hasColumn($table, $k))->all();
            $payload = array_merge($keys, $values);
            if (Schema::hasColumn($table, 'created_at')) $payload['created_at'] = $now;
            if (Schema::hasColumn($table, 'updated_at')) $payload['updated_at'] = $now;
            DB::table($table)->updateOrInsert($keys, $payload);
            return (int) DB::table($table)->where($keys)->value('id');
        };

        // Give every bank account a visible trial balance. The dashboard
        // calculates balance from opening_balance plus bank_transactions, so
        // creating accounts alone intentionally is not enough for a demo.
        if (Schema::hasTable('bank_accounts') && Schema::hasTable('bank_transactions')) {
            foreach (DB::table('bank_accounts')->orderBy('id')->get() as $index => $bankAccount) {
                $opening = 12000000 + ($index * 1750000);
                DB::table('bank_accounts')->where('id', $bankAccount->id)->update(array_filter([
                    'opening_balance' => $opening,
                    'opening_balance_date' => '2025-01-01',
                ], fn ($value, $column) => Schema::hasColumn('bank_accounts', $column), ARRAY_FILTER_USE_BOTH));
                $put('bank_transactions', ['bank_account_id' => $bankAccount->id, 'reference' => "SEED-BANK-IN-{$bankAccount->id}"], [
                    'transaction_date' => '2026-03-05', 'description' => 'Trial grant disbursement received',
                    'debit' => 0, 'credit' => 8500000 + ($index * 250000), 'status' => 'reconciled', 'created_by' => $user->id,
                ]);
                $put('bank_transactions', ['bank_account_id' => $bankAccount->id, 'reference' => "SEED-BANK-OUT-{$bankAccount->id}"], [
                    'transaction_date' => '2026-03-18', 'description' => 'Trial programme operating payment',
                    'debit' => 2750000 + ($index * 100000), 'credit' => 0, 'status' => 'reconciled', 'created_by' => $user->id,
                ]);
            }
        }

        // COA screens show opening and current balances. MasterDataSeeder
        // intentionally keeps COA global, but it previously left all opening
        // balances at zero. Seed realistic trial balances for every account;
        // posted journal lines will be added by the normal process seeders.
        if (Schema::hasTable('chart_of_accounts')) {
            foreach (DB::table('chart_of_accounts')->orderBy('id')->get() as $index => $account) {
                $base = match (strtolower((string) $account->account_type)) {
                    'asset' => 125000000,
                    'liability' => 65000000,
                    'equity' => 225000000,
                    'revenue', 'income' => 95000000,
                    'expense' => 18000000,
                    default => 10000000,
                };
                $opening = $base + ($index * 375000);
                DB::table('chart_of_accounts')->where('id', $account->id)->update(array_filter([
                    'opening_balance' => $opening,
                    'opening_balance_date' => '2025-01-01',
                ], fn ($value, $column) => Schema::hasColumn('chart_of_accounts', $column), ARRAY_FILTER_USE_BOTH));
            }
        }

        // Ensure the General Ledger has a balanced posted journal in every
        // fiscal year and that every line points to a real COA record.
        $expenseAccountId = DB::table('chart_of_accounts')->where('code', '5200')->value('id')
            ?? DB::table('chart_of_accounts')->where('account_type', 'expense')->value('id');
        $cashAccountId = DB::table('chart_of_accounts')->where('code', '10210')->value('id')
            ?? DB::table('chart_of_accounts')->where('account_type', 'asset')->value('id');
        if ($expenseAccountId && $cashAccountId && Schema::hasTable('journals') && Schema::hasTable('journal_lines')) {
            foreach ([2025, 2026, 2027] as $year) {
                $fyId = $years[$year] ?? null;
                $yearProject = DB::table('projects')->where('code', "PRJ-{$year}-FORD-01")->first() ?? $project;
                $yearBudget = DB::table('budget_lines')->where('line_code', "BL-FOREST-{$year}-1")->first()
                    ?? DB::table('budget_lines')->where('fiscal_year_id', $fyId)->orderBy('id')->first()
                    ?? $budget;
                $amount = 1850000 + (($year - 2025) * 400000);
                $journalId = $put('journals', ['journal_number' => "JV-TRIAL-{$year}-GL"], [
                    'fiscal_year_id' => $fyId, 'journal_date' => "{$year}-08-15", 'journal_type' => 'manual',
                    'reference' => "GL-TRIAL-{$year}-001", 'description' => "Trial posted programme expense {$year}",
                    'currency_id' => $currency, 'exchange_rate' => 1, 'status' => 'posted',
                    'posted_by' => $user->id, 'posted_at' => $now, 'created_by' => $user->id,
                ]);
                $put('journal_lines', ['journal_id' => $journalId, 'line_order' => 1], [
                    'account_id' => $expenseAccountId, 'project_id' => $yearProject?->id, 'budget_line_id' => $yearBudget?->id,
                    'line_description' => "Trial programme expense {$year}", 'debit' => $amount, 'credit' => 0,
                ]);
                $put('journal_lines', ['journal_id' => $journalId, 'line_order' => 2], [
                    'account_id' => $cashAccountId, 'line_description' => "Trial bank payment {$year}", 'debit' => 0, 'credit' => $amount,
                ]);
            }
        }

        // Every document-type tab in Requests & Verification needs at least
        // one real request per fiscal year. The original fixtures covered
        // reimbursement and cash advance, but not loan and supplier payment.
        foreach ([2025, 2026, 2027] as $year) {
            $fyId = $years[$year] ?? null;
            $yearProject = DB::table('projects')->where('code', "PRJ-{$year}-FORD-01")->first() ?? $project;
            $yearGrant = DB::table('grant_agreements')->where('grant_no', "GRT-{$year}-FORD-01")->first() ?? $grant;
            $yearExpenseCategory = DB::table('expense_categories')->orderBy('id')->value('id');
            foreach ([
                ['type' => 'loan', 'amount' => 4500000, 'status' => 'submitted', 'label' => 'Field equipment staff loan'],
                ['type' => 'supplier_payment', 'amount' => 6800000, 'status' => 'approved', 'label' => 'Supplier payment voucher'],
            ] as $fixture) {
                $reference = "EXP-{$year}-".($fixture['type'] === 'loan' ? 'LOAN-001' : 'SUPPLIER-001');
                $expenseId = $put('expense_requests', ['request_number' => $reference], [
                    'fiscal_year_id' => $fyId, 'external_request_id' => "SEED-{$reference}",
                    'requester_id' => $user->id, 'donor_id' => $yearGrant?->donor_id, 'grant_agreement_id' => $yearGrant?->id,
                    'project_id' => $yearProject?->id, 'expense_type' => $fixture['type'], 'request_date' => "{$year}-06-15",
                    'currency_code' => 'IDR', 'exchange_rate' => 1, 'description' => "{$fixture['label']} ({$year})",
                    'status' => $fixture['status'], 'paid_amount' => $fixture['status'] === 'approved' ? $fixture['amount'] : 0,
                    'submitted_by' => $user->id, 'submitted_at' => $now,
                    'approved_by' => $fixture['status'] === 'approved' ? $user->id : null,
                    'approved_at' => $fixture['status'] === 'approved' ? $now : null, 'created_by' => $user->id,
                ]);
                if ($expenseId) $put('expense_request_lines', ['expense_request_id' => $expenseId, 'description' => $fixture['label']], [
                    'expense_category_id' => $yearExpenseCategory, 'budget_line_id' => $budget?->id,
                    'amount' => $fixture['amount'], 'line_order' => 1,
                ]);
            }

            if (Schema::hasTable('tax_transactions')) {
                $trialTaxes = DB::table('taxes')->whereIn('code', ['TRIAL-PPH21', 'TRIAL-PPH23', 'TRIAL-PPH42', 'TRIAL-PPN'])->pluck('id', 'code');
                foreach ([
                    ['TRX-TAX-2025-001', 'TRIAL-PPH21', '2025-11-12', 'withholding_out', 18500000, 5, 'reported', 'EBUPOT-2025-001'],
                    ['TRX-TAX-2025-002', 'TRIAL-PPN', '2025-11-18', 'purchase', 32000000, 11, 'reported', 'EFAKTUR-2025-001'],
                    ['TRX-TAX-2026-001', 'TRIAL-PPH23', '2026-11-08', 'withholding_out', 25000000, 2, 'draft', 'EBUPOT-2026-001'],
                    ['TRX-TAX-2026-002', 'TRIAL-PPH42', '2026-11-15', 'withholding_out', 18000000, 10, 'reported', 'EBUPOT-2026-002'],
                    ['TRX-TAX-2026-003', 'TRIAL-PPN', '2026-11-21', 'sales', 45000000, 11, 'reported', 'EFAKTUR-2026-001'],
                    ['TRX-TAX-2027-001', 'TRIAL-PPH21', '2027-11-10', 'withholding_out', 22000000, 5, 'draft', 'EBUPOT-2027-001'],
                    ['TRX-TAX-2027-002', 'TRIAL-PPN', '2027-11-19', 'purchase', 38000000, 11, 'reported', 'EFAKTUR-2027-001'],
                ] as [$reference, $taxCode, $date, $direction, $taxable, $rate, $status, $documentRef]) {
                    $taxId = $trialTaxes[$taxCode] ?? null;
                    if (!$taxId) continue;
                    $taxAmount = round($taxable * $rate / 100, 2);
                    $put('tax_transactions', ['reference' => $reference], [
                        'tax_id' => $taxId, 'transaction_type' => 'manual', 'reference' => $reference,
                        'transaction_date' => $date, 'direction' => $direction, 'taxable_amount' => $taxable,
                        'tax_rate' => $rate, 'tax_amount' => $taxAmount, 'net_amount' => $taxable,
                        'gross_amount' => $taxable + $taxAmount, 'e_bupot_reference' => str_starts_with($documentRef, 'EBUPOT') ? $documentRef : null,
                        'e_faktur_reference' => str_starts_with($documentRef, 'EFAKTUR') ? $documentRef : null,
                        'status' => $status, 'notes' => 'Seeded tax transaction for reporting trial.',
                        'created_by' => $user->id, 'updated_by' => $user->id,
                    ]);
                }
            }
        }

        // Membership dues / AR demo: every year has issued, paid and waived dues.
        $customer = $put('customers', ['code' => 'CUST-TRIAL-MEMBER'], [
            'name' => 'Kaoem Telapak Member Cooperative', 'email' => 'member.trial@kaoemtelapak.org',
            'phone' => '0251-000000', 'address' => 'Bogor, Jawa Barat', 'is_active' => true,
            'created_by' => $user->id,
        ]);
        if ($customer) foreach ([2025, 2026, 2027] as $year) {
            $fy = $years[$year] ?? null;
            foreach ([['issued', 1], ['paid', 2], ['waived', 3]] as [$status, $n]) {
                $put('membership_dues', ['reference' => "IURAN-{$year}-TRIAL-0{$n}"], [
                    'customer_id' => $customer, 'fiscal_year_id' => $fy, 'due_date' => "{$year}-0{$n}-28",
                    'amount' => 250000, 'status' => $status, 'paid_at' => $status === 'paid' ? "{$year}-0{$n}-20" : null,
                    'payment_reference' => $status === 'paid' ? "PAY-IURAN-{$year}-0{$n}" : null,
                    'notes' => "Trial membership due {$year} ({$status}).", 'created_by' => $user->id,
                ]);
            }
        }

        // Forecast and donor disbursement cards.
        if ($grant) foreach ([2025, 2026, 2027] as $year) {
            $fy = $years[$year] ?? null;
            $forecast = $put('forecast_entries', ['reference' => "FC-TRIAL-{$year}-Q1"], [
                'fiscal_year_id' => $fy, 'grant_agreement_id' => $grant->id, 'project_id' => $project?->id,
                'entry_type' => 'disbursement_request', 'period_date' => "{$year}-03-31", 'amount' => 75000000,
                'currency_id' => $currency, 'exchange_rate' => 1, 'status' => 'approved',
                'notes' => "Approved Q1 forecast {$year}.", 'created_by' => $user->id,
            ]);
            $put('forecast_entries', ['reference' => "FC-TRIAL-{$year}-Q2"], [
                'fiscal_year_id' => $fy, 'grant_agreement_id' => $grant->id, 'project_id' => $project?->id,
                'entry_type' => 'disbursement_request', 'period_date' => "{$year}-06-30", 'amount' => 55000000,
                'currency_id' => $currency, 'exchange_rate' => 1, 'status' => $year === 2025 ? 'received' : 'submitted',
                'notes' => "Q2 disbursement request {$year}.", 'created_by' => $user->id,
            ]);
            $put('forecast_entries', ['reference' => "FUNDS-TRIAL-{$year}-Q1"], [
                'fiscal_year_id' => $fy, 'grant_agreement_id' => $grant->id, 'project_id' => $project?->id,
                'entry_type' => 'funds_received', 'period_date' => "{$year}-04-15", 'amount' => 70000000,
                'currency_id' => $currency, 'exchange_rate' => 1, 'status' => 'received',
                'notes' => "Q1 funds received and reconciled {$year}.", 'created_by' => $user->id,
            ]);
            $request = $put('grant_disbursement_requests', ['request_no' => "GDR-TRIAL-{$year}-Q1"], [
                'fiscal_year_id' => $fy, 'grant_agreement_id' => $grant->id, 'bank_account_id' => $bank?->id,
                'period_type' => 'quarterly', 'period_label' => "Q1 {$year}", 'request_date' => "{$year}-02-15",
                'currency_id' => $currency, 'forecast_total_amount' => 75000000, 'requested_amount' => 70000000,
                'requested_amount_idr' => 70000000, 'status' => $year === 2025 ? 'received' : ($year === 2026 ? 'approved' : 'submitted'),
                'received_date' => $year === 2025 ? "{$year}-03-20" : null, 'received_amount' => $year === 2025 ? 70000000 : 0,
                'received_amount_idr' => $year === 2025 ? 70000000 : 0, 'received_bank_account_id' => $bank?->id,
                'created_by' => $user->id,
            ]);
            if ($request) $put('grant_forecast_items', ['disbursement_request_id' => $request, 'period_label' => "Q1 {$year}"], [
                'grant_agreement_id' => $grant->id, 'budget_line_id' => $budget?->id, 'sub_output_code' => "OUT-{$year}-01",
                'sub_output_name' => "Community programme delivery {$year}", 'activity_code' => "ACT-{$year}-01",
                'activity_name' => 'Community mapping and policy engagement', 'cost_category' => 'Programme delivery',
                'proposal_budget' => 80000000, 'historical_expense' => $year === 2025 ? 65000000 : 0,
                'forecast_amount' => 75000000, 'notes' => 'Trial forecast line.',
            ]);
        }

        // Complete the detail tabs for procurement and AR.
        $po = DB::table('purchase_orders')->orderBy('id')->first();
        $poLine = $po ? $put('purchase_order_lines', ['purchase_order_id' => $po->id, 'line_order' => 99], [
            'budget_line_id' => $budget?->id, 'item_description' => 'Trial programme supplies', 'quantity' => 10,
            'unit_price' => 500000, 'total_amount' => 5000000,
        ]) : null;
        $grn = DB::table('goods_receipts')->orderBy('id')->first();
        if ($grn && $poLine) $put('goods_receipt_lines', ['goods_receipt_id' => $grn->id, 'purchase_order_line_id' => $poLine], ['received_quantity' => 10]);
        $invoice = DB::table('supplier_invoices')->orderBy('id')->first();
        if ($invoice) $put('supplier_invoice_lines', ['supplier_invoice_id' => $invoice->id, 'item_description' => 'Trial programme supplies'], [
            'purchase_order_line_id' => $poLine, 'quantity' => 10, 'unit_price' => 500000, 'total_amount' => 5000000,
        ]);
        $ar = DB::table('customer_invoices')->orderBy('id')->first();
        if ($ar) $put('customer_invoice_lines', ['customer_invoice_id' => $ar->id, 'line_order' => 99], [
            'revenue_account_id' => DB::table('chart_of_accounts')->orderBy('id')->value('id'), 'budget_line_id' => $budget?->id,
            'description' => 'Trial programme reimbursement', 'quantity' => 1, 'unit_price' => 12500000, 'total_amount' => 12500000,
        ]);

        // Fill the smaller master/detail screens that are easy to miss when
        // testing the navigation manually. These are real records, not RBAC
        // placeholders, and are safe to re-run because every row has a stable
        // business key.
        foreach ([
            ['code' => 'BP-TRIAL-01', 'name' => 'Community Forest Partner', 'type' => 'community_partner', 'pic_name' => 'Siti Rahma'],
            ['code' => 'BP-TRIAL-02', 'name' => 'Provincial Conservation Office', 'type' => 'government_partner', 'pic_name' => 'Budi Santoso'],
        ] as $row) {
            $put('beneficiary_partners', ['code' => $row['code']], $row + ['is_active' => true, 'created_by' => $user->id]);
        }
        foreach ([
            ['dimension_type' => 'region', 'code' => 'REG-WEST', 'name' => 'West Kalimantan'],
            ['dimension_type' => 'theme', 'code' => 'THEME-FOREST', 'name' => 'Forest Governance'],
            ['dimension_type' => 'channel', 'code' => 'CHANNEL-COMMUNITY', 'name' => 'Community Facilitation'],
        ] as $row) {
            $put('reporting_dimensions', ['dimension_type' => $row['dimension_type'], 'code' => $row['code']], $row + ['is_active' => true, 'created_by' => $user->id]);
        }
        $put('petty_cashes', ['code' => 'PC-TRIAL-HO'], [
            'organization_id' => DB::table('organizations')->value('id'), 'office_location_id' => DB::table('office_locations')->value('id'),
            'currency_id' => $currency, 'name' => 'Head Office Petty Cash', 'custodian_name' => $employee?->name ?? 'Trial Finance Officer',
            'limit_amount' => 5000000, 'gl_account_id' => DB::table('chart_of_accounts')->value('id'), 'is_active' => true, 'created_by' => $user->id,
        ]);
        foreach ([
            ['code' => 'RC-FIN', 'name' => 'Financial Reporting', 'category_type' => 'financial'],
            ['code' => 'RC-PROG', 'name' => 'Programme Reporting', 'category_type' => 'programme'],
            ['code' => 'RC-COMPLIANCE', 'name' => 'Compliance Reporting', 'category_type' => 'compliance'],
        ] as $row) $put('reporting_categories', ['code' => $row['code']], $row + ['is_active' => true, 'created_by' => $user->id]);
        foreach ([
            ['code' => 'PCAT-SUPPLIES', 'name' => 'Programme Supplies'],
            ['code' => 'PCAT-SERVICES', 'name' => 'Professional Services'],
        ] as $row) $put('procurement_categories', ['code' => $row['code']], $row + ['is_active' => true, 'created_by' => $user->id]);
        $procCategory = DB::table('procurement_categories')->where('code', 'PCAT-SUPPLIES')->value('id');
        $uom = DB::table('unit_of_measures')->value('id');
        $put('procurement_items', ['code' => 'ITEM-TRIAL-MAPPING-KIT'], [
            'name' => 'Participatory Mapping Kit', 'item_type' => 'goods', 'procurement_category_id' => $procCategory,
            'unit_of_measure_id' => $uom, 'default_unit_price' => 750000, 'description' => 'Demo item used by the procurement request flow.',
            'is_active' => true, 'created_by' => $user->id,
        ]);
        foreach ([
            ['date' => '2025-01-01', 'name' => 'New Year 2025', 'type' => 'national_holiday'],
            ['date' => '2026-01-01', 'name' => 'New Year 2026', 'type' => 'national_holiday'],
            ['date' => '2027-01-01', 'name' => 'New Year 2027', 'type' => 'national_holiday'],
        ] as $row) $put('holiday_calendars', ['date' => $row['date']], $row + ['is_active' => true, 'created_by' => $user->id]);
        if ($employee && $currency) $put('notifications', ['user_id' => $user->id, 'title' => 'Trial approval notification'], [
            'message' => 'There is a seeded expense request waiting for approval.', 'type' => 'approval',
            'action_url' => '/approval-center', 'read_at' => null,
        ]);
        $rfq = DB::table('rfqs')->orderBy('id')->first();
        if ($rfq && $vendor) $put('rfq_vendors', ['rfq_id' => $rfq->id, 'vendor_id' => $vendor->id], ['status' => 'responded']);
        $fixedAsset = DB::table('fixed_assets')->orderBy('id')->first();
        if ($fixedAsset) $put('asset_depreciations', ['fixed_asset_id' => $fixedAsset->id, 'depreciation_date' => '2026-12-31'], [
            'amount' => 500000, 'accumulated_depreciation' => 500000, 'net_book_value' => 9500000,
            'journal_id' => DB::table('journals')->orderBy('id')->value('id'), 'created_by' => $user->id,
        ]);
        $expense = DB::table('expense_requests')->orderBy('id')->first();
        if ($expense) $put('expense_request_fundings', ['expense_request_id' => $expense->id, 'donor_id' => $grant?->donor_id], [
            'program_id' => $project?->program_id, 'budget_line_id' => $budget?->id, 'amount' => 1850000, 'currency_code' => 'IDR', 'exchange_rate' => 1,
        ]);

        // Tax Setup is a global master, not a transaction list. Keep all
        // categories available for trialing the calculator and tax mapping.
        if (Schema::hasTable('taxes')) {
            $taxGl = DB::table('chart_of_accounts')->whereIn('code', ['2120', '2130', '5200'])->pluck('id', 'code');
            foreach ([
                ['TRIAL-PPH21', 'PPh 21 Staff Trial', 'PPH21', 5.00],
                ['TRIAL-PPH23', 'PPh 23 Services Trial', 'PPH23', 2.00],
                ['TRIAL-PPH42', 'PPh Final Pasal 4(2) Trial', 'PPH_FINAL', 10.00],
                ['TRIAL-PPN', 'PPN / VAT Trial', 'PPN', 11.00],
                ['TRIAL-OTHER', 'Pajak Daerah Trial', 'OTHER', 1.50],
            ] as [$code, $name, $type, $rate]) {
                $put('taxes', ['code' => $code], [
                    'fiscal_year_id' => null, 'name' => $name, 'tax_type' => $type,
                    'rate_percent' => $rate, 'purchase_gl_account_id' => $taxGl['2120'] ?? null,
                    'sales_gl_account_id' => $taxGl['2130'] ?? ($taxGl['2120'] ?? null),
                    'description' => 'Seeded tax master configuration for end-to-end trial.',
                    'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }
        }

        // My Timesheet is scoped by authenticated user_id. Seed entries for
        // the demo login so its personal page is populated as well.
        if (Schema::hasTable('timesheet_entries') && $employee) {
            $myEmployee = DB::table('employees')->where('email', $user->email)->first() ?: $employee;
            $myProject = $project;
            $myActivity = DB::table('activities')->where('project_id', $myProject?->id)->orderBy('id')->first();
            $myDepartment = DB::table('departments')->orderBy('id')->first();
            $myDonor = DB::table('donors')->orderBy('id')->first();
            foreach ([
                [2025, '2025-11-04', 7.5, 'Community mapping and partner coordination', 'approved'],
                [2025, '2025-11-12', 6.0, 'Financial monitoring and donor reporting', 'submitted'],
                [2026, '2026-11-05', 8.0, 'Programme implementation planning', 'approved'],
                [2026, '2026-11-18', 6.5, 'Procurement and field activity follow-up', 'submitted'],
                [2027, '2027-11-03', 7.0, 'Annual workplan and budget preparation', 'draft'],
                [2027, '2027-11-17', 5.5, 'Community partner reporting review', 'submitted'],
            ] as [$year, $date, $hours, $description, $status]) {
                $put('timesheet_entries', ['user_id' => $user->id, 'entry_date' => $date, 'description' => $description], [
                    'fiscal_year_id' => $years[$year] ?? null, 'employee_id' => $myEmployee->id,
                    'worker_type' => 'internal', 'hours' => $hours, 'description' => $description,
                    'donor_id' => $myDonor?->id, 'project_id' => $myProject?->id, 'activity_id' => $myActivity?->id,
                    'department_id' => $myDepartment?->id, 'is_billable' => true, 'status' => $status,
                    'submitted_by' => $user->id, 'submitted_at' => $status === 'draft' ? null : $now,
                    'approved_by' => $status === 'approved' ? $user->id : null, 'approved_at' => $status === 'approved' ? $now : null,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }
        }

        // External Timesheet Register uses the same table but filters strictly
        // by worker_type=external. Provide vendor/contract data so the
        // external register and verification pages have end-to-end fixtures.
        if (Schema::hasTable('timesheet_entries')) {
            foreach ([
                [2025, '2025-10-07', 'PT Borneo Field Services', 'EXT-CTR-2025-001', 6.0, 1800000, 'submitted'],
                [2026, '2026-10-14', 'Konsultan GIS Nusantara', 'EXT-CTR-2026-001', 7.5, 2750000, 'approved'],
                [2026, '2026-11-11', 'PT Borneo Field Services', 'EXT-CTR-2026-002', 8.0, 3200000, 'submitted'],
                [2027, '2027-09-21', 'Konsultan GIS Nusantara', 'EXT-CTR-2027-001', 6.5, 2950000, 'draft'],
            ] as [$year, $date, $vendorName, $contract, $hours, $fee, $status]) {
                $put('timesheet_entries', ['user_id' => $user->id, 'entry_date' => $date, 'description' => "External service delivery - {$vendorName}"], [
                    'fiscal_year_id' => $years[$year] ?? null, 'employee_id' => $employee?->id, 'worker_type' => 'external',
                    'vendor_name' => $vendorName, 'contract_reference' => $contract, 'billing_mode' => 'daily',
                    'fee_total' => $fee, 'work_days' => 1, 'rate_per_day' => $fee, 'hours' => $hours,
                    'description' => "External service delivery - {$vendorName}", 'project_id' => $project?->id,
                    'activity_id' => DB::table('activities')->where('project_id', $project?->id)->orderBy('id')->value('id'),
                    'is_billable' => true, 'status' => $status, 'submitted_by' => $user->id,
                    'submitted_at' => $status === 'draft' ? null : $now,
                    'approved_by' => $status === 'approved' ? $user->id : null, 'approved_at' => $status === 'approved' ? $now : null,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }
            // Repair older fixtures that only had a free-form timesheet row.
            // Project Timesheet needs these relations to render a project
            // code and contributor instead of a dash.
            $repair = collect([
                'project_id' => $project?->id,
                'employee_id' => $employee?->id,
                'activity_id' => DB::table('activities')->where('project_id', $project?->id)->orderBy('id')->value('id'),
            ])->filter(fn ($value) => $value !== null)->all();
            if ($repair) DB::table('timesheet_entries')->where(function ($query) use ($repair) {
                $query->whereNull('project_id')->orWhereNull('employee_id')->orWhereNull('activity_id');
            })->update(array_merge($repair, ['updated_at' => $now]));
        }

        // Waiver & Justification must also be visible when the user switches
        // the fiscal-year selector. Keep one realistic exception per year.
        if (Schema::hasTable('procurement_waivers')) {
            $waiverRows = [
                [2025, 'WVR-TRIAL-2025-001', '2025-03-18', 1250000, 'Emergency field logistics waiver 2025', 'Urgent delivery is required to meet the approved field schedule.', 'approved'],
                [2026, 'WVR-TRIAL-2026-001', '2026-04-22', 2400000, 'Specialist conservation survey waiver 2026', 'Only one qualified provider is available within the approved programme timeline.', 'pending'],
                [2027, 'WVR-TRIAL-2027-001', '2027-05-14', 3150000, 'Remote-area transport waiver 2027', 'Competitive sourcing is impractical for the remote field location and delivery window.', 'approved'],
            ];
            foreach ($waiverRows as [$year, $number, $date, $amount, $description, $justification, $status]) {
                $fyId = DB::table('fiscal_years')->where('year', $year)->value('id');
                $yearProject = DB::table('projects')->where('code', "PRJ-{$year}-FORD-01")->first() ?: $project;
                $yearPrId = DB::table('purchase_requests')->where('pr_number', "PR-{$year}-001")->value('id')
                    ?: DB::table('purchase_requests')->where('fiscal_year_id', $fyId)->value('id');
                $put('procurement_waivers', ['waiver_number' => $number], [
                    'fiscal_year_id' => $fyId, 'purchase_request_id' => $yearPrId,
                    'project_id' => $yearProject?->id, 'project_name' => $yearProject?->name ?? "Trial Programme {$year}",
                    'vendor_id' => $vendor?->id, 'vendor_name' => $vendor?->name ?? 'Trial Field Services Supplier',
                    'total_amount' => $amount, 'description' => $description, 'justification' => $justification,
                    'date' => $date, 'donor_name' => 'Ford Foundation',
                    'prepared_by' => $employee?->name ?? 'Trial Procurement Officer', 'prepared_title' => 'Procurement Officer',
                    'authorised_by' => $user->name, 'authorised_title' => 'Programme Manager',
                    'verified_by' => $user->name, 'verified_title' => 'Finance Manager',
                    'remarks' => 'Seeded waiver for end-to-end procurement trial.', 'status' => $status,
                    'approved_by' => $status === 'approved' ? $user->id : null,
                    'approved_at' => $status === 'approved' ? $now : null,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }
        }

        // Approval center history for multiple modules, not only expense.
        $targets = DB::table('expense_requests')->orderBy('id')->limit(3)->get();
        foreach ($targets as $i => $target) {
            $run = $put('approval_workflow_runs', ['module' => 'expense', 'approvable_type' => 'App\\Models\\Expense\\ExpenseRequest', 'approvable_id' => $target->id], [
                'status' => $i === 0 ? 'in_progress' : ($i === 1 ? 'approved' : 'rejected'), 'current_level' => $i === 0 ? 2 : 3,
                'submitted_by' => $user->id, 'completed_at' => $i === 0 ? null : $now,
            ]);
            if ($run) {
                $action = $put('approval_workflow_actions', ['approval_workflow_run_id' => $run, 'level' => 1], [
                    'approval_matrix_id' => $matrix?->id, 'role_id' => $role?->id, 'user_id' => $user->id,
                    'employee_id' => $employee?->id, 'status' => $i === 0 ? 'queued' : 'approved',
                    'action_by' => $i === 0 ? null : $user->id, 'action_at' => $i === 0 ? null : $now,
                    'notes' => $i === 2 ? 'Needs supporting document.' : 'Trial approval action.',
                ]);
                $put('approval_workflow_events', ['approval_workflow_run_id' => $run, 'event' => 'submitted', 'version' => 1], [
                    'approval_workflow_action_id' => $action, 'from_level' => null, 'to_level' => 1,
                    'actor_id' => $user->id, 'actor_name' => $user->name, 'actor_position' => 'Trial Approver',
                    'notes' => 'Submitted to approval center.', 'metadata' => json_encode(['seeded' => true]),
                ]);
            }
        }
    }
}
