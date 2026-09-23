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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_request_posts_and_pays_with_mobile_reference(): void
    {
        [$user, $budgetLine, $category, $bank] = $this->fixture();

        $expenseId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/expenses/requests', [
                'external_request_id' => 'MOB-REQ-001',
                'expense_type' => 'reimbursement',
                'request_date' => '2026-09-20',
                'currency_code' => 'IDR',
                'description' => 'Mobile reimbursement',
                'attachments' => ['https://example.test/receipt-mob-req-001.pdf'],
                'lines' => [[
                    'expense_category_id' => $category->id,
                    'budget_line_id' => $budgetLine->id,
                    'description' => 'Transport',
                    'amount' => 150,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.external_request_id', 'MOB-REQ-001')
            ->json('data.id');

        $this->postJson("/api/v1/expenses/requests/{$expenseId}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->postJson("/api/v1/expenses/requests/{$expenseId}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/v1/expenses/requests/{$expenseId}/post")->assertOk()->assertJsonPath('data.status', 'posted');
        $this->postJson("/api/v1/expenses/requests/{$expenseId}/pay", [
            'bank_account_id' => $bank->id,
            'payment_date' => '2026-09-21',
            'amount' => 150,
            'reference' => 'EXP-PAY-001',
        ])->assertCreated()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('journals', ['reference' => 'MOB-REQ-001', 'status' => 'posted']);
        $this->assertDatabaseHas('bank_transactions', ['credit' => 150, 'status' => 'matched']);
    }

    public function test_required_receipt_can_be_uploaded_before_submission(): void
    {
        Storage::fake('public');
        [$user, $budgetLine, $category] = $this->fixture();

        $expenseId = $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses/requests', [
            'expense_type' => 'reimbursement',
            'request_date' => '2026-09-20',
            'description' => 'Receipt-controlled expense',
            'lines' => [['expense_category_id' => $category->id, 'budget_line_id' => $budgetLine->id, 'description' => 'Transport', 'amount' => 10]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/expenses/requests/{$expenseId}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments');

        $this->post("/api/v1/expenses/requests/{$expenseId}/attachments", [
            'file' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
        ])->assertCreated()->assertJsonCount(1, 'data.attachments');

        $this->postJson("/api/v1/expenses/requests/{$expenseId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'Expense Role', 'slug' => 'expense-role']);
        foreach (['expense.create', 'expense.submit', 'expense.approve', 'expense.post', 'expense.pay'] as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $cash = ChartOfAccount::create(['code' => '1000', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        ChartOfAccount::create(['code' => '2000', 'name' => 'Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $expenseAccount = ChartOfAccount::create(['code' => '5100', 'name' => 'Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Bank', 'account_number' => '001', 'account_name' => 'Main', 'currency_id' => $currency->id, 'gl_account_id' => $cash->id, 'is_active' => true]);
        $category = BudgetCategory::create(['code' => 'OPS', 'name' => 'Ops', 'is_active' => true]);
        $budgetLine = BudgetLine::create(['budget_category_id' => $category->id, 'line_code' => 'OPS-001', 'description' => 'Ops', 'total_amount' => 1000, 'gl_account_id' => $expenseAccount->id, 'is_active' => true]);
        $expenseCategory = ExpenseCategory::create(['code' => 'TRV', 'name' => 'Travel', 'default_gl_account_id' => $expenseAccount->id, 'is_active' => true]);

        return [$user, $budgetLine, $expenseCategory, $bank];
    }
}
