<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\RoleMenuController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Accounting\JournalController;
use App\Http\Controllers\Api\Accounting\GeneralLedgerController;
use App\Http\Controllers\Api\Accounting\RecurringJournalController;
use App\Http\Controllers\Api\Budget\BudgetMonitoringController;
use App\Http\Controllers\Api\Budget\BudgetReallocationController;
use App\Http\Controllers\Api\Procurement\PurchaseRequestController;
use App\Http\Controllers\Api\Procurement\ProcurementFulfillmentController;
use App\Http\Controllers\Api\Procurement\AdvancedProcurementController;
use App\Http\Controllers\Api\Procurement\ProcurementDocumentController;
use App\Http\Controllers\Api\Procurement\SupplierContractNotificationController;
use App\Http\Controllers\Api\Finance\AccountsPayableController;
use App\Http\Controllers\Api\Finance\AccountsReceivableController;
use App\Http\Controllers\Api\Finance\TaxTransactionController;
use App\Http\Controllers\Api\Expense\ExpenseRequestController;
use App\Http\Controllers\Api\Timesheet\TimesheetEntryController;
use App\Http\Controllers\Api\Asset\FixedAssetController;
use App\Http\Controllers\Api\ReportsDashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\CustomReportController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\SavedReportController;
use App\Http\Controllers\Api\ApprovalCenterController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\UserSecurityController;

// Master Controllers
use App\Http\Controllers\Api\Master\OrganizationController;
use App\Http\Controllers\Api\Master\OfficeLocationController;
use App\Http\Controllers\Api\Master\DepartmentController;
use App\Http\Controllers\Api\Master\CostCenterController;
use App\Http\Controllers\Api\Master\CurrencyController;
use App\Http\Controllers\Api\Master\ExchangeRateController;
use App\Http\Controllers\Api\Master\FiscalYearController;
use App\Http\Controllers\Api\Master\AccountingPeriodController;
use App\Http\Controllers\Api\Master\ChartOfAccountController;
use App\Http\Controllers\Api\Master\AccountCategoryController;
use App\Http\Controllers\Api\Master\TaxController;
use App\Http\Controllers\Api\Master\BankAccountController;
use App\Http\Controllers\Api\Master\PettyCashController;
use App\Http\Controllers\Api\Master\PaymentMethodController;
use App\Http\Controllers\Api\Master\FundingSourceController;
use App\Http\Controllers\Api\Master\DonorController;
use App\Http\Controllers\Api\Master\GrantAgreementController;
use App\Http\Controllers\Api\Master\ProgramController;
use App\Http\Controllers\Api\Master\ProjectController;
use App\Http\Controllers\Api\Master\ActivityController;
use App\Http\Controllers\Api\Master\BeneficiaryPartnerController;
use App\Http\Controllers\Api\Master\ReportingDimensionController;
use App\Http\Controllers\Api\Master\UnitOfMeasureController;
use App\Http\Controllers\Api\Master\BudgetCategoryController;
use App\Http\Controllers\Api\Master\BudgetLineController;
use App\Http\Controllers\Api\Master\EmployeeController;
use App\Http\Controllers\Api\Master\VendorController;
use App\Http\Controllers\Api\Master\ExpenseCategoryController;
use App\Http\Controllers\Api\Master\DocumentTypeController;
use App\Http\Controllers\Api\Master\AssetCategoryController;
use App\Http\Controllers\Api\Master\ApprovalMatrixController;
use App\Http\Controllers\Api\Master\VendorCategoryController;
use App\Http\Controllers\Api\Master\ProcurementCategoryController;
use App\Http\Controllers\Api\Master\ProcurementItemController;
use App\Http\Controllers\Api\Master\PositionController;
use App\Http\Controllers\Api\Master\ProjectLogframeController;
use App\Http\Controllers\Api\Master\GrantReportingDeadlineController;

