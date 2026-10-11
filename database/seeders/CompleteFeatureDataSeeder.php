<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills the secondary screens that are easy to miss when seeding only the
 * primary transaction chain. All writes are keyed by stable demo references.
 */
class CompleteFeatureDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $user = DB::table('users')->where('email', 'admin@kaoemtelapak.test')->first()
            ?? DB::table('users')->orderBy('id')->first();
        if (! $user) return;

        $fy = DB::table('fiscal_years')->where('year', 2026)->first();
        $project = DB::table('projects')->where('code', 'PRJ-2026-FORD-01')->first()
            ?? DB::table('projects')->orderBy('id')->first();
        $budgetLine = DB::table('budget_lines')->where('line_code', 'BL-FORD-1.1')->first()
            ?? DB::table('budget_lines')->orderBy('id')->first();
        $expense = DB::table('expense_requests')->orderBy('id')->first();
        $purchaseOrder = DB::table('purchase_orders')->orderBy('id')->first();
        $goodsReceipt = DB::table('goods_receipts')->orderBy('id')->first();
        $vendor = DB::table('vendors')->where('code', 'VND-HOTEL-01')->first()
            ?? DB::table('vendors')->orderBy('id')->first();
        $bank = DB::table('bank_accounts')->orderBy('id')->first();
        $employee = DB::table('employees')->orderBy('id')->first();
        $account = DB::table('chart_of_accounts')->orderBy('id')->first();

        $put = static function (string $table, array $keys, array $values) use ($now): ?int {
            if (! Schema::hasTable($table)) return null;
            $keyPayload = collect($keys)->filter(fn ($value, $column) => Schema::hasColumn($table, $column))->all();
            if (! $keyPayload) return null;
            $valuePayload = collect($values)->filter(fn ($value, $column) => Schema::hasColumn($table, $column))->all();
            $existing = DB::table($table)->where($keyPayload)->first();
            if ($existing) {
                $updates = $valuePayload;
                if (Schema::hasColumn($table, 'updated_at')) $updates['updated_at'] = $now;
                DB::table($table)->where($keyPayload)->update($updates);
                return isset($existing->id) ? (int) $existing->id : null;
            }
            $payload = array_merge($keyPayload, $valuePayload);
            if (Schema::hasColumn($table, 'created_at')) $payload['created_at'] = $now;
            if (Schema::hasColumn($table, 'updated_at')) $payload['updated_at'] = $now;
            return (int) DB::table($table)->insertGetId($payload);
        };

        // Settings and saved report builder examples.
        foreach ([
            ['key' => 'default_currency', 'value' => ['code' => 'IDR', 'name' => 'Indonesian Rupiah']],
            ['key' => 'approval_sla_days', 'value' => ['expense' => 3, 'procurement' => 5]],
            ['key' => 'fiscal_year_policy', 'value' => ['default_year' => 2026, 'allow_global_records' => true]],
        ] as $setting) {
            $put('application_settings', ['key' => $setting['key']], ['value' => json_encode($setting['value']), 'updated_by' => $user->id]);
        }
        $put('saved_reports', ['user_id' => $user->id, 'name' => 'Monthly Programme Finance'], [
            'configuration' => json_encode(['report_type' => 'financial', 'fiscal_year_id' => $fy?->id, 'group_by' => 'program']),
        ]);

        // Accounting recurring journal and its lines.
        if ($account) {
            $recurring = $put('recurring_journals', ['name' => 'Monthly Field Operations Accrual'], [
                'frequency' => 'monthly', 'next_run' => '2026-04-01', 'ends_at' => '2026-12-31',
                'description' => 'Accrual for recurring field programme operations.', 'is_active' => true,
            ]);
            if ($recurring) {
                $put('recurring_journal_lines', ['recurring_journal_id' => $recurring, 'line_order' => 1], [
                    'account_id' => $account->id, 'description' => 'Monthly programme accrual', 'debit' => 2500000, 'credit' => 0,
                ]);
            }
        }

        // Budget reallocation queue.
        if ($project && $budgetLine) {
            $put('budget_reallocations', ['reference' => 'REALLOC-2026-001'], [
                'project_id' => $project->id, 'from_budget_line_id' => $budgetLine->id, 'to_budget_line_id' => $budgetLine->id,
                'amount' => 500000, 'reason' => 'Move unused workshop allocation to community facilitation.',
                'status' => 'submitted', 'submitted_by' => $user->id, 'submitted_at' => $now, 'created_by' => $user->id,
            ]);
        }

        // Procurement amendment and vendor evaluation screens.
        if ($purchaseOrder && $vendor) {
            $put('po_amendments', ['amendment_number' => 'POA-2026-001'], [
                'purchase_order_id' => $purchaseOrder->id, 'version' => 1,
                'changes' => json_encode(['delivery_date' => ['old' => '2026-03-20', 'new' => '2026-03-25']]),
                'reason' => 'Delivery schedule adjusted by supplier.', 'status' => 'pending_approval', 'created_by' => $user->id,
            ]);
            $put('vendor_evaluations', ['vendor_id' => $vendor->id, 'purchase_order_id' => $purchaseOrder->id], [
                'goods_receipt_id' => $goodsReceipt?->id, 'quality_score' => 4, 'delivery_score' => 4,
                'price_score' => 5, 'service_score' => 4, 'overall_score' => 4.3,
                'evaluator_notes' => 'Supplier delivered according to specification.', 'evaluated_by' => $user->id,
            ]);
        }

        // Cash advance return and additional reimbursement flows.
        if ($expense) {
            $returnId = $put('cash_advance_returns', ['reference_no' => 'CAR-2026-001'], [
                'expense_request_id' => $expense->id, 'employee_id' => $employee?->id, 'bank_account_id' => $bank?->id,
                'return_amount' => 750000, 'payment_method' => 'bank_transfer', 'return_date' => '2026-04-10',
                'status' => 'received', 'notes' => 'Unused cash advance returned after field activity.', 'created_by' => $user->id,
            ]);
            $put('cash_advance_reimbursements', ['expense_request_id' => $expense->id, 'amount' => 325000], [
                'employee_id' => $employee?->id, 'project_id' => $project?->id, 'budget_line_id' => $budgetLine?->id,
                'status' => 'pending', 'notes' => 'Additional eligible expense from settlement.', 'created_by' => $user->id,
            ]);
            if ($returnId && Schema::hasTable('bank_transactions') && $bank) {
                $put('bank_transactions', ['reference' => 'CAR-2026-001'], [
                    'bank_account_id' => $bank->id, 'cash_advance_return_id' => $returnId,
                    'transaction_date' => '2026-04-10', 'description' => 'Cash advance return CAR-2026-001',
                    'debit' => 0, 'credit' => 750000, 'status' => 'reconciled', 'created_by' => $user->id,
                ]);
            }
        }
    }
}
