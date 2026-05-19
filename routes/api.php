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
use App\Http\Controllers\Api\V1\Staff\AuthController as StaffAuthController;
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
  // Staff (legacy + technician)
    Route::post('/auth/login', [StaffAuthController::class, 'login'])->middleware('throttle:15,1');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/me', [MeController::class, 'show']);
        Route::post('/support-tickets', [SupportTicketApiController::class, 'store']);
        Route::post('/auth/logout', [StaffAuthController::class, 'logout']);

        Route::middleware(EnsureSanctumTechnician::class)->prefix('technician')->group(function (): void {
            Route::get('/field-visits', [FieldVisitController::class, 'index']);
            Route::get('/field-visits/{fieldVisit}', [FieldVisitController::class, 'show']);
            Route::patch('/field-visits/{fieldVisit}', [FieldVisitController::class, 'update']);
            Route::post('/devices', [TechnicianDeviceController::class, 'register']);
        });

        Route::middleware(EnsureSanctumCollector::class)->prefix('collector')->group(function (): void {
            Route::get('/customers/search', [CollectorController::class, 'searchCustomers']);
            Route::get('/visits/today', [CollectorController::class, 'todayVisits']);
            Route::get('/wallet', [CollectorController::class, 'wallet']);
            Route::post('/collections', [CollectorController::class, 'storeCollection']);
            Route::post('/expenses', [CollectorController::class, 'storeExpense']);
            Route::post('/settlements', [CollectorController::class, 'storeSettlement']);
            Route::post('/daily-closing', [CollectorController::class, 'storeDailyClosing']);
        });
    });

    // Customer mobile app
    Route::prefix('customer')->group(function (): void {
        Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:15,1');

        Route::middleware(['auth:sanctum', EnsureSanctumCustomer::class])->group(function (): void {
            Route::post('/logout', [CustomerAuthController::class, 'logout']);
            Route::get('/me', [CustomerAuthController::class, 'me']);
            Route::get('/dashboard', [CustomerDashboardController::class, 'show']);
            Route::get('/bills', [CustomerInvoiceController::class, 'index']);
            Route::get('/bills/{invoice}', [CustomerInvoiceController::class, 'show']);
            Route::post('/bills/{invoice}/pay', [CustomerPaymentController::class, 'initiate']);
            Route::get('/usage/live', [CustomerUsageController::class, 'live']);
            Route::get('/tickets', [CustomerTicketController::class, 'index']);
            Route::post('/tickets', [CustomerTicketController::class, 'store']);
            Route::get('/tickets/{ticket}', [CustomerTicketController::class, 'show']);
            Route::post('/tickets/{ticket}/reply', [CustomerTicketController::class, 'reply']);
            Route::post('/devices', [CustomerDeviceController::class, 'register']);
            Route::delete('/devices', [CustomerDeviceController::class, 'unregister']);
        });
    });
});
