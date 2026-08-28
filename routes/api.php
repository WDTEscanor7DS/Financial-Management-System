<?php

use App\Http\Controllers\Api\AccountsPayableController;
use App\Http\Controllers\Api\AccountsReceivableController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FundController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProcurementController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RevenueController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Every route below sits behind the 'auth:sanctum' (session-based, same
| origin as the existing frontend under public/app) and 'account.active'
| middleware at group level, then an additional 'permission:<slug>' entry
| per route for the specific action -- this is the server-side
| enforcement point for Sections 32-34 of the brief. The frontend's own
| role-based hiding of buttons/menu items (js/auth.js) is left in place as
| a UX nicety, but every one of these endpoints re-checks independently,
| so a request forged outside the UI still gets a 403.
|
*/

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {

    Route::get('/me', [MeController::class, 'show']);
    Route::get('/departments', [DepartmentController::class, 'index']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::middleware('permission:view_dashboard')->group(function () {
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/dashboard/recent-transactions', [DashboardController::class, 'recentTransactions']);
        Route::get('/dashboard/pending-actions', [DashboardController::class, 'pendingActions']);
    });

    // Budget
    Route::middleware('permission:view_budget')->get('/budgets', [BudgetController::class, 'index']);
    Route::middleware('permission:create_budget')->post('/budgets', [BudgetController::class, 'store']);
    Route::middleware('permission:edit_budget')->put('/budgets/{budget}', [BudgetController::class, 'update']);
    Route::middleware('permission:delete_budget')->delete('/budgets/{budget}', [BudgetController::class, 'destroy']);

    // Revenue
    Route::middleware('permission:view_revenue')->get('/revenues', [RevenueController::class, 'index']);
    Route::middleware('permission:create_revenue')->post('/revenues', [RevenueController::class, 'store']);
    Route::middleware('permission:edit_revenue')->put('/revenues/{revenue}', [RevenueController::class, 'update']);
    Route::middleware('permission:delete_revenue')->delete('/revenues/{revenue}', [RevenueController::class, 'destroy']);

    // Expenses
    Route::middleware('permission:view_expenses')->get('/expenses', [ExpenseController::class, 'index']);
    Route::middleware('permission:create_expense')->post('/expenses', [ExpenseController::class, 'store']);
    Route::middleware('permission:edit_expense')->put('/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::middleware('permission:delete_expense')->delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

    // Accounts Payable
    Route::middleware('permission:view_accounts_payable')->get('/accounts-payable', [AccountsPayableController::class, 'index']);
    Route::middleware('permission:create_accounts_payable')->post('/accounts-payable', [AccountsPayableController::class, 'store']);
    Route::middleware('permission:record_payable_payment')->post('/accounts-payable/{accountsPayable}/payments', [AccountsPayableController::class, 'recordPayment']);
    Route::middleware('permission:delete_accounts_payable')->delete('/accounts-payable/{accountsPayable}', [AccountsPayableController::class, 'destroy']);

    // Accounts Receivable
    Route::middleware('permission:view_accounts_receivable')->get('/accounts-receivable', [AccountsReceivableController::class, 'index']);
    Route::middleware('permission:create_accounts_receivable')->post('/accounts-receivable', [AccountsReceivableController::class, 'store']);
    Route::middleware('permission:record_receivable_payment')->post('/accounts-receivable/{accountsReceivable}/payments', [AccountsReceivableController::class, 'recordPayment']);
    Route::middleware('permission:delete_accounts_receivable')->delete('/accounts-receivable/{accountsReceivable}', [AccountsReceivableController::class, 'destroy']);

    // Funds
    Route::middleware('permission:view_funds')->get('/funds', [FundController::class, 'index']);
    Route::middleware('permission:create_fund')->post('/funds', [FundController::class, 'store']);
    Route::middleware('permission:allocate_funds')->post('/funds/{fund}/allocate', [FundController::class, 'allocate']);
    Route::middleware('permission:delete_fund')->delete('/funds/{fund}', [FundController::class, 'destroy']);

    // Procurement -- note: index() itself scopes visibility per-role, so it
    // only needs view_procurement (every role that can reach the module at
    // all has this permission, per RolePermissionSeeder).
    Route::middleware('permission:view_procurement')->get('/procurement', [ProcurementController::class, 'index']);
    Route::middleware('permission:create_procurement_request')->post('/procurement', [ProcurementController::class, 'store']);
    Route::post('/procurement/{procurementRequest}/review', [ProcurementController::class, 'review']); // permission checked per-decision inside the controller
    Route::middleware('permission:advance_procurement_request')->post('/procurement/{procurementRequest}/advance', [ProcurementController::class, 'advance']);
    Route::middleware('permission:delete_procurement_request')->delete('/procurement/{procurementRequest}', [ProcurementController::class, 'destroy']);

    // Assets
    Route::middleware('permission:view_assets')->get('/assets', [AssetController::class, 'index']);
    Route::middleware('permission:create_asset')->post('/assets', [AssetController::class, 'store']);
    Route::middleware('permission:edit_asset')->put('/assets/{asset}', [AssetController::class, 'update']);
    Route::middleware('permission:delete_asset')->delete('/assets/{asset}', [AssetController::class, 'destroy']);

    // Reports
    Route::middleware('permission:view_reports')->group(function () {
        Route::get('/reports/income-statement', [ReportController::class, 'income']);
        Route::get('/reports/budget-vs-actual', [ReportController::class, 'budgetVsActual']);
        Route::get('/reports/aging', [ReportController::class, 'aging']);
        Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow']);
        Route::get('/reports/expenses', [ReportController::class, 'expenses']);
        Route::get('/reports/revenues', [ReportController::class, 'revenues']);
    });

    // Audit trail -- read-only, Administrator only.
    Route::middleware('permission:view_audit_logs')->get('/audit-logs', [AuditLogController::class, 'index']);

    // User management -- Administrator only.
    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/send-password-reset', [UserController::class, 'sendPasswordReset']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
