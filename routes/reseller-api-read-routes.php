<?php

use App\Http\Controllers\Api\V1\Reseller\ResellerApiActivityController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiAnnouncementController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiCommissionController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiCustomerController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiCustomerTransferController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiDashboardController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiDueAccountController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiEnterpriseReportController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiInvoiceController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiNetworkController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiNotificationController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiOnuController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiReportController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiSettlementController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiSubResellerController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiTicketController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiWalletController;
use App\Http\Controllers\Api\V1\Reseller\ResellerApiWalletOverviewController;
use App\Support\ResellerPortalPermission;
use Illuminate\Support\Facades\Route;

/**
 * Shared GET routes for /api/v1/reseller/* and legacy /api/v1/reseller/partner/*.
 */
return function (): void {
    Route::get('/dashboard', [ResellerApiDashboardController::class, 'show']);
    Route::get('/wallet', [ResellerApiWalletController::class, 'show'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::WALLET_VIEW);
    Route::get('/wallet/overview', [ResellerApiWalletOverviewController::class, 'show'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::WALLET_VIEW);
    Route::get('/customers', [ResellerApiCustomerController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::CUSTOMER_VIEW);
    Route::get('/customers/{customer}', [ResellerApiCustomerController::class, 'show'])
        ->whereNumber('customer')
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::CUSTOMER_VIEW);
    Route::get('/commissions', [ResellerApiCommissionController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::COMMISSION_VIEW);
    Route::get('/settlements', [ResellerApiSettlementController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::SETTLEMENT_MANAGE);
    Route::get('/invoices', [ResellerApiInvoiceController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::BILLING_VIEW);
    Route::get('/tickets', [ResellerApiTicketController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::TICKET_CREATE);
    Route::get('/network', [ResellerApiNetworkController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::NETWORK_VIEW);
    Route::get('/onu', [ResellerApiOnuController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::ONU_VIEW);
    Route::get('/reports/summary', [ResellerApiReportController::class, 'summary'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::REPORTS_VIEW);
    Route::get('/reports/enterprise', [ResellerApiEnterpriseReportController::class, 'show'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::REPORTS_VIEW);
    Route::get('/activity', [ResellerApiActivityController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::REPORTS_VIEW);
    Route::get('/sub-resellers', [ResellerApiSubResellerController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::SUB_RESELLER_VIEW);
    Route::get('/sub-resellers/{child}', [ResellerApiSubResellerController::class, 'show'])
        ->whereNumber('child')
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::SUB_RESELLER_VIEW);
    Route::get('/customer-transfers', [ResellerApiCustomerTransferController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::CUSTOMER_TRANSFER);
    Route::get('/announcements', [ResellerApiAnnouncementController::class, 'index'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::ANNOUNCEMENTS_VIEW);
    Route::get('/due-account', [ResellerApiDueAccountController::class, 'show'])
        ->middleware('reseller.api.permission:'.ResellerPortalPermission::RESELLER_BILLING_VIEW);
    Route::get('/notifications', [ResellerApiNotificationController::class, 'index']);
};
