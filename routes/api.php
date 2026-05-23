<?php

use App\Http\Controllers\Api\NetflowWebhookController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\SupportTicketWebhookController;
use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Api\V1\Customer\DeviceController as CustomerDeviceController;
use App\Http\Controllers\Api\V1\Customer\InvoiceController as CustomerInvoiceController;
use App\Http\Controllers\Api\V1\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Api\V1\Customer\TicketController as CustomerTicketController;
use App\Http\Controllers\Api\V1\Customer\UsageController as CustomerUsageController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Mobile\ApiIndexController;
use App\Http\Controllers\Api\V1\Mobile\MobileConfigController;
use App\Http\Controllers\Api\V1\Mobile\TokenRefreshController;
use App\Http\Controllers\Api\V1\Mobile\UnifiedAuthController;
use App\Http\Controllers\Api\V1\Staff\CustomerDetailController;
use App\Http\Controllers\Api\V1\Staff\CustomerSearchController;
use App\Http\Controllers\Api\V1\Staff\StaffOnlineController;
use App\Http\Controllers\Api\V1\Staff\StaffTasksController;
use App\Http\Controllers\Api\V1\Staff\StaffApprovalsController;
use App\Http\Controllers\Api\V1\Staff\StaffBillingController;
use App\Http\Controllers\Api\V1\Staff\StaffCustomerStoreController;
use App\Http\Controllers\Api\V1\Staff\StaffCustomerUsageController;
use App\Http\Controllers\Api\V1\Staff\StaffCommsController;
use App\Http\Controllers\Api\V1\Staff\StaffExpenseController;
use App\Http\Controllers\Api\V1\Staff\StaffOnuController;
use App\Http\Controllers\Api\V1\Staff\StaffPackagesController;
use App\Http\Controllers\Api\V1\Staff\StaffPaymentsController;
use App\Http\Controllers\Api\V1\Staff\StaffProfileController;
use App\Http\Controllers\Api\V1\Staff\StaffReportsController;
use App\Http\Controllers\Api\V1\Staff\StaffCustomerUpdateController;
use App\Http\Controllers\Api\V1\Staff\StaffLineActivationController;
use App\Http\Controllers\Api\V1\Staff\StaffMonitoringController;
use App\Http\Controllers\Api\V1\Staff\StaffTicketsController;
use App\Http\Controllers\Api\V1\Customer\AiController as CustomerAiController;
use App\Http\Controllers\Api\V1\Customer\OnuController as CustomerOnuController;
use App\Http\Controllers\Api\V1\Customer\PackageController as CustomerPackageController;
use App\Http\Controllers\Api\V1\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Api\V1\Mobile\RealtimeController;
use App\Http\Controllers\Api\V1\Mobile\SyncController;
use App\Http\Controllers\Api\V1\Staff\NetworkController;
use App\Http\Controllers\Api\V1\Staff\NocController;
use App\Http\Controllers\Api\V1\Staff\StaffDeviceController;
use App\Http\Controllers\Api\V1\Technician\InstallationController;
use App\Http\Controllers\Api\V1\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\Api\V1\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Api\V1\SupportTicketApiController;
use App\Http\Controllers\Api\V1\Technician\DeviceController as TechnicianDeviceController;
use App\Http\Controllers\Api\V1\Technician\FieldVisitController;
use App\Http\Controllers\Api\V1\Collector\CollectorController;
use App\Http\Middleware\EnsureSanctumCollector;
use App\Http\Middleware\EnsureSanctumCustomer;
use App\Http\Middleware\EnsureSanctumTechnician;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:webhooks')->group(function (): void {
    Route::post('/webhooks/support-ticket-ingest', [SupportTicketWebhookController::class, 'store'])
        ->name('api.webhooks.support-ticket-ingest');

    Route::post('/webhooks/payments/{gateway}', [PaymentWebhookController::class, 'store'])
        ->name('api.webhooks.payments');

    Route::post('/webhooks/netflow-ingest', [NetflowWebhookController::class, 'store'])
        ->name('api.webhooks.netflow-ingest');

    Route::post('/webhooks/onu-optical-ingest', [\App\Http\Controllers\Api\OnuOpticalWebhookController::class, 'store'])
        ->name('api.webhooks.onu-optical-ingest');

    Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
        ->name('api.webhooks.whatsapp.verify');
    Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
        ->name('api.webhooks.whatsapp');
});

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [ApiIndexController::class, 'show']);
    Route::get('/mobile/config', [MobileConfigController::class, 'show']);

    Route::post('/mfs/sms/ingest', [\App\Http\Controllers\Api\V1\MfsSmsIngestController::class, 'ingest'])
        ->middleware('throttle:120,1')
        ->name('api.mfs.sms.ingest');
    Route::post('/login', [\App\Http\Controllers\Api\V1\Mobile\AppAuthController::class, 'login'])->middleware('throttle:15,1');
    Route::post('/mobile/login', [UnifiedAuthController::class, 'login'])->middleware('throttle:15,1');

    // Staff (legacy + technician)
    Route::post('/auth/login', [StaffAuthController::class, 'login'])->middleware('throttle:15,1');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/me', [MeController::class, 'show']);
        Route::get('/staff/dashboard', [StaffDashboardController::class, 'show']);
        Route::post('/staff/mfs/sms/ingest', [\App\Http\Controllers\Api\V1\MfsSmsIngestController::class, 'ingestStaff']);
        Route::get('/staff/noc/dashboard', [NocController::class, 'dashboard']);
        Route::get('/staff/optical/noc', [\App\Http\Controllers\Api\V1\Staff\StaffOpticalNocController::class, 'dashboard']);
        Route::get('/staff/optical/onu/{device}/signals', [\App\Http\Controllers\Api\V1\Staff\StaffOpticalNocController::class, 'signalHistory'])->whereNumber('device');
        Route::get('/staff/optical/predictions', [\App\Http\Controllers\Api\V1\Staff\StaffOpticalNocController::class, 'predictions']);
        Route::get('/staff/optical/pon-ports', [\App\Http\Controllers\Api\V1\Staff\StaffOpticalNocController::class, 'ponPorts']);
        Route::get('/staff/optical/topology', [\App\Http\Controllers\Api\V1\Staff\StaffOpticalNocController::class, 'topology']);
        Route::get('/staff/optical/olts', [\App\Http\Controllers\Api\V1\Staff\StaffOpticalNocController::class, 'oltHealth']);
        Route::get('/staff/optical/olt/{device}/health', [\App\Http\Controllers\Api\V1\Staff\StaffOpticalNocController::class, 'oltHealthHistory'])->whereNumber('device');
        Route::get('/staff/monitoring/online', [StaffMonitoringController::class, 'index']);
        Route::get('/staff/monitoring/live', [StaffMonitoringController::class, 'live']);
        Route::get('/staff/billing/summary', [StaffBillingController::class, 'summary']);
        Route::get('/staff/billing/due', [StaffBillingController::class, 'due']);
        Route::get('/staff/billing/invoices', [StaffBillingController::class, 'invoices']);
        Route::get('/staff/billing/collections', [StaffBillingController::class, 'collections']);
        Route::get('/staff/billing/collection-options', [\App\Http\Controllers\Api\V1\Staff\StaffCollectionOptionsController::class, 'show']);
        Route::get('/staff/team/discounts', [\App\Http\Controllers\Api\V1\Staff\StaffTeamDiscountController::class, 'index']);
        Route::patch('/staff/team/{user}/discount', [\App\Http\Controllers\Api\V1\Staff\StaffTeamDiscountController::class, 'update'])->whereNumber('user');
        Route::get('/staff/customers/search', [CustomerSearchController::class, 'search']);
        Route::get('/staff/customers', [CustomerDetailController::class, 'index']);
        Route::patch('/staff/customers/{customer}', [StaffCustomerUpdateController::class, 'update'])->whereNumber('customer');
        Route::post('/staff/customers/{customer}/extend-service', [\App\Http\Controllers\Api\V1\Staff\StaffCustomerQuickActionsController::class, 'extendService'])->whereNumber('customer');
        Route::post('/staff/customers/{customer}/toggle-network', [\App\Http\Controllers\Api\V1\Staff\StaffCustomerQuickActionsController::class, 'toggleNetwork'])->whereNumber('customer');
        Route::get('/staff/tickets/assignees', [StaffTicketsController::class, 'assignees']);
        Route::get('/staff/tickets', [StaffTicketsController::class, 'index']);
        Route::post('/staff/tickets', [StaffTicketsController::class, 'store']);
        Route::get('/staff/tickets/{ticket}', [StaffTicketsController::class, 'show'])->whereNumber('ticket');
        Route::post('/staff/tickets/{ticket}/reply', [StaffTicketsController::class, 'reply'])->whereNumber('ticket');
        Route::patch('/staff/tickets/{ticket}', [StaffTicketsController::class, 'update'])->whereNumber('ticket');
        Route::get('/staff/tasks', [StaffTasksController::class, 'index']);
        Route::patch('/staff/tasks/{task}', [StaffTasksController::class, 'update'])->whereNumber('task');
        Route::get('/staff/approvals/pending', [StaffApprovalsController::class, 'index']);
        Route::post('/staff/approvals/expenses/{expense}/approve', [StaffApprovalsController::class, 'approveExpense'])->whereNumber('expense');
        Route::post('/staff/approvals/expenses/{expense}/reject', [StaffApprovalsController::class, 'rejectExpense'])->whereNumber('expense');
        Route::post('/staff/approvals/staff-expenses/{expense}/approve', [StaffApprovalsController::class, 'approveStaffExpense'])->whereNumber('expense');
        Route::post('/staff/approvals/staff-expenses/{expense}/reject', [StaffApprovalsController::class, 'rejectStaffExpense'])->whereNumber('expense');
        Route::get('/staff/customers/form-options', [StaffCustomerStoreController::class, 'formOptions']);
        Route::get('/staff/customer-packages', [StaffCustomerStoreController::class, 'packages']);
        Route::post('/staff/customers/create', [StaffCustomerStoreController::class, 'store']);
        Route::get('/staff/expense-categories', [StaffExpenseController::class, 'categories']);
        Route::get('/staff/expenses', [StaffExpenseController::class, 'index']);
        Route::post('/staff/expenses', [StaffExpenseController::class, 'store']);
        Route::get('/staff/payment-methods', [StaffPaymentsController::class, 'methods']);
        Route::post('/staff/payments', [StaffPaymentsController::class, 'store']);
        Route::get('/staff/payments/{payment}/receipt-pdf', [\App\Http\Controllers\Api\V1\Staff\StaffDocumentController::class, 'paymentReceiptPdf'])->whereNumber('payment');
        Route::delete('/staff/payments/{payment}', [StaffPaymentsController::class, 'destroy'])->whereNumber('payment');
        Route::get('/staff/invoices/{invoice}/pdf', [\App\Http\Controllers\Api\V1\Staff\StaffDocumentController::class, 'invoicePdf'])->whereNumber('invoice');
        Route::get('/staff/inventory/bootstrap', [\App\Http\Controllers\Api\V1\Staff\StaffInventoryController::class, 'bootstrap']);
        Route::get('/staff/inventory/products', [\App\Http\Controllers\Api\V1\Staff\StaffInventoryController::class, 'products']);
        Route::post('/staff/inventory/sales', [\App\Http\Controllers\Api\V1\Staff\StaffInventoryController::class, 'store']);
        Route::get('/staff/invoices/{invoice}/hardware-options', [\App\Http\Controllers\Api\V1\Staff\StaffInvoiceHardwareController::class, 'options'])->whereNumber('invoice');
        Route::get('/staff/invoices/{invoice}/hardware-product', [\App\Http\Controllers\Api\V1\Staff\StaffInvoiceHardwareController::class, 'lookupProduct'])->whereNumber('invoice');
        Route::post('/staff/invoices/{invoice}/hardware-line', [\App\Http\Controllers\Api\V1\Staff\StaffInvoiceHardwareController::class, 'store'])->whereNumber('invoice');
        Route::get('/staff/packages', [StaffPackagesController::class, 'index']);
        Route::post('/staff/packages', [StaffPackagesController::class, 'store']);
        Route::patch('/staff/packages/{package}', [StaffPackagesController::class, 'update'])->whereNumber('package');
        Route::get('/staff/reports/expiring', [StaffReportsController::class, 'expiring']);
        Route::get('/staff/reports/collections', [StaffReportsController::class, 'collections']);
        Route::get('/staff/reports/due', [StaffReportsController::class, 'due']);
        Route::post('/staff/customers/{customer}/sms-reminder', [StaffCommsController::class, 'smsReminder'])->whereNumber('customer');
        Route::post('/staff/sms/bulk-due', [StaffCommsController::class, 'smsBulkDue']);
        Route::post('/staff/notices/broadcast', [StaffCommsController::class, 'broadcastNotice']);
        Route::post('/staff/profile/password', [StaffProfileController::class, 'updatePassword']);
        Route::get('/staff/customers/{customer}/onu', [StaffOnuController::class, 'show'])->whereNumber('customer');
        Route::patch('/staff/customers/{customer}/onu', [StaffOnuController::class, 'update'])->whereNumber('customer');
        Route::post('/staff/customers/{customer}/activate-line', [StaffLineActivationController::class, 'store'])->whereNumber('customer');
        Route::get('/staff/online-clients', [StaffOnlineController::class, 'index']);
        Route::get('/staff/customers/{customer}', [CustomerDetailController::class, 'show'])->whereNumber('customer');
        Route::get('/staff/customers/{customer}/usage/live', [StaffCustomerUsageController::class, 'live'])->whereNumber('customer');
        Route::post('/staff/network/suspend', [NetworkController::class, 'suspend']);
        Route::post('/staff/network/reconnect', [NetworkController::class, 'reconnect']);
        Route::post('/staff/devices', [StaffDeviceController::class, 'register']);
        Route::post('/mobile/sync', [SyncController::class, 'push']);
        Route::get('/mobile/realtime', [RealtimeController::class, 'config']);
        Route::post('/support-tickets', [SupportTicketApiController::class, 'store']);
        Route::post('/auth/refresh', [TokenRefreshController::class, 'refreshStaff']);
        Route::post('/auth/logout', [StaffAuthController::class, 'logout']);

        Route::middleware(EnsureSanctumTechnician::class)->prefix('technician')->group(function (): void {
            Route::get('/field-visits', [FieldVisitController::class, 'index']);
            Route::get('/field-visits/{fieldVisit}', [FieldVisitController::class, 'show']);
            Route::patch('/field-visits/{fieldVisit}', [FieldVisitController::class, 'update']);
            Route::post('/devices', [TechnicianDeviceController::class, 'register']);
            Route::post('/installations', [InstallationController::class, 'store']);
        });

        Route::middleware(EnsureSanctumCollector::class)->prefix('collector')->group(function (): void {
            Route::get('/customers/search', [CollectorController::class, 'searchCustomers']);
            Route::get('/visits/today', [CollectorController::class, 'todayVisits']);
            Route::get('/wallet', [CollectorController::class, 'wallet']);
            Route::post('/collections', [CollectorController::class, 'storeCollection']);
            Route::get('/expense-categories', [CollectorController::class, 'expenseCategories']);
            Route::get('/expenses', [CollectorController::class, 'expenses']);
            Route::post('/expenses', [CollectorController::class, 'storeExpense']);
            Route::post('/settlements', [CollectorController::class, 'storeSettlement']);
            Route::post('/daily-closing', [CollectorController::class, 'storeDailyClosing']);
        });
    });

    // Reseller partner API (Sanctum token on Reseller model)
    Route::post('/reseller/login', [\App\Http\Controllers\Api\V1\Reseller\ResellerAuthController::class, 'login'])
        ->middleware('throttle:15,1');

    Route::middleware(['auth:sanctum', 'reseller.api', 'throttle:api'])->prefix('reseller')->group(function (): void {
        Route::get('/me', [\App\Http\Controllers\Api\V1\Reseller\ResellerAuthController::class, 'me']);
        Route::post('/logout', [\App\Http\Controllers\Api\V1\Reseller\ResellerAuthController::class, 'logout']);
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Reseller\ResellerApiDashboardController::class, 'show']);
        Route::get('/customers', [\App\Http\Controllers\Api\V1\Reseller\ResellerApiCustomerController::class, 'index']);
        Route::post('/customers', [\App\Http\Controllers\Api\V1\Reseller\ResellerApiCustomerController::class, 'store']);
        Route::get('/customers/{customer}', [\App\Http\Controllers\Api\V1\Reseller\ResellerApiCustomerController::class, 'show'])->whereNumber('customer');
        Route::patch('/customers/{customer}', [\App\Http\Controllers\Api\V1\Reseller\ResellerApiCustomerController::class, 'update'])->whereNumber('customer');
        Route::post('/customers/{customer}/payments', [\App\Http\Controllers\Api\V1\Reseller\ResellerApiPaymentController::class, 'store'])->whereNumber('customer');
        Route::get('/onu', [\App\Http\Controllers\Api\V1\Reseller\ResellerApiOnuController::class, 'index']);
        Route::get('/onu/{customer}', [\App\Http\Controllers\Api\V1\Reseller\ResellerApiOnuController::class, 'show'])->whereNumber('customer');
    });

    // Customer mobile app
    Route::prefix('customer')->group(function (): void {
        Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:15,1');

        Route::middleware(['auth:sanctum', EnsureSanctumCustomer::class])->group(function (): void {
            Route::post('/auth/refresh', [TokenRefreshController::class, 'refreshCustomer']);
            Route::post('/logout', [CustomerAuthController::class, 'logout']);
            Route::get('/me', [CustomerAuthController::class, 'me']);
            Route::get('/dashboard', [CustomerDashboardController::class, 'show']);
            Route::get('/bills/payables', [CustomerPaymentController::class, 'payables']);
            Route::get('/bills', [CustomerInvoiceController::class, 'index']);
            Route::get('/bills/{invoice}', [CustomerInvoiceController::class, 'show']);
            Route::get('/payments', [\App\Http\Controllers\Api\V1\Customer\PaymentHistoryController::class, 'index']);
            Route::post('/bills/{invoice}/pay', [CustomerPaymentController::class, 'initiate']);
            Route::get('/usage/live', [CustomerUsageController::class, 'live']);
            Route::get('/onu/status', [CustomerOnuController::class, 'status']);
            Route::post('/onu/reboot', [CustomerOnuController::class, 'reboot']);
            Route::post('/ai/ask', [CustomerAiController::class, 'ask']);
            Route::post('/profile/password', [CustomerProfileController::class, 'updatePassword']);
            Route::get('/packages', [CustomerPackageController::class, 'index']);
            Route::post('/packages/change', [CustomerPackageController::class, 'requestChange']);
            Route::get('/tickets', [CustomerTicketController::class, 'index']);
            Route::post('/tickets', [CustomerTicketController::class, 'store']);
            Route::get('/tickets/{ticket}', [CustomerTicketController::class, 'show']);
            Route::post('/tickets/{ticket}/reply', [CustomerTicketController::class, 'reply']);
            Route::post('/devices', [CustomerDeviceController::class, 'register']);
            Route::delete('/devices', [CustomerDeviceController::class, 'unregister']);
        });
    });
});
