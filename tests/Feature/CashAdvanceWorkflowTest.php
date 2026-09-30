<?php

namespace Tests\Feature;

use App\Models\Master\BankAccount;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAdvanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_advance_exact_settlement_is_balanced(): void
    {
        [$user, $bank, $line, $category] = $this->fixture();
        $id = $this->createAndApprove($user, $line, $category, 1000);
        \DB::table('expense_requests')->where('id', $id)->update(['currency_code' => 'USD', 'exchange_rate' => 15000]);
        $this->postJson("/api/v1/expenses/requests/{$id}/post")->assertOk();
        $this->postJson("/api/v1/expenses/requests/{$id}/pay", ['bank_account_id' => $bank->id, 'payment_date' => '2026-09-21', 'amount' => 1000, 'exchange_rate' => 15000])->assertCreated();
        $this->postJson("/api/v1/expenses/requests/{$id}/settle", ['actual_expense_amount' => 1000, 'exchange_rate' => 15500])->assertOk()->assertJsonPath('data.settlement_state', 'settled');
        $this->assertDatabaseHas('journals', ['journal_type' => 'manual', 'reference' => 'CA-TEST-1000']);
        $this->assertDatabaseHas('bank_transactions', ['credit' => 1000, 'status' => 'matched']);
        $this->assertEquals(0, (float) \DB::table('expense_requests')->where('id', $id)->value('return_amount'));
        $this->assertDatabaseHas('journals', ['source_type' => 'cash_advance_settlement', 'exchange_rate' => 15500, 'converted_amount' => 15500000]);
    }

    public function test_partial_settlement_requires_return_and_closes_after_return(): void
    {
        [$user, $bank, $line, $category] = $this->fixture();
        $id = $this->createAndApprove($user, $line, $category, 1000);
        $this->postJson("/api/v1/expenses/requests/{$id}/post");
        $this->postJson("/api/v1/expenses/requests/{$id}/pay", ['bank_account_id' => $bank->id, 'payment_date' => '2026-09-21', 'amount' => 1000]);
        $this->postJson("/api/v1/expenses/requests/{$id}/settle", ['actual_expense_amount' => 300, 'finalize' => false])->assertOk()->assertJsonPath('data.settlement_state', 'partially_settled');
        $this->postJson("/api/v1/expenses/requests/{$id}/settle", ['actual_expense_amount' => 800, 'finalize' => true])->assertOk()->assertJsonPath('data.settlement_state', 'awaiting_return');
        $this->postJson("/api/v1/expenses/requests/{$id}/return", ['bank_account_id' => $bank->id, 'return_date' => '2026-09-25', 'amount' => 200])->assertCreated()->assertJsonPath('data.settlement_state', 'settled');
        $this->assertDatabaseHas('cash_advance_returns', ['expense_request_id' => $id, 'return_amount' => 200]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'cash-advance-returns', 'action' => 'CREATE']);
    }

    public function test_user_without_payment_permission_cannot_pay_cash_advance(): void
    {
        [$owner, $bank, $line, $category] = $this->fixture();
        $id = $this->createAndApprove($owner, $line, $category, 1000);
        $unauthorizedRole = Role::create(['name' => 'View Only', 'slug' => 'view-only']);
        $unauthorizedRole->permissions()->attach(Permission::where('slug', 'expense.view')->first());
        $unauthorized = User::factory()->create(['role_id' => $unauthorizedRole->id]);
        $this->actingAs($unauthorized, 'sanctum')->postJson("/api/v1/expenses/requests/{$id}/pay", ['bank_account_id' => $bank->id, 'payment_date' => '2026-09-21', 'amount' => 1000])->assertForbidden();
        $this->postJson("/api/v1/expenses/requests/{$id}/approve")->assertForbidden();
        $this->postJson("/api/v1/expenses/requests/{$id}/settle", ['actual_expense_amount' => 1000])->assertForbidden();
        $this->postJson("/api/v1/expenses/requests/{$id}/return", ['bank_account_id' => $bank->id, 'return_date' => '2026-09-21', 'amount' => 1])->assertForbidden();
        $this->postJson("/api/v1/expenses/requests/{$id}/reimbursement/pay", ['bank_account_id' => $bank->id, 'payment_date' => '2026-09-21'])->assertForbidden();
    }

    public function test_actual_above_advance_creates_and_pays_reimbursement(): void
    {
        [$user, $bank, $line, $category] = $this->fixture();
        $id = $this->createAndApprove($user, $line, $category, 1000);
        $this->postJson("/api/v1/expenses/requests/{$id}/post");
        $this->postJson("/api/v1/expenses/requests/{$id}/pay", ['bank_account_id' => $bank->id, 'payment_date' => '2026-09-21', 'amount' => 1000]);
        $this->postJson("/api/v1/expenses/requests/{$id}/settle", ['actual_expense_amount' => 1200])->assertOk()->assertJsonPath('data.settlement_state', 'awaiting_reimbursement');
        $this->postJson("/api/v1/expenses/requests/{$id}/reimbursement/pay", ['bank_account_id' => $bank->id, 'payment_date' => '2026-09-25'])->assertCreated()->assertJsonPath('data.settlement_state', 'settled');
        $this->assertDatabaseHas('cash_advance_reimbursements', ['expense_request_id' => $id, 'amount' => 200, 'status' => 'paid']);
    }

    private function createAndApprove(User $user, BudgetLine $line, ExpenseCategory $category, float $amount): int
    {
        $id = $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses/requests', ['request_number' => 'CA-TEST-1000', 'expense_type' => 'cash_advance', 'request_date' => '2026-09-20', 'description' => 'Cash Advance test', 'attachments' => ['receipt.pdf'], 'lines' => [['expense_category_id' => $category->id, 'budget_line_id' => $line->id, 'description' => 'Advance', 'amount' => $amount]]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/expenses/requests/{$id}/submit")->assertJsonPath('data.status', 'submitted');
        $this->postJson("/api/v1/expenses/requests/{$id}/verify")->assertJsonPath('data.status', 'verified');
        $this->postJson("/api/v1/expenses/requests/{$id}/approve")->assertJsonPath('data.status', 'approved');
        return $id;
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'CA Role', 'slug' => 'ca-role']);
        foreach (['expense.view', 'expense.create', 'expense.submit', 'expense.verify', 'expense.approve', 'expense.post', 'expense.pay'] as $permission) $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        $user = User::factory()->create(['role_id' => $role->id]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $cash = ChartOfAccount::create(['code' => '1000', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        ChartOfAccount::create(['code' => '11500', 'name' => 'Employee Advances', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        ChartOfAccount::create(['code' => '2000', 'name' => 'Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $expense = ChartOfAccount::create(['code' => '5100', 'name' => 'Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Bank', 'account_number' => '001', 'account_name' => 'Main', 'currency_id' => $currency->id, 'gl_account_id' => $cash->id, 'is_active' => true]);
        $budget = BudgetCategory::create(['code' => 'OPS', 'name' => 'Ops', 'is_active' => true]);
        $line = BudgetLine::create(['budget_category_id' => $budget->id, 'line_code' => 'OPS-001', 'description' => 'Ops', 'total_amount' => 10000, 'gl_account_id' => $expense->id, 'is_active' => true]);
        $category = ExpenseCategory::create(['code' => 'TRV', 'name' => 'Travel', 'default_gl_account_id' => $expense->id, 'is_active' => true]);
        return [$user, $bank, $line, $category];
    }
}
