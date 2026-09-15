<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\RoleMenuController;
use Illuminate\Support\Facades\Route;

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

Route::post('/login', [AuthController::class, 'login']);

Route::options('/{any}', function () {
    return response()->noContent();
})->where('any', '.*');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/rbac/me', [RbacController::class, 'me']);
    Route::apiResource('/menus', MenuController::class)->except(['show']);
    Route::get('/roles/menus', [RoleMenuController::class, 'index']);
    Route::put('/roles/{role}/menus', [RoleMenuController::class, 'sync']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // -------------------------------------------------------------
    // V1 MASTER DATA ROUTES (30 Entities + Export + Toggle Status)
    // -------------------------------------------------------------
    Route::prefix('v1/master')->group(function () {
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
            'vendors' => VendorController::class,
            'expense-categories' => ExpenseCategoryController::class,
            'document-types' => DocumentTypeController::class,
            'asset-categories' => AssetCategoryController::class,
            'approval-matrices' => ApprovalMatrixController::class,
        ];

        foreach ($masterResources as $uri => $controller) {
            Route::get("{$uri}/export", [$controller, 'export']);
            Route::patch("{$uri}/{id}/toggle-status", [$controller, 'toggleStatus']);
            Route::apiResource($uri, $controller)->parameters([$uri => 'id']);
        }
    });
});