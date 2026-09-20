<?php

namespace Tests\Feature;

use App\Models\Master\BankAccount;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Permission;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\SupplierInvoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsPayablePaymentBankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_invoice_posts_to_ap_and_payment_creates_bank_transaction(): void
    {
        [$user, $invoice, $bank] = $this->fixture();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/finance/ap/invoices/{$invoice->id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $this->assertDatabaseHas('journals', [
            'reference' => 'INV-AP-001',
            'status' => 'posted',
        ]);

        $this->postJson("/api/v1/finance/ap/invoices/{$invoice->id}/payments", [
            'bank_account_id' => $bank->id,
            'payment_date' => '2026-09-23',
            'amount' => 200,
            'reference' => 'TRF-001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '200.00');

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'paid_amount' => 200,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $bank->id,
            'credit' => 200,
            'status' => 'matched',
        ]);
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'AP Role', 'slug' => 'ap-role']);
        foreach (['ap.post', 'ap.pay'] as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);

        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $cash = ChartOfAccount::create(['code' => '1000', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        ChartOfAccount::create(['code' => '2000', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $expense = ChartOfAccount::create(['code' => '5100', 'name' => 'Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Test Bank', 'account_number' => '001', 'account_name' => 'Main', 'currency_id' => $currency->id, 'gl_account_id' => $cash->id, 'is_active' => true]);
        $category = BudgetCategory::create(['code' => 'OPS', 'name' => 'Operations', 'is_active' => true]);
        $budgetLine = BudgetLine::create(['budget_category_id' => $category->id, 'line_code' => 'OPS-001', 'description' => 'Ops', 'total_amount' => 1000, 'gl_account_id' => $expense->id, 'is_active' => true]);
        $po = PurchaseOrder::create(['po_number' => 'PO-AP-001', 'po_date' => '2026-09-20', 'status' => 'approved']);
        $poLine = $po->lines()->create(['budget_line_id' => $budgetLine->id, 'item_description' => 'Materials', 'quantity' => 2, 'unit_price' => 100, 'total_amount' => 200, 'line_order' => 1]);
        $invoice = SupplierInvoice::create(['purchase_order_id' => $po->id, 'invoice_number' => 'INV-AP-001', 'invoice_date' => '2026-09-22', 'status' => 'matched', 'match_status' => 'matched', 'total_amount' => 200]);
        $invoice->lines()->create(['purchase_order_line_id' => $poLine->id, 'item_description' => 'Materials', 'quantity' => 2, 'unit_price' => 100, 'total_amount' => 200]);

        return [$user, $invoice, $bank];
    }
}