Route::get('/health', function () {
    $dbStatus = 'ok';
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        $dbStatus = 'unreachable: ' . $e->getMessage();
    }

    return response()->json([
        'status' => 'healthy',
        'app' => config('app.name'),
        'environment' => config('app.env'),
        'database' => $dbStatus,
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::options('/{any}', function () {
    return response()->noContent();
})->where('any', '.*');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/profile', [ProfileController::class, 'show']);
    Route::put('/me/profile', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'changePassword']);
    Route::get('/me/sessions', [UserSecurityController::class, 'sessions']);
    Route::delete('/me/sessions/{tokenId}', [UserSecurityController::class, 'revokeSession']);
    Route::get('/users/{user}/sessions', [UserSecurityController::class, 'sessions'])->middleware('permission:user.manage');
    Route::delete('/users/{user}/sessions/{tokenId}', [UserSecurityController::class, 'revokeSession'])->middleware('permission:user.manage');
    Route::put('/users/{user}/password', [UserSecurityController::class, 'resetPassword'])->middleware('permission:user.manage');
    Route::put('/users/{user}/active', [UserSecurityController::class, 'setActive'])->middleware('permission:user.manage');
    Route::get('/v1/settings', [SystemSettingsController::class, 'show'])->middleware('permission:settings.manage');
    Route::put('/v1/settings', [SystemSettingsController::class, 'update'])->middleware('permission:settings.manage');
    Route::get('/rbac/me', [RbacController::class, 'me']);
    Route::get('/menus', [MenuController::class, 'index'])->middleware('permission:master-menu.view');
    Route::apiResource('/menus', MenuController::class)->except(['show'])->middleware('permission:master-menu.manage');
    Route::get('/roles', [RoleMenuController::class, 'options'])->middleware('permission:master-data.view');
    Route::get('/permissions', [RoleMenuController::class, 'permissionOptions'])->middleware('permission:role-access.view');
    Route::get('/roles/menus', [RoleMenuController::class, 'index'])->middleware('permission:role-access.view');
    Route::put('/roles/{role}/menus', [RoleMenuController::class, 'sync'])->middleware('permission:role-access.manage');
    Route::put('/roles/{role}/permissions', [RoleMenuController::class, 'syncPermissions'])->middleware('permission:role-access.manage');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/v1/dashboard/overview', [ReportsDashboardController::class, 'dashboard'])->middleware('permission:dashboard.view');
    Route::get('/v1/reports/summary', [ReportsDashboardController::class, 'reports'])->middleware('permission:reports.view');
    Route::get('/v1/reports/balance-sheet', [ReportsDashboardController::class, 'balanceSheet'])->middleware('permission:reports.view');
    Route::get('/v1/reports/profit-loss/pdf', [ReportsDashboardController::class, 'profitLossPdf'])->middleware('permission:reports.export');
    Route::get('/v1/reports/balance-sheet/pdf', [ReportsDashboardController::class, 'balanceSheetPdf'])->middleware('permission:reports.export');
    Route::get('/v1/reports/forecast', [ReportsDashboardController::class, 'forecast'])->middleware('permission:reports.view');
    Route::get('/v1/reports/custom/options', [CustomReportController::class, 'options'])->middleware('permission:reports.view');
    Route::post('/v1/reports/custom', [CustomReportController::class, 'build'])->middleware('permission:reports.view');
    Route::post('/v1/reports/custom/export', [CustomReportController::class, 'export'])->middleware('permission:reports.export');
    Route::get('/v1/reports/custom/saved', [SavedReportController::class, 'index'])->middleware('permission:reports.view');
    Route::post('/v1/reports/custom/saved', [SavedReportController::class, 'store'])->middleware('permission:reports.view');
    Route::delete('/v1/reports/custom/saved/{savedReport}', [SavedReportController::class, 'destroy'])->middleware('permission:reports.view');
    Route::post('/v1/automation/run', [AutomationController::class, 'run'])->middleware('permission:reports.view');
    Route::get('/v1/donors/dashboard', [ReportsDashboardController::class, 'donorDashboard'])->middleware('permission:reports.view');

    Route::prefix('v1/approval-center')->group(function () {
        Route::get('/pending', [ApprovalCenterController::class, 'pending']);
        Route::post('/batch-action', [ApprovalCenterController::class, 'batchAction']);
    });

    Route::prefix('v1/attachments')->group(function () {
        Route::post('/upload', [AttachmentController::class, 'upload']);
        Route::get('/{module}/{id}/{index}', [AttachmentController::class, 'download']);
    });

    Route::prefix('v1/notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
    });

    Route::prefix('v1/accounting')->group(function () {
        Route::get('general-ledger', [GeneralLedgerController::class, 'index'])->middleware('permission:accounting.view');
        Route::post('recurring-journals/{recurringJournal}/generate', [RecurringJournalController::class, 'generate'])
            ->middleware('permission:accounting.journal.create');
        Route::apiResource('recurring-journals', RecurringJournalController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:accounting.journal.create');
        Route::get('journals', [JournalController::class, 'index'])->middleware('permission:accounting.journal.view');
        Route::post('journals', [JournalController::class, 'store'])->middleware('permission:accounting.journal.create');
        Route::get('journals/{journal}', [JournalController::class, 'show'])->middleware('permission:accounting.journal.view');
        Route::put('journals/{journal}', [JournalController::class, 'update'])->middleware('permission:accounting.journal.update');
        Route::delete('journals/{journal}', [JournalController::class, 'destroy'])->middleware('permission:accounting.journal.delete');
        Route::post('journals/{journal}/submit', [JournalController::class, 'submit'])->middleware('permission:accounting.journal.submit');
        Route::post('journals/{journal}/review', [JournalController::class, 'review'])->middleware('permission:accounting.journal.review');
        Route::post('journals/{journal}/post', [JournalController::class, 'post'])->middleware('permission:accounting.journal.post');
        Route::post('journals/{journal}/reverse', [JournalController::class, 'reverse'])->middleware('permission:accounting.journal.reverse');
    });

    Route::prefix('v1/budget')->group(function () {
        Route::get('monitoring', [BudgetMonitoringController::class, 'index'])->middleware('permission:budget.view');
        Route::post('validate', [BudgetMonitoringController::class, 'validateBudget'])->middleware('permission:budget.validate');
        Route::get('reallocations', [BudgetReallocationController::class, 'index'])->middleware('permission:budget.view');
        Route::post('reallocations', [BudgetReallocationController::class, 'store'])->middleware('permission:budget.update');
        Route::post('reallocations/{budgetReallocation}/submit', [BudgetReallocationController::class, 'submit'])->middleware('permission:budget.update');
        Route::post('reallocations/{budgetReallocation}/approve', [BudgetReallocationController::class, 'approve'])->middleware('permission:budget.approve');
    });

    Route::prefix('v1/procurement')->group(function () {
        Route::get('purchase-requests/{purchaseRequest}/pdf', [ProcurementDocumentController::class, 'purchaseRequest'])->middleware('permission:procurement.pr.view');
        Route::get('purchase-orders/{purchaseOrder}/pdf', [ProcurementDocumentController::class, 'purchaseOrder'])->middleware('permission:procurement.po.view');
        Route::get('goods-receipts/{goodsReceipt}/pdf', [ProcurementDocumentController::class, 'goodsReceipt'])->middleware('permission:procurement.grn.view');
        Route::get('supplier-invoices/{supplierInvoice}/pdf', [ProcurementDocumentController::class, 'supplierInvoice'])->middleware('permission:procurement.invoice.view');
        Route::get('scns/{supplierContractNotification}/pdf', [ProcurementDocumentController::class, 'scn'])->middleware('permission:procurement.pr.view');
        Route::get('processes/options', [ProcurementFulfillmentController::class, 'processOptions'])->middleware('permission:procurement.po.view');
        Route::get('goods-receipts', [ProcurementFulfillmentController::class, 'goodsReceipts'])->middleware('permission:procurement.grn.view');
        Route::get('goods-receipts/{goodsReceipt}', [ProcurementFulfillmentController::class, 'showGoodsReceipt'])->middleware('permission:procurement.grn.view');
        Route::put('goods-receipts/{goodsReceipt}', [ProcurementFulfillmentController::class, 'updateGoodsReceipt'])->middleware('permission:procurement.grn.create');
        Route::post('goods-receipts/{goodsReceipt}/cancel', [ProcurementFulfillmentController::class, 'cancelGoodsReceipt'])->middleware('permission:procurement.grn.create');
        Route::get('supplier-invoices', [ProcurementFulfillmentController::class, 'supplierInvoices'])->middleware('permission:procurement.invoice.view');
        Route::get('supplier-invoices/{supplierInvoice}', [ProcurementFulfillmentController::class, 'showSupplierInvoice'])->middleware('permission:procurement.invoice.view');
        Route::put('supplier-invoices/{supplierInvoice}', [ProcurementFulfillmentController::class, 'updateSupplierInvoice'])->middleware('permission:procurement.invoice.create');
        Route::post('supplier-invoices/{supplierInvoice}/cancel', [ProcurementFulfillmentController::class, 'cancelSupplierInvoice'])->middleware('permission:procurement.invoice.create');
        Route::get('purchase-requests', [PurchaseRequestController::class, 'index'])->middleware('permission:procurement.pr.view');
        Route::post('purchase-requests', [PurchaseRequestController::class, 'store'])->middleware('permission:procurement.pr.create');
        Route::get('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->middleware('permission:procurement.pr.view');
        Route::put('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->middleware('permission:procurement.pr.update');
        Route::delete('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->middleware('permission:procurement.pr.delete');
        Route::post('purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->middleware('permission:procurement.pr.submit');
        Route::post('purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->middleware('permission:procurement.pr.approve');
        Route::post('purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->middleware('permission:procurement.pr.approve');
        Route::post('purchase-requests/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])->middleware('permission:procurement.pr.update');
        Route::get('purchase-orders', [ProcurementFulfillmentController::class, 'purchaseOrders'])->middleware('permission:procurement.po.view');
        Route::get('purchase-orders/{purchaseOrder}', [ProcurementFulfillmentController::class, 'showPurchaseOrder'])->middleware('permission:procurement.po.view');
        Route::put('purchase-orders/{purchaseOrder}', [ProcurementFulfillmentController::class, 'updatePurchaseOrder'])->middleware('permission:procurement.po.create');
        Route::post('purchase-orders/{purchaseOrder}/cancel', [ProcurementFulfillmentController::class, 'cancelPurchaseOrder'])->middleware('permission:procurement.po.approve');
        Route::post('purchase-requests/{purchaseRequest}/purchase-orders', [ProcurementFulfillmentController::class, 'createPoFromPr'])->middleware('permission:procurement.po.create');
        Route::post('purchase-orders/{purchaseOrder}/submit', [ProcurementFulfillmentController::class, 'submitPo'])->middleware('permission:procurement.po.approve');
        Route::post('purchase-orders/{purchaseOrder}/approve', [ProcurementFulfillmentController::class, 'approvePo'])->middleware('permission:procurement.po.approve');
        Route::get('purchase-orders/{purchaseOrder}/amendments', [ProcurementFulfillmentController::class, 'amendments'])->middleware('permission:procurement.po.view');
        Route::post('purchase-orders/{purchaseOrder}/amendments', [ProcurementFulfillmentController::class, 'createAmendment'])->middleware('permission:procurement.po.create');
        Route::post('po-amendments/{poAmendment}/approve', [ProcurementFulfillmentController::class, 'approveAmendment'])->middleware('permission:procurement.po.approve');
        Route::post('purchase-orders/{purchaseOrder}/vendor-evaluations', [ProcurementFulfillmentController::class, 'evaluateVendor'])->middleware('permission:procurement.po.approve');
        Route::post('purchase-orders/{purchaseOrder}/goods-receipts', [ProcurementFulfillmentController::class, 'createGrn'])->middleware('permission:procurement.grn.create');
        Route::post('purchase-orders/{purchaseOrder}/supplier-invoices', [ProcurementFulfillmentController::class, 'createInvoice'])->middleware('permission:procurement.invoice.create');
        Route::post('supplier-invoices/{supplierInvoice}/three-way-match', [ProcurementFulfillmentController::class, 'threeWayMatch'])->middleware('permission:procurement.invoice.match');
        Route::get('rfqs', [AdvancedProcurementController::class, 'rfqs'])->middleware('permission:procurement.rfq.view');
        Route::post('purchase-requests/{purchaseRequest}/rfqs', [AdvancedProcurementController::class, 'createRfq'])->middleware('permission:procurement.rfq.create');
        Route::post('rfqs/{rfq}/quotations', [AdvancedProcurementController::class, 'submitQuotation'])->middleware('permission:procurement.rfq.create');
        Route::post('rfqs/{rfq}/close', [AdvancedProcurementController::class, 'closeRfq'])->middleware('permission:procurement.rfq.create');
        Route::post('rfqs/{rfq}/cancel', [AdvancedProcurementController::class, 'cancelRfq'])->middleware('permission:procurement.rfq.create');
        Route::post('rfqs/{rfq}/cba', [AdvancedProcurementController::class, 'createCba'])->middleware('permission:procurement.cba.create');
        Route::post('cba/{cba}/submit', [AdvancedProcurementController::class, 'submitCba'])->middleware('permission:procurement.cba.approve');
        Route::post('cba/{cba}/approve', [AdvancedProcurementController::class, 'approveCba'])->middleware('permission:procurement.cba.approve');
        Route::post('cba/{cba}/purchase-orders', [AdvancedProcurementController::class, 'createPoFromCba'])->middleware('permission:procurement.po.create');
        Route::get('scns', [SupplierContractNotificationController::class, 'index'])->middleware('permission:procurement.pr.view');
        Route::get('scns/{supplierContractNotification}', [SupplierContractNotificationController::class, 'show'])->middleware('permission:procurement.pr.view');
        Route::put('scns/{supplierContractNotification}', [SupplierContractNotificationController::class, 'update'])->middleware('permission:procurement.pr.create');
        Route::post('scns', [SupplierContractNotificationController::class, 'store'])->middleware('permission:procurement.pr.create');
        Route::post('scns/{supplierContractNotification}/submit', [SupplierContractNotificationController::class, 'submit'])->middleware('permission:procurement.pr.approve');
        Route::post('scns/{supplierContractNotification}/issue', [SupplierContractNotificationController::class, 'issue'])->middleware('permission:procurement.pr.approve');
        Route::post('scns/{supplierContractNotification}/cancel', [SupplierContractNotificationController::class, 'cancel'])->middleware('permission:procurement.pr.approve');
    });

    Route::prefix('v1/finance')->group(function () {
        Route::get('ap/invoices', [AccountsPayableController::class, 'invoices'])->middleware('permission:ap.view');
        Route::post('ap/invoices/{supplierInvoice}/submit', [AccountsPayableController::class, 'submitInvoice'])->middleware('permission:ap.post');
        Route::post('ap/invoices/{supplierInvoice}/reject', [AccountsPayableController::class, 'rejectInvoice'])->middleware('permission:ap.post');
        Route::post('ap/invoices/{supplierInvoice}/post', [AccountsPayableController::class, 'postInvoice'])->middleware('permission:ap.post');
        Route::post('ap/invoices/{supplierInvoice}/payments', [AccountsPayableController::class, 'payInvoice'])->middleware('permission:ap.pay');
        Route::get('ar/customers', [AccountsReceivableController::class, 'customers'])->middleware('permission:ar.view');
        Route::post('ar/customers', [AccountsReceivableController::class, 'storeCustomer'])->middleware('permission:ar.create');
        Route::get('ar/invoices', [AccountsReceivableController::class, 'invoices'])->middleware('permission:ar.view');
        Route::post('ar/invoices', [AccountsReceivableController::class, 'storeInvoice'])->middleware('permission:ar.create');
        Route::post('ar/invoices/{customerInvoice}/submit', [AccountsReceivableController::class, 'submitInvoice'])->middleware('permission:ar.post');
        Route::post('ar/invoices/{customerInvoice}/reject', [AccountsReceivableController::class, 'rejectInvoice'])->middleware('permission:ar.post');
        Route::post('ar/invoices/{customerInvoice}/post', [AccountsReceivableController::class, 'postInvoice'])->middleware('permission:ar.post');
        Route::post('ar/invoices/{customerInvoice}/receipts', [AccountsReceivableController::class, 'receivePayment'])->middleware('permission:ar.receive');
        Route::get('payments', [AccountsPayableController::class, 'payments'])->middleware('permission:payments.view');
        Route::get('bank-transactions', [AccountsPayableController::class, 'bankTransactions'])->middleware('permission:banking.view');
        Route::get('bank-transactions/exceptions', [AccountsPayableController::class, 'bankExceptions'])->middleware('permission:banking.view');
        Route::post('bank-transactions/import', [AccountsPayableController::class, 'importBankTransactions'])->middleware('permission:banking.view');
        Route::post('bank-transactions/{bankTransaction}/auto-match', [AccountsPayableController::class, 'autoMatchBankTransaction'])->middleware('permission:banking.view');
        Route::post('bank-transactions/{bankTransaction}/match', [AccountsPayableController::class, 'matchBankTransaction'])->middleware('permission:banking.view');
        Route::post('bank-transactions/{bankTransaction}/reconcile', [AccountsPayableController::class, 'reconcileBankTransaction'])->middleware('permission:banking.view');
        Route::post('bank-transactions/{bankTransaction}/unmatch', [AccountsPayableController::class, 'unmatchBankTransaction'])->middleware('permission:banking.view');
    });

    Route::prefix('v1/tax')->group(function () {
        Route::get('taxes', [TaxTransactionController::class, 'taxes'])->middleware('permission:tax.view');
        Route::post('calculate', [TaxTransactionController::class, 'calculate'])->middleware('permission:tax.view');
        Route::get('transactions', [TaxTransactionController::class, 'index'])->middleware('permission:tax.view');
        Route::post('transactions', [TaxTransactionController::class, 'store'])->middleware('permission:tax.manage');
        Route::get('export/djp', [TaxTransactionController::class, 'exportDjp'])->middleware('permission:tax.manage');
        Route::post('transactions/{taxTransaction}/report', [TaxTransactionController::class, 'markReported'])->middleware('permission:tax.manage');
        Route::get('report', [TaxTransactionController::class, 'report'])->middleware('permission:tax.view');
        Route::get('calendar', [TaxTransactionController::class, 'calendar'])->middleware('permission:tax.view');
    });

    Route::prefix('v1/timesheets')->group(function () {
        Route::get('entries', [TimesheetEntryController::class, 'index'])->middleware('permission:timesheet.view');
        Route::post('entries', [TimesheetEntryController::class, 'store'])->middleware('permission:timesheet.create');
        Route::post('entries/post-labor-cost', [TimesheetEntryController::class, 'postLaborCost'])->middleware('permission:timesheet.approve');
        Route::put('entries/{timesheetEntry}', [TimesheetEntryController::class, 'update'])->middleware('permission:timesheet.update');
        Route::post('entries/{timesheetEntry}/submit', [TimesheetEntryController::class, 'submit'])->middleware('permission:timesheet.submit');
        Route::post('entries/{timesheetEntry}/approve', [TimesheetEntryController::class, 'approve'])->middleware('permission:timesheet.approve');
        Route::post('entries/{timesheetEntry}/reject', [TimesheetEntryController::class, 'reject'])->middleware('permission:timesheet.approve');
    });

    Route::prefix('v1/assets')->group(function () {
        Route::get('fixed-assets', [FixedAssetController::class, 'index'])->middleware('permission:asset.view');
        Route::post('fixed-assets', [FixedAssetController::class, 'store'])->middleware('permission:asset.create');
        Route::post('fixed-assets/bulk-depreciate', [FixedAssetController::class, 'bulkDepreciate'])->middleware('permission:asset.depreciate');
        Route::post('fixed-assets/stock-opname', [FixedAssetController::class, 'stockOpname'])->middleware('permission:asset.view');
        Route::post('fixed-assets/{fixedAsset}/submit-capitalization', [FixedAssetController::class, 'submitCapitalization'])->middleware('permission:asset.capitalize');
        Route::post('fixed-assets/{fixedAsset}/capitalize', [FixedAssetController::class, 'capitalize'])->middleware('permission:asset.capitalize');
        Route::post('fixed-assets/{fixedAsset}/depreciate', [FixedAssetController::class, 'depreciate'])->middleware('permission:asset.depreciate');
        Route::post('fixed-assets/{fixedAsset}/transfer', [FixedAssetController::class, 'transfer'])->middleware('permission:asset.transfer');
        Route::post('fixed-assets/{fixedAsset}/submit-disposal', [FixedAssetController::class, 'submitDisposal'])->middleware('permission:asset.dispose');
        Route::post('fixed-assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])->middleware('permission:asset.dispose');
    });

    Route::prefix('v1/expenses')->group(function () {
        Route::get('requests', [ExpenseRequestController::class, 'index'])->middleware('permission:expense.view');
        Route::post('requests', [ExpenseRequestController::class, 'store'])->middleware('permission:expense.create');
        Route::post('requests/{expenseRequest}/attachments', [ExpenseRequestController::class, 'uploadAttachment'])->middleware('permission:expense.create');
        Route::get('requests/{expenseRequest}/attachments/{index}', [ExpenseRequestController::class, 'downloadAttachment'])->middleware('permission:expense.view');
        Route::post('requests/{expenseRequest}/submit', [ExpenseRequestController::class, 'submit'])->middleware('permission:expense.submit');
        Route::post('requests/{expenseRequest}/verify', [ExpenseRequestController::class, 'verify'])->middleware('permission:expense.verify');
        Route::post('requests/{expenseRequest}/approve', [ExpenseRequestController::class, 'approve'])->middleware('permission:expense.approve');
        Route::post('requests/{expenseRequest}/reject', [ExpenseRequestController::class, 'reject'])->middleware('permission:expense.approve');
        Route::post('requests/{expenseRequest}/resubmit', [ExpenseRequestController::class, 'resubmit'])->middleware('permission:expense.submit');
        Route::post('requests/{expenseRequest}/post', [ExpenseRequestController::class, 'post'])->middleware('permission:expense.post');
        Route::post('requests/{expenseRequest}/pay', [ExpenseRequestController::class, 'pay'])->middleware('permission:expense.pay');
        Route::post('requests/{expenseRequest}/settle', [ExpenseRequestController::class, 'settle'])->middleware('permission:expense.submit');
    });

    // -------------------------------------------------------------
    // V1 MASTER DATA ROUTES (30 Entities + Export + Toggle Status)
    // -------------------------------------------------------------
    Route::prefix('v1/master')->group(function () {
        Route::apiResource('project-logframes', ProjectLogframeController::class)->middleware('permission:master-data');
        Route::apiResource('grant-reporting-deadlines', GrantReportingDeadlineController::class)->middleware('permission:master-data');
        Route::get('roles', [RoleMenuController::class, 'options'])->middleware('permission:master-data.view');
        $masterResources = [
            'organizations' => OrganizationController::class,
            'office-locations' => OfficeLocationController::class,
            'departments' => DepartmentController::class,
            'cost-centers' => CostCenterController::class,
            'currencies' => CurrencyController::class,
            'exchange-rates' => ExchangeRateController::class,
            'fiscal-years' => FiscalYearController::class,
            'accounting-periods' => AccountingPeriodController::class,
            'chart-of-accounts' => ChartOfAccountController::class,
            'account-categories' => AccountCategoryController::class,
            'taxes' => TaxController::class,
            'bank-accounts' => BankAccountController::class,
            'petty-cashes' => PettyCashController::class,
            'payment-methods' => PaymentMethodController::class,
            'funding-sources' => FundingSourceController::class,
            'donors' => DonorController::class,
            'grant-agreements' => GrantAgreementController::class,
            'programs' => ProgramController::class,
            'projects' => ProjectController::class,
            'activities' => ActivityController::class,
            'beneficiary-partners' => BeneficiaryPartnerController::class,
            'reporting-dimensions' => ReportingDimensionController::class,
            'unit-of-measures' => UnitOfMeasureController::class,
            'budget-categories' => BudgetCategoryController::class,
            'budget-lines' => BudgetLineController::class,
            'employees' => EmployeeController::class,
            'positions' => PositionController::class,
            'vendors' => VendorController::class,
            'vendor-categories' => VendorCategoryController::class,
            'procurement-categories' => ProcurementCategoryController::class,
            'procurement-items' => ProcurementItemController::class,
            'expense-categories' => ExpenseCategoryController::class,
            'document-types' => DocumentTypeController::class,
            'asset-categories' => AssetCategoryController::class,
            'approval-matrices' => ApprovalMatrixController::class,
        ];
        Route::post('accounting-periods/{id}/close', [AccountingPeriodController::class, 'close'])->middleware('permission:settings.manage');
        Route::post('accounting-periods/{id}/reopen', [AccountingPeriodController::class, 'reopen'])->middleware('permission:settings.manage');

        foreach ($masterResources as $uri => $controller) {
            Route::get("{$uri}/template", [$controller, 'template'])->middleware('permission:master-data.view');
            Route::get("{$uri}/export", [$controller, 'export'])->middleware('permission:master-data.export');
            Route::post("{$uri}/import", [$controller, 'import'])->middleware('permission:master-data.manage');
            Route::patch("{$uri}/{id}/toggle-status", [$controller, 'toggleStatus'])->middleware('permission:master-data.manage');
            Route::apiResource($uri, $controller)
                ->parameters([$uri => 'id'])
                ->middleware('permission:master-data');
        }
    });
});
