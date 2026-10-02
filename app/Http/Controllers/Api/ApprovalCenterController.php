<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Accounting\JournalController;
use App\Http\Controllers\Api\Asset\FixedAssetController;
use App\Http\Controllers\Api\Budget\BudgetReallocationController;
use App\Http\Controllers\Api\Master\AccountingPeriodController;
use App\Http\Controllers\Api\Expense\ExpenseRequestController;
use App\Http\Controllers\Api\Finance\AccountsPayableController;
use App\Http\Controllers\Api\Finance\AccountsReceivableController;
use App\Http\Controllers\Api\Procurement\AdvancedProcurementController;
use App\Http\Controllers\Api\Procurement\ProcurementFulfillmentController;
use App\Http\Controllers\Api\Procurement\PurchaseRequestController;
use App\Http\Controllers\Api\Procurement\SupplierContractNotificationController;
use App\Http\Controllers\Api\Timesheet\TimesheetEntryController;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\ApprovalWorkflowAction;
use App\Models\ApprovalWorkflowRun;
use App\Models\Asset\FixedAsset;
use App\Models\Expense\ExpenseRequest;
use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\BankTransaction;
use App\Models\Master\AccountingPeriod;
use App\Models\Budget\BudgetReallocation;
use App\Models\Finance\TaxTransaction;
use App\Models\Procurement\ComparativeBidAnalysis;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\SupplierContractNotification;
use App\Models\Procurement\SupplierInvoice;
use App\Models\ProjectAssignment;
use App\Models\Timesheet\TimesheetEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalCenterController extends Controller
{
    /**
     * Get pending items needing approval across all ERP modules.
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $roleSlug = strtolower((string) ($user->role?->slug ?? ''));
        if (in_array($roleSlug, ['super-admin', 'super_admin', 'superadmin'], true) || (bool) ($user->is_super_admin ?? false)) {
            return response()->json(['success' => true, 'data' => [], 'message' => 'Super Admin tidak memerlukan approval.']);
        }
        $moduleFilter = $request->query('module');
        $items = collect();

        // 1. Expense Requests
        if (! $moduleFilter || $moduleFilter === 'expense') {
            if ($user->hasAnyPermission(['expense.approve', 'expenses.approve', 'expenses-approvals.view'])) {
                $expenses = ExpenseRequest::with(['requester', 'project', 'department'])
                    ->where('status', 'submitted')
                    ->latest('id')
                    ->get()
                    ->map(fn (ExpenseRequest $exp) => [
                        'id' => $exp->id,
                        'module' => 'expense',
                        'module_label' => 'Expense Request',
                        'reference_number' => $exp->request_number ?? 'EXP-'.$exp->id,
                        'request_date' => $exp->request_date?->toDateString() ?? $exp->created_at?->toDateString(),
                        'title_summary' => $exp->description ?? 'Pengajuan klaim expense',
                        'amount' => (float) $exp->total_amount,
                        'currency' => 'IDR',
                        'requester_name' => $exp->requester?->name ?? 'User',
                        'department_name' => $exp->department?->name,
                        'project_name' => $exp->project?->name,
                        'status' => $exp->status,
                        'created_at' => $exp->created_at?->toISOString(),
                    ]);
                $items = $items->concat($expenses);
            }
        }

        // 2. Purchase Requests
        if (! $moduleFilter || $moduleFilter === 'pr') {
            if ($user->hasAnyPermission(['procurement.pr.approve'])) {
                $prs = PurchaseRequest::with(['requester', 'project', 'department'])
                    ->where('status', 'submitted')
                    ->latest('id')
                    ->get()
                    ->map(fn (PurchaseRequest $pr) => [
                        'id' => $pr->id,
                        'module' => 'pr',
                        'module_label' => 'Purchase Request',
                        'reference_number' => $pr->pr_number,
                        'request_date' => $pr->request_date?->toDateString(),
                        'title_summary' => $pr->justification ?? 'Permintaan Pembelian Barang/Jasa',
                        'amount' => (float) $pr->total_amount,
                        'currency' => 'IDR',
                        'requester_name' => $pr->requester?->name ?? 'System User',
                        'department_name' => $pr->department?->name,
                        'project_name' => $pr->project?->name,
                        'status' => $pr->status,
                        'created_at' => $pr->created_at?->toISOString(),
                    ]);
                $items = $items->concat($prs);
            }
        }

        // 3. Purchase Orders
        if (! $moduleFilter || $moduleFilter === 'po') {
            if ($user->hasAnyPermission(['procurement.po.approve'])) {
                $pos = PurchaseOrder::with(['vendor', 'project'])
                    ->whereIn('status', ['draft', 'submitted'])
                    ->latest('id')
                    ->get()
                    ->map(fn (PurchaseOrder $po) => [
                        'id' => $po->id,
                        'module' => 'po',
                        'module_label' => 'Purchase Order',
                        'reference_number' => $po->po_number,
                        'request_date' => $po->po_date?->toDateString(),
                        'title_summary' => 'Pesanan Pembelian ke Supplier: '.($po->vendor?->name ?? '-'),
                        'amount' => (float) $po->total_amount,
                        'currency' => 'IDR',
                        'requester_name' => 'Procurement Officer',
                        'department_name' => null,
                        'project_name' => $po->project?->name,
                        'status' => $po->status,
                        'created_at' => $po->created_at?->toISOString(),
                    ]);
                $items = $items->concat($pos);
            }
        }

        // 4. CBA (Comparative Bid Analysis)
        if (! $moduleFilter || $moduleFilter === 'cba') {
            if ($user->hasAnyPermission(['procurement.cba.approve'])) {
                $cbas = ComparativeBidAnalysis::with(['rfq', 'selectedVendor'])
                    ->where('status', 'submitted')
                    ->latest('id')
                    ->get()
                    ->map(fn (ComparativeBidAnalysis $cba) => [
                        'id' => $cba->id,
                        'module' => 'cba',
                        'module_label' => 'Comparative Bid Analysis',
                        'reference_number' => $cba->cba_number,
                        'request_date' => $cba->cba_date?->toDateString(),
                        'title_summary' => 'Analisis Perbandingan Penawaran Vendor',
                        'amount' => (float) $cba->selected_amount,
                        'currency' => 'IDR',
                        'requester_name' => 'Procurement Team',
                        'department_name' => null,
                        'project_name' => null,
                        'status' => $cba->status,
                        'created_at' => $cba->created_at?->toISOString(),
                    ]);
                $items = $items->concat($cbas);
            }
        }

        // 5. Supplier Contract Notification (SCN)
        if (! $moduleFilter || $moduleFilter === 'scn') {
            if ($user->hasAnyPermission(['procurement.pr.approve'])) {
                $scns = SupplierContractNotification::with(['vendor'])
                    ->whereIn('status', ['draft', 'submitted'])
                    ->latest('id')
                    ->get()
                    ->map(fn (SupplierContractNotification $scn) => [
                        'id' => $scn->id,
                        'module' => 'scn',
                        'module_label' => 'Supplier Contract Notification',
                        'reference_number' => $scn->scn_number,
                        'request_date' => $scn->issued_date?->toDateString() ?? $scn->created_at?->toDateString(),
                        'title_summary' => 'Pemberitahuan Kontrak Vendor: '.($scn->vendor?->name ?? '-'),
                        'amount' => (float) $scn->contract_value,
                        'currency' => 'IDR',
                        'requester_name' => 'Procurement Specialist',
                        'department_name' => null,
                        'project_name' => null,
                        'status' => $scn->status,
                        'created_at' => $scn->created_at?->toISOString(),
                    ]);
                $items = $items->concat($scns);
            }
        }

        // 6. Journal Entries
        if (! $moduleFilter || $moduleFilter === 'journal') {
            if ($user->hasAnyPermission(['accounting.journal.review', 'accounting.journal.post'])) {
                $journals = Journal::whereIn('status', ['submitted', 'reviewed'])
                    ->latest('id')
                    ->get()
                    ->map(fn (Journal $j) => [
                        'id' => $j->id,
                        'module' => 'journal',
                        'module_label' => 'Jurnal Umum (GL)',
                        'reference_number' => $j->journal_number,
                        'request_date' => $j->journal_date?->toDateString(),
                        'title_summary' => $j->description ?? 'Pengajuan Jurnal Akuntansi',
                        'amount' => (float) $j->lines()->sum('debit'),
                        'currency' => 'IDR',
                        'requester_name' => 'Accounting Officer',
                        'department_name' => null,
                        'project_name' => null,
                        'status' => $j->status,
                        'created_at' => $j->created_at?->toISOString(),
                    ]);
                $items = $items->concat($journals);
            }
        }

        // 7. Accounts Payable (AP) Supplier Invoices
        if (! $moduleFilter || $moduleFilter === 'ap') {
            if ($user->hasAnyPermission(['ap.post', 'ap.pay'])) {
                $invoices = SupplierInvoice::with(['vendor'])
                    ->whereIn('status', ['draft', 'unposted', 'matched'])
                    ->latest('id')
                    ->get()
                    ->map(fn (SupplierInvoice $inv) => [
                        'id' => $inv->id,
                        'module' => 'ap',
                        'module_label' => 'Hutang Usaha (AP Invoice)',
                        'reference_number' => $inv->invoice_number,
                        'request_date' => $inv->invoice_date?->toDateString(),
                        'title_summary' => 'Persetujuan AP Invoice Vendor: '.($inv->vendor?->name ?? '-'),
                        'amount' => (float) $inv->total_amount,
                        'currency' => 'IDR',
                        'requester_name' => 'Finance Staff',
                        'department_name' => null,
                        'project_name' => null,
                        'status' => $inv->status,
                        'created_at' => $inv->created_at?->toISOString(),
                    ]);
                $items = $items->concat($invoices);
            }
        }

        // 8. Accounts Receivable (AR) Customer Invoices
        if (! $moduleFilter || $moduleFilter === 'ar') {
            if ($user->hasAnyPermission(['ar.post', 'ar.receive'])) {
                $arInvoices = CustomerInvoice::with(['customer'])
                    ->where('status', 'draft')
                    ->latest('id')
                    ->get()
                    ->map(fn (CustomerInvoice $inv) => [
                        'id' => $inv->id,
                        'module' => 'ar',
                        'module_label' => 'Piutang Usaha (AR Invoice)',
                        'reference_number' => $inv->invoice_number,
                        'request_date' => $inv->invoice_date?->toDateString(),
                        'title_summary' => 'Penagihan ke Customer: '.($inv->customer?->name ?? '-'),
                        'amount' => (float) $inv->total_amount,
                        'currency' => 'IDR',
                        'requester_name' => 'Finance Staff',
                        'department_name' => null,
                        'project_name' => null,
                        'status' => $inv->status,
                        'created_at' => $inv->created_at?->toISOString(),
                    ]);
                $items = $items->concat($arInvoices);
            }
        }

        // 9. Timesheets
        if (! $moduleFilter || $moduleFilter === 'timesheet') {
            if ($user->hasAnyPermission(['timesheet.approve'])) {
                $canViewAllTimesheets = $user->hasPermission('timesheet.view_all');
                $canViewTeamTimesheets = $user->hasPermission('timesheet.team.view');
                $tsQuery = TimesheetEntry::with(['employee', 'project'])->where('worker_type', 'internal')->where('status', 'submitted');
                if (! $canViewAllTimesheets && $canViewTeamTimesheets) {
                    $projectIds = ProjectAssignment::where('user_id', $user->id)->where('is_active', true)->pluck('project_id');
                    $tsQuery->where(function ($scope) use ($user, $projectIds) {
                        $scope->where('supervisor_id', $user->id);
                        if ($projectIds->isNotEmpty()) $scope->orWhereIn('project_id', $projectIds);
                    });
                } elseif (! $canViewAllTimesheets) {
                    $tsQuery->where('user_id', $user->id);
                }
                $ts = $tsQuery
                    ->latest('id')
                    ->get()
                    ->map(fn (TimesheetEntry $t) => [
                        'id' => $t->id,
                        'module' => 'timesheet',
                        'module_label' => 'Timesheet Pegawai',
                        'reference_number' => 'TS-'.$t->id,
                        'request_date' => $t->entry_date?->toDateString(),
                        'title_summary' => 'Presensi/Jam Kerja: '.$t->description.' ('.$t->hours.' jam)',
                        'amount' => (float) $t->hours,
                        'currency' => 'Jam',
                        'requester_name' => $t->employee?->name ?? 'Staff',
                        'department_name' => null,
                        'project_name' => $t->project?->name,
                        'status' => $t->status,
                        'created_at' => $t->created_at?->toISOString(),
                    ]);
                $items = $items->concat($ts);
            }
        }

        // 9b. Tax transactions awaiting tax filing/report approval
        if (! $moduleFilter || $moduleFilter === 'tax_transaction') {
            if ($user->hasAnyPermission(['tax.manage', 'tax.approve'])) {
                $taxIds = ApprovalWorkflowRun::where('module', 'tax_transaction')->where('status', 'in_progress')->pluck('approvable_id');
                $taxes = TaxTransaction::where('status', 'draft')->whereIn('id', $taxIds)->latest('id')->get()->map(fn (TaxTransaction $tax) => [
                    'id' => $tax->id, 'module' => 'tax_transaction', 'module_label' => 'Transaksi Pajak',
                    'reference_number' => $tax->reference ?? 'TAX-'.$tax->id, 'request_date' => $tax->transaction_date?->toDateString() ?? $tax->created_at?->toDateString(),
                    'title_summary' => 'Pelaporan transaksi pajak', 'amount' => (float) $tax->tax_amount, 'currency' => 'IDR',
                    'requester_name' => 'Tax Staff', 'department_name' => null, 'project_name' => null, 'status' => $tax->status, 'created_at' => $tax->created_at?->toISOString(),
                ]);
                $items = $items->concat($taxes);
            }
        }

        // 9c. Bank reconciliation requests that already have an approval run
        if (! $moduleFilter || $moduleFilter === 'bank_reconciliation') {
            if ($user->hasAnyPermission(['banking.view', 'banking.reconcile'])) {
                $ids = ApprovalWorkflowRun::where('module', 'bank_reconciliation')->where('status', 'in_progress')->pluck('approvable_id');
                $bankRows = BankTransaction::with('bankAccount')->whereIn('id', $ids)->latest('id')->get()->map(fn (BankTransaction $bank) => [
                    'id' => $bank->id, 'module' => 'bank_reconciliation', 'module_label' => 'Rekonsiliasi Bank',
                    'reference_number' => $bank->reference ?? 'BANK-'.$bank->id, 'request_date' => $bank->transaction_date?->toDateString(),
                    'title_summary' => $bank->description ?? 'Rekonsiliasi transaksi bank', 'amount' => (float) $bank->debit + (float) $bank->credit, 'currency' => 'IDR',
                    'requester_name' => 'Banking Staff', 'department_name' => null, 'project_name' => null, 'status' => $bank->status, 'created_at' => $bank->created_at?->toISOString(),
                ]);
                $items = $items->concat($bankRows);
            }
        }

        // 9d. Cash advance settlements
        if (! $moduleFilter || $moduleFilter === 'settlement') {
            if ($user->hasAnyPermission(['expense.submit', 'expense.approve'])) {
                $settlements = ExpenseRequest::where('expense_type', 'cash_advance')->whereIn('status', ['posted', 'paid'])->whereIn('id', ApprovalWorkflowRun::where('module', 'settlement')->where('status', 'in_progress')->pluck('approvable_id'))->latest('id')->get()->map(fn (ExpenseRequest $exp) => [
                    'id' => $exp->id, 'module' => 'settlement', 'module_label' => 'Settlement Cash Advance',
                    'reference_number' => $exp->request_number ?? 'SET-'.$exp->id, 'request_date' => $exp->created_at?->toDateString(),
                    'title_summary' => 'Settlement cash advance', 'amount' => (float) $exp->paid_amount, 'currency' => 'IDR',
                    'requester_name' => 'Finance Staff', 'department_name' => null, 'project_name' => null, 'status' => $exp->settlement_status ?? $exp->status, 'created_at' => $exp->created_at?->toISOString(),
                ]);
                $items = $items->concat($settlements);
            }
        }

        // 9e. Period closing and budget reallocations
        if (! $moduleFilter || $moduleFilter === 'period_closing') {
            if ($user->hasAnyPermission(['settings.manage', 'accounting.period.close'])) {
                $ids = ApprovalWorkflowRun::where('module', 'period_closing')->where('status', 'in_progress')->pluck('approvable_id');
                $periods = AccountingPeriod::whereIn('id', $ids)->latest('id')->get()->map(fn (AccountingPeriod $period) => [
                    'id' => $period->id, 'module' => 'period_closing', 'module_label' => 'Penutupan Periode', 'reference_number' => $period->name ?? 'PERIOD-'.$period->id,
                    'request_date' => $period->end_date?->toDateString(), 'title_summary' => 'Penutupan periode akuntansi', 'amount' => 0, 'currency' => 'IDR', 'requester_name' => 'Accounting Staff', 'department_name' => null, 'project_name' => null, 'status' => $period->status, 'created_at' => $period->created_at?->toISOString(),
                ]);
                $items = $items->concat($periods);
            }
        }

        // 9f. Payments (AP and expense) use the same approval module.
        if (! $moduleFilter || $moduleFilter === 'payment') {
            if ($user->hasAnyPermission(['ap.pay', 'expense.pay', 'expense.approve'])) {
                $paymentRunIds = ApprovalWorkflowRun::where('module', 'payment')->where('status', 'in_progress')->get(['approvable_type', 'approvable_id']);
                $apIds = $paymentRunIds->where('approvable_type', SupplierInvoice::class)->pluck('approvable_id');
                $expenseIds = $paymentRunIds->where('approvable_type', ExpenseRequest::class)->pluck('approvable_id');
                $apPayments = SupplierInvoice::whereIn('id', $apIds)->latest('id')->get()->map(fn (SupplierInvoice $inv) => [
                    'id' => $inv->id, 'module' => 'payment', 'entity_type' => SupplierInvoice::class, 'module_label' => 'Pembayaran AP', 'reference_number' => $inv->invoice_number,
                    'request_date' => $inv->invoice_date?->toDateString(), 'title_summary' => 'Pembayaran invoice supplier', 'amount' => (float) $inv->total_amount, 'currency' => 'IDR', 'requester_name' => 'Finance Staff', 'department_name' => null, 'project_name' => null, 'status' => $inv->status, 'created_at' => $inv->created_at?->toISOString(),
                ]);
                $expensePayments = ExpenseRequest::whereIn('id', $expenseIds)->latest('id')->get()->map(fn (ExpenseRequest $exp) => [
                    'id' => $exp->id, 'module' => 'payment', 'entity_type' => ExpenseRequest::class, 'module_label' => 'Pembayaran Expense', 'reference_number' => $exp->request_number ?? 'EXP-'.$exp->id,
                    'request_date' => $exp->request_date?->toDateString(), 'title_summary' => 'Pembayaran pengeluaran', 'amount' => (float) $exp->total_amount, 'currency' => 'IDR', 'requester_name' => 'Finance Staff', 'department_name' => null, 'project_name' => null, 'status' => $exp->status, 'created_at' => $exp->created_at?->toISOString(),
                ]);
                $items = $items->concat($apPayments)->concat($expensePayments);
            }
        }
        if (! $moduleFilter || $moduleFilter === 'budget_reallocation') {
            if ($user->hasAnyPermission(['budget.approve'])) {
                $rows = BudgetReallocation::where('status', 'submitted')->latest('id')->get()->map(fn (BudgetReallocation $row) => [
                    'id' => $row->id, 'module' => 'budget_reallocation', 'module_label' => 'Realokasi Anggaran', 'reference_number' => $row->reference ?? 'BR-'.$row->id,
                    'request_date' => $row->submitted_at?->toDateString() ?? $row->created_at?->toDateString(), 'title_summary' => $row->reason, 'amount' => (float) $row->amount, 'currency' => 'IDR', 'requester_name' => 'Budget Staff', 'department_name' => null, 'project_name' => null, 'status' => $row->status, 'created_at' => $row->created_at?->toISOString(),
                ]);
                $items = $items->concat($rows);
            }
        }

        // 10. Fixed Assets
        if (! $moduleFilter || $moduleFilter === 'asset') {
            if ($user->hasAnyPermission(['asset.capitalize'])) {
                $assets = FixedAsset::with(['category', 'project'])
                    ->where('status', 'draft')
                    ->latest('id')
                    ->get()
                    ->map(fn (FixedAsset $a) => [
                        'id' => $a->id,
                        'module' => 'asset',
                        'module_label' => 'Fixed Asset Capitalization',
                        'reference_number' => $a->asset_code,
                        'request_date' => $a->acquisition_date?->toDateString(),
                        'title_summary' => 'Kapitalisasi Aset Tetap: '.$a->asset_name,
                        'amount' => (float) $a->acquisition_cost,
                        'currency' => 'IDR',
                        'requester_name' => 'Asset Custodian',
                        'department_name' => null,
                        'project_name' => $a->project?->name,
                        'status' => $a->status,
                        'created_at' => $a->created_at?->toISOString(),
                    ]);
                $items = $items->concat($assets);
            }
        }

        // 11. Fixed asset disposals
        if (! $moduleFilter || $moduleFilter === 'asset_disposal') {
            if ($user->hasAnyPermission(['asset.dispose'])) {
                $disposalIds = ApprovalWorkflowRun::where('module', 'asset_disposal')->where('status', 'in_progress')->pluck('approvable_id');
                $disposals = FixedAsset::with(['category', 'project'])
                    ->whereIn('status', ['active', 'transferred'])
                    ->whereNotNull('disposal_requested_date')
                    ->whereIn('id', $disposalIds)
                    ->latest('id')
                    ->get()
                    ->map(fn (FixedAsset $a) => [
                        'id' => $a->id,
                        'module' => 'asset_disposal',
                        'module_label' => 'Fixed Asset Disposal',
                        'reference_number' => $a->asset_code,
                        'request_date' => $a->disposal_requested_date?->toDateString(),
                        'title_summary' => 'Penghapusan Aset: '.$a->asset_name,
                        'amount' => (float) $a->net_book_value,
                        'currency' => 'IDR',
                        'requester_name' => 'Asset Custodian',
                        'department_name' => null,
                        'project_name' => $a->project?->name,
                        'status' => 'submitted',
                        'created_at' => $a->created_at?->toISOString(),
                    ]);
                $items = $items->concat($disposals);
            }
        }

        // A document with a configured workflow is visible only to the
        // approver assigned to its current pending level. Legacy documents
        // without a workflow remain visible through the existing permission
        // rules so historical processes are not hidden.
        $moduleMap = ['expense' => 'expense', 'pr' => 'procurement', 'po' => 'po', 'cba' => 'cba', 'scn' => 'scn', 'journal' => 'journal', 'ap' => 'ap', 'ar' => 'ar', 'timesheet' => 'timesheet', 'asset' => 'asset', 'asset_disposal' => 'asset_disposal', 'tax_transaction' => 'tax_transaction', 'bank_reconciliation' => 'bank_reconciliation', 'settlement' => 'settlement', 'period_closing' => 'period_closing', 'budget_reallocation' => 'budget_reallocation', 'payment' => 'payment'];
        $typeMap = ['expense' => ExpenseRequest::class, 'pr' => PurchaseRequest::class, 'po' => PurchaseOrder::class, 'cba' => ComparativeBidAnalysis::class, 'scn' => SupplierContractNotification::class, 'journal' => Journal::class, 'ap' => SupplierInvoice::class, 'ar' => CustomerInvoice::class, 'timesheet' => TimesheetEntry::class, 'asset' => FixedAsset::class, 'asset_disposal' => FixedAsset::class, 'tax_transaction' => TaxTransaction::class, 'bank_reconciliation' => BankTransaction::class, 'settlement' => ExpenseRequest::class, 'period_closing' => AccountingPeriod::class, 'budget_reallocation' => BudgetReallocation::class, 'payment' => ExpenseRequest::class];
        $employeeId = $user->employee()->value('id');
        $activeRuns = ApprovalWorkflowRun::query()->where('status', 'in_progress')->get()->keyBy(fn ($run) => "{$run->module}:{$run->approvable_type}:{$run->approvable_id}");
        $approvalService = app(\App\Services\Approval\ApprovalWorkflowService::class);
        $allowedRuns = ApprovalWorkflowAction::query()->select('approval_workflow_actions.*')
            ->join('approval_workflow_runs', 'approval_workflow_runs.id', '=', 'approval_workflow_actions.approval_workflow_run_id')
            ->where('approval_workflow_runs.status', 'in_progress')
            ->where('approval_workflow_actions.status', 'pending')
            ->whereColumn('approval_workflow_actions.level', 'approval_workflow_runs.current_level')
            ->where(fn ($query) => $query->where('approval_workflow_actions.user_id', $user->id)->orWhere('approval_workflow_actions.role_id', $user->role_id)->orWhere('approval_workflow_actions.employee_id', $employeeId))
            ->with(['approvalMatrix', 'run:id,module,approvable_type,approvable_id', 'run.approvable'])
            ->get()
            ->filter(fn ($action) => $approvalService->userCanApproveAction($action, $user, $action->run?->approvable))
            ->mapWithKeys(fn ($action) => ["{$action->run->module}:{$action->run->approvable_type}:{$action->run->approvable_id}" => true]);

        $sortedItems = $items->filter(function (array $item) use ($moduleMap, $typeMap, $activeRuns, $allowedRuns) {
            if (! isset($moduleMap[$item['module']])) return true;
            $type = $item['entity_type'] ?? $typeMap[$item['module']];
            $key = "{$moduleMap[$item['module']]}:{$type}:{$item['id']}";
            return ! isset($activeRuns[$key]) || isset($allowedRuns[$key]);
        })->sortByDesc('created_at')->values();

        return response()->json([
            'success' => true,
            'count' => $sortedItems->count(),
            'data' => $sortedItems,
        ]);
    }

    /**
     * Execute hardened batch approve or reject actions by delegating to official domain services.
     */
    public function batchAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.module' => ['required', 'string', 'in:expense,pr,po,cba,scn,journal,ap,ar,timesheet,asset,asset_disposal,tax_transaction,bank_reconciliation,settlement,period_closing,budget_reallocation,payment'],
            'items.*.id' => ['required', 'integer'],
        ]);

        $action = $validated['action'];
        // This endpoint invokes domain controller methods directly. Route
        // middleware on the original endpoint is therefore not in the call
        // path, so enforce each action permission before processing anything.
        foreach ($validated['items'] as $item) {
            if ($item['module'] === 'payment') {
                if (! $request->user()->hasAnyPermission(['ap.pay', 'expense.pay'])) abort(403, 'Tidak memiliki permission pembayaran.');
                continue;
            }
            $permission = $this->approvalPermission($item['module']);
            if (! $request->user()->hasPermission($permission)) {
                abort(403, "Tidak memiliki permission {$permission} untuk approval {$item['module']}.");
            }
        }

        $processedCount = 0;
        $errors = [];

        // Instantiate domain controllers for official business logic delegation
        $expenseController = app(ExpenseRequestController::class);
        $prController = app(PurchaseRequestController::class);
        $fulfillmentController = app(ProcurementFulfillmentController::class);
        $advProcurementController = app(AdvancedProcurementController::class);
        $scnController = app(SupplierContractNotificationController::class);
        $journalController = app(JournalController::class);
        $timesheetController = app(TimesheetEntryController::class);
        $assetController = app(FixedAssetController::class);
        $apController = app(AccountsPayableController::class);
        $arController = app(AccountsReceivableController::class);
        $budgetReallocationController = app(BudgetReallocationController::class);
        $periodController = app(AccountingPeriodController::class);

        foreach ($validated['items'] as $item) {
            $module = $item['module'];
            $id = $item['id'];

            try {
                switch ($module) {
                    case 'expense':
                        $exp = ExpenseRequest::find($id);
                        if ($exp) {
                            if ($action === 'approve') {
                                if ($exp->status === 'submitted') {
                                    if (! $request->user()->hasPermission('expense.verify')) {
                                        abort(403, 'Tidak memiliki permission expense.verify untuk memproses Finance Verification.');
                                    }
                                    $expenseController->verify($request, $exp);
                                    $exp->refresh();
                                }
                                $expenseController->approve($request, $exp, app(\App\Services\Budget\BudgetMonitoringService::class), app(\App\Services\Settings\SystemPolicyService::class), app(\App\Services\Approval\ApprovalWorkflowService::class));
                            } else {
                                $expenseController->reject($request, $exp);
                            }
                            $processedCount++;
                        }
                        break;

                    case 'pr':
                        $pr = PurchaseRequest::find($id);
                        if ($pr) {
                            if ($action === 'approve') {
                                $prController->approve($request, $pr, app(\App\Services\Budget\BudgetMonitoringService::class), app(\App\Services\Settings\SystemPolicyService::class), app(\App\Services\Approval\ApprovalWorkflowService::class));
                            } else {
                                $prController->reject($pr);
                            }
                            $processedCount++;
                        }
                        break;

                    case 'po':
                        $po = PurchaseOrder::find($id);
                        if ($po) {
                            if ($action === 'approve') {
                                $fulfillmentController->approvePo($request, $po, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            } else {
                                $fulfillmentController->cancelPurchaseOrder($request, $po);
                            }
                            $processedCount++;
                        }
                        break;

                    case 'cba':
                        $cba = ComparativeBidAnalysis::find($id);
                        if ($cba) {
                            if ($action === 'approve') {
                                $advProcurementController->approveCba($request, $cba, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            }
                            $processedCount++;
                        }
                        break;

                    case 'scn':
                        $scn = SupplierContractNotification::find($id);
                        if ($scn) {
                            if ($action === 'approve') {
                                $scnController->issue($request, $scn, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            } else {
                                $scnController->cancel($scn);
                            }
                            $processedCount++;
                        }
                        break;

                    case 'journal':
                        $journal = Journal::find($id);
                        if ($journal) {
                            if ($action === 'approve') {
                                $journalController->post($request, $journal, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            } else {
                                $journal->update(['status' => 'draft']);
                            }
                            $processedCount++;
                        }
                        break;

                    case 'ap':
                        $invoice = SupplierInvoice::find($id);
                        if ($invoice) {
                            if ($action === 'approve') {
                                $apController->postInvoice($request, $invoice, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            } else {
                                $request->merge(['notes' => $validated['notes'] ?? 'Ditolak melalui Approval Center']);
                                $apController->rejectInvoice($request, $invoice, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            }
                            $processedCount++;
                        }
                        break;

                    case 'ar':
                        $invoice = CustomerInvoice::find($id);
                        if ($invoice) {
                            if ($action === 'approve') {
                                $arController->postInvoice($request, $invoice, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            } else {
                                $request->merge(['notes' => $validated['notes'] ?? 'Ditolak melalui Approval Center']);
                                $arController->rejectInvoice($request, $invoice, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            }
                            $processedCount++;
                        }
                        break;

                    case 'timesheet':
                        $ts = TimesheetEntry::find($id);
                        if ($ts) {
                            if ($action === 'approve') {
                                $timesheetController->approve($request, $ts, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            } else {
                                $timesheetController->reject($request, $ts, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            }
                            $processedCount++;
                        }
                        break;

                    case 'asset':
                        $asset = FixedAsset::find($id);
                        if ($asset) {
                            if ($action === 'approve') {
                                $assetController->capitalize($request, $asset, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            }
                            $processedCount++;
                        }
                        break;

                    case 'asset_disposal':
                        $asset = FixedAsset::find($id);
                        if ($asset && $action === 'approve') {
                            $request->merge(['disposed_date' => $asset->disposal_requested_date?->toDateString(), 'disposal_reason' => $asset->disposal_requested_reason]);
                            $assetController->dispose($request, $asset);
                            $processedCount++;
                        }
                        break;

                    case 'tax_transaction':
                        $tax = TaxTransaction::find($id);
                        if ($tax && $action === 'approve') {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->approve('tax_transaction', $tax, $request->user(), $validated['notes'] ?? null);
                            $processedCount++;
                        } elseif ($tax) {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->reject('tax_transaction', $tax, $request->user(), $validated['notes'] ?? 'Ditolak melalui Approval Center');
                            $processedCount++;
                        }
                        break;

                    case 'bank_reconciliation':
                        $bank = BankTransaction::find($id);
                        if ($bank && $action === 'approve') {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->approve('bank_reconciliation', $bank, $request->user(), $validated['notes'] ?? null);
                            $processedCount++;
                        } elseif ($bank) {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->reject('bank_reconciliation', $bank, $request->user(), $validated['notes'] ?? 'Ditolak melalui Approval Center');
                            $processedCount++;
                        }
                        break;

                    case 'settlement':
                        $settlement = ExpenseRequest::find($id);
                        if ($settlement && $action === 'approve') {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->approve('settlement', $settlement, $request->user(), $validated['notes'] ?? null);
                            $processedCount++;
                        } elseif ($settlement) {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->reject('settlement', $settlement, $request->user(), $validated['notes'] ?? 'Ditolak melalui Approval Center');
                            $processedCount++;
                        }
                        break;

                    case 'period_closing':
                        $period = AccountingPeriod::find($id);
                        if ($period && $action === 'approve') {
                            $periodController->close($request, $period->id, app(\App\Services\Approval\ApprovalWorkflowService::class));
                            $processedCount++;
                        } elseif ($period) {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->reject('period_closing', $period, $request->user(), $validated['notes'] ?? 'Ditolak melalui Approval Center');
                            $processedCount++;
                        }
                        break;

                    case 'budget_reallocation':
                        $reallocation = BudgetReallocation::find($id);
                        if ($reallocation && $action === 'approve') {
                            $budgetReallocationController->approve($request, $reallocation, app(\App\Services\Budget\BudgetMonitoringService::class), app(\App\Services\Approval\ApprovalWorkflowService::class));
                            $processedCount++;
                        } elseif ($reallocation) {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->reject('budget_reallocation', $reallocation, $request->user(), $validated['notes'] ?? 'Ditolak melalui Approval Center');
                            $reallocation->update(['status' => 'draft', 'decision_notes' => $validated['notes'] ?? 'Ditolak melalui Approval Center']);
                            $processedCount++;
                        }
                        break;

                    case 'payment':
                        $entity = SupplierInvoice::find($id) ?? ExpenseRequest::find($id);
                        if ($entity && $action === 'approve') {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->approve('payment', $entity, $request->user(), $validated['notes'] ?? null);
                            $processedCount++;
                        } elseif ($entity) {
                            app(\App\Services\Approval\ApprovalWorkflowService::class)->reject('payment', $entity, $request->user(), $validated['notes'] ?? 'Ditolak melalui Approval Center');
                            $processedCount++;
                        }
                        break;

                    default:
                        break;
                }
            } catch (\Throwable $e) {
                $errors[] = "Gagal memproses {$module} ID {$id}: ".$e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil memproses {$processedCount} item transaksi.",
            'processed_count' => $processedCount,
            'errors' => $errors,
        ]);
    }

    private function approvalPermission(string $module): string
    {
        return match ($module) {
            'expense' => 'expense.approve',
            'pr' => 'procurement.pr.approve',
            'po' => 'procurement.po.approve',
            'cba' => 'procurement.cba.approve',
            'scn' => 'procurement.pr.approve',
            'journal' => 'accounting.journal.post',
            'ap' => 'ap.post',
            'ar' => 'ar.post',
            'timesheet' => 'timesheet.approve',
            'asset' => 'asset.capitalize',
            'asset_disposal' => 'asset.dispose',
            'tax_transaction' => 'tax.manage',
            'bank_reconciliation' => 'banking.reconcile',
            'settlement' => 'expense.submit',
            'period_closing' => 'settings.manage',
            'budget_reallocation' => 'budget.approve',
            'payment' => 'ap.pay',
        };
    }
}
