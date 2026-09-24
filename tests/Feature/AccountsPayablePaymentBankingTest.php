<?php

namespace Tests\Feature;

use App\Models\Master\BankAccount;
use App\Models\Master\ApprovalMatrix;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Permission;
use App\Models\Finance\BankTransaction;
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

    public function test_supplier_invoice_uses_approval_matrix_when_configured(): void
    {
        [$user, $invoice] = $this->fixture();
        ApprovalMatrix::create(['module' => 'ap', 'level' => 1, 'min_amount' => 0, 'role_id' => $user->role_id, 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/finance/ap/invoices/{$invoice->id}/submit")
            ->assertOk()
            ->assertJsonPath('workflow_managed', true);

        $this->postJson('/api/v1/approval-center/batch-action', [
            'action' => 'approve',
            'notes' => 'Approved from finance queue',
            'items' => [['module' => 'ap', 'id' => $invoice->id]],
        ])
            ->assertOk()
            ->assertJsonPath('processed_count', 1);

        $this->assertDatabaseHas('supplier_invoices', ['id' => $invoice->id, 'status' => 'posted']);

        $this->assertDatabaseHas('approval_workflow_runs', ['module' => 'ap', 'approvable_id' => $invoice->id, 'status' => 'approved']);
    }

    public function test_ap_posting_cannot_bypass_configured_approval_matrix(): void
    {
        [$user, $invoice] = $this->fixture();
        ApprovalMatrix::create(['module' => 'ap', 'level' => 1, 'min_amount' => 0, 'role_id' => $user->role_id, 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/finance/ap/invoices/{$invoice->id}/post")
            ->assertUnprocessable();

        $this->assertDatabaseHas('supplier_invoices', ['id' => $invoice->id, 'status' => 'matched']);
    }

    public function test_unmatched_imported_bank_transaction_is_auto_matched_to_one_payment(): void
    {
        [$user, $invoice, $bank] = $this->fixture();
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/finance/ap/invoices/{$invoice->id}/post")->assertOk();
        $this->postJson("/api/v1/finance/ap/invoices/{$invoice->id}/payments", [
            'bank_account_id' => $bank->id,
            'payment_date' => '2026-09-23',
            'amount' => 200,
            'reference' => 'TRF-AUTO-001',
        ])->assertCreated();

        $statement = BankTransaction::create([
            'bank_account_id' => $bank->id,
            'created_by' => $user->id,
            'transaction_date' => '2026-09-24',
            'reference' => 'MUTASI-001',
            'credit' => 200,
            'debit' => 0,
            'status' => 'unmatched',
        ]);

        $this->getJson('/api/v1/finance/bank-transactions/exceptions')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', $statement->id)
            ->assertJsonPath('data.0.candidates.0.amount', 200)
            ->assertJsonPath('data.0.recommended_action', 'auto_match');

        $this->postJson("/api/v1/finance/bank-transactions/{$statement->id}/auto-match")
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('data.status', 'matched');

        $this->assertDatabaseHas('bank_transactions', ['id' => $statement->id, 'status' => 'matched']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'banking', 'action' => 'AUTO_MATCH', 'entity_id' => $statement->id]);

        $paymentId = $statement->fresh()->payment_id;
        $this->postJson("/api/v1/finance/bank-transactions/{$statement->id}/unmatch")
            ->assertOk()
            ->assertJsonPath('data.status', 'unmatched');
        $this->postJson("/api/v1/finance/bank-transactions/{$statement->id}/match", ['payment_id' => $paymentId])
            ->assertOk()
            ->assertJsonPath('data.status', 'matched');
        $this->assertDatabaseHas('audit_logs', ['module' => 'banking', 'action' => 'MATCH', 'entity_id' => $statement->id]);
    }

    public function test_reconcile_and_excluded_status_transitions_and_audit_logs(): void
    {
        [$user, $invoice, $bank] = $this->fixture();
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/finance/ap/invoices/{$invoice->id}/post")->assertOk();
        $this->postJson("/api/v1/finance/ap/invoices/{$invoice->id}/payments", [
            'bank_account_id' => $bank->id,
            'payment_date' => '2026-09-23',
            'amount' => 200,
            'reference' => 'TRF-REC-001',
        ])->assertCreated();

        $transaction = \App\Models\Finance\BankTransaction::where('bank_account_id', $bank->id)->first();

        $this->postJson("/api/v1/finance/bank-transactions/{$transaction->id}/reconcile", ['status' => 'reconciled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'reconciled');
        $this->assertDatabaseHas('bank_transactions', ['id' => $transaction->id, 'status' => 'reconciled']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'banking', 'action' => 'RECONCILE', 'entity_id' => $transaction->id]);

        $this->postJson("/api/v1/finance/bank-transactions/{$transaction->id}/unmatch")
            ->assertOk()
            ->assertJsonPath('data.status', 'unmatched');

        $this->postJson("/api/v1/finance/bank-transactions/{$transaction->id}/reconcile", ['status' => 'excluded'])
            ->assertOk()
            ->assertJsonPath('data.status', 'excluded');
        $this->assertDatabaseHas('audit_logs', ['module' => 'banking', 'action' => 'RECONCILE', 'entity_id' => $transaction->id]);
    }

    public function test_bank_transaction_actions_respect_owner_data_scope(): void
    {
        [$user, , $bank] = $this->fixture();
        $intruder = User::factory()->create(['role_id' => $user->role_id]);
        $other = User::factory()->create(['role_id' => $user->role_id]);

        $transaction = \App\Models\Finance\BankTransaction::create([
            'bank_account_id' => $bank->id,
            'created_by' => $other->id,
            'transaction_date' => '2026-09-24',
            'reference' => 'MUT-OWNED',
            'debit' => 0,
            'credit' => 100,
            'status' => 'unmatched',
        ]);

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/v1/finance/bank-transactions/{$transaction->id}/auto-match")
            ->assertForbidden();

        $this->getJson('/api/v1/finance/bank-transactions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_import_bank_statement_csv_creates_transactions_and_audit_log(): void
    {
        [$user, , $bank] = $this->fixture();
        $this->actingAs($user, 'sanctum');
        \Illuminate\Support\Facades\Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('statement.csv', "date,reference,description,debit,credit\n2026-09-24,MUT-001,Payment vendor,0,500000\n2026-09-25,,Transfer in,250000,0\n");

        $this->postJson('/api/v1/finance/bank-transactions/import', [
            'bank_account_id' => $bank->id,
            'file' => $file,
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('bank_transactions', ['bank_account_id' => $bank->id, 'reference' => 'MUT-001', 'credit' => 500000, 'status' => 'unmatched']);
        $this->assertDatabaseHas('bank_transactions', ['bank_account_id' => $bank->id, 'reference' => null, 'debit' => 250000]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'banking', 'action' => 'IMPORT', 'entity_type' => BankTransaction::class]);
    }

    public function test_import_preview_does_not_persist_rows(): void
    {
        [$user, , $bank] = $this->fixture();
        $this->actingAs($user, 'sanctum');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('statement.csv', "date,reference,description,debit,credit\n2026-09-24,MUT-PREVIEW,Preview row,0,500000\n");

        $this->postJson('/api/v1/finance/bank-transactions/import', [
            'bank_account_id' => $bank->id,
            'file' => $file,
            'preview' => true,
        ])
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('data.created', 1)
            ->assertJsonCount(1, 'data.rows');

        $this->assertDatabaseMissing('bank_transactions', ['bank_account_id' => $bank->id, 'reference' => 'MUT-PREVIEW']);
    }

    public function test_import_rejects_duplicates_and_reports_broken_rows(): void
    {
        [$user, , $bank] = $this->fixture();
        $this->actingAs($user, 'sanctum');
        \App\Models\Finance\BankTransaction::create([
            'bank_account_id' => $bank->id,
            'created_by' => $user->id,
            'transaction_date' => '2026-09-24',
            'reference' => 'MUT-DUP',
            'debit' => 0,
            'credit' => 100,
            'status' => 'unmatched',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('statement.csv', "date,reference,description,debit,credit\n2026-09-24,MUT-DUP,Dup row,0,100\nnot-a-date,MUT-BAD,Bad date,10,0\n2026-09-25,MUT-OK,New row,0,150\n");

        $this->postJson('/api/v1/finance/bank-transactions/import', [
            'bank_account_id' => $bank->id,
            'file' => $file,
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.duplicates', 1)
            ->assertJsonPath('data.failed', 1);
    }

    public function test_import_requires_banking_view_permission(): void
    {
        [$user, , $bank] = $this->fixture();
        $user->role->permissions()->detach();
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('statement.csv', "date,reference,description,debit,credit\n2026-09-24,MUT-NOPERM,No perm,0,500000\n");

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/bank-transactions/import', [
                'bank_account_id' => $bank->id,
                'file' => $file,
            ])
            ->assertForbidden();
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'AP Role', 'slug' => 'ap-role']);
        foreach (['ap.post', 'ap.pay', 'banking.view'] as $permission) {
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
