<?php

namespace Tests\Feature;

use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsReceivableCustomerInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_invoice_posts_to_ar_and_receipt_creates_bank_transaction(): void
    {
        [$user, $customer, $revenue, $bank] = $this->fixture();

        $invoiceResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/ar/invoices', [
                'invoice_number' => 'AR-INV-001',
                'customer_id' => $customer->id,
                'invoice_date' => '2026-09-20',
                'due_date' => '2026-09-30',
                'description' => 'Grant receivable',
                'lines' => [
                    ['revenue_account_id' => $revenue->id, 'description' => 'Grant income', 'quantity' => 1, 'unit_price' => 500],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.total_amount', '500.00');

        $invoiceId = $invoiceResponse->json('data.id');

        $this->postJson("/api/v1/finance/ar/invoices/{$invoiceId}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $this->assertDatabaseHas('journals', [
            'reference' => 'AR-INV-001',
            'status' => 'posted',
        ]);

        $this->postJson("/api/v1/finance/ar/invoices/{$invoiceId}/receipts", [
            'bank_account_id' => $bank->id,
            'payment_date' => '2026-09-25',
            'amount' => 500,
            'reference' => 'RCPT-001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '500.00');

        $this->assertDatabaseHas('customer_invoices', [
            'id' => $invoiceId,
            'status' => 'received',
            'received_amount' => 500,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $bank->id,
            'debit' => 500,
            'status' => 'matched',
        ]);
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'AR Role', 'slug' => 'ar-role']);
        foreach (['ar.create', 'ar.post', 'ar.receive'] as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);

        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $cash = ChartOfAccount::create(['code' => '1000', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        ChartOfAccount::create(['code' => '1100', 'name' => 'Accounts Receivable', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $revenue = ChartOfAccount::create(['code' => '4100', 'name' => 'Grant Revenue', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Test Bank', 'account_number' => '001', 'account_name' => 'Main', 'currency_id' => $currency->id, 'gl_account_id' => $cash->id, 'is_active' => true]);
        $customer = Customer::create(['code' => 'CUST-001', 'name' => 'Grant Customer', 'is_active' => true]);

        return [$user, $customer, $revenue, $bank];
    }
}
