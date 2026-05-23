<?php

use App\Http\Controllers\Admin\AdminSessionLoginController;
use App\Http\Controllers\Admin\GoogleDriveOAuthController;
use App\Http\Controllers\Admin\PlatformBackupDownloadController;
use App\Http\Controllers\HotspotPortalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\BillPaymentController;
use App\Http\Controllers\BkashPaymentController;
use App\Http\Controllers\NagadPaymentController;
use App\Http\Controllers\SslCommerzPaymentController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\Portal\PortalAccountController;
use App\Http\Controllers\Portal\PortalBillController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalNotificationController;
use App\Http\Controllers\Portal\PortalOnuController;
use App\Http\Controllers\Portal\PortalPackageController;
use App\Http\Controllers\Portal\PortalSpeedTestController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\Portal\PortalUsageController;
use App\Http\Controllers\Portal\PortalEquipmentController;
use App\Http\Controllers\Portal\PortalInvoiceController;
use App\Http\Controllers\Portal\PortalInvoicePaymentController;
use App\Http\Controllers\Portal\PortalKnowledgeController;
use App\Http\Controllers\Portal\PortalLiveChatController;
use App\Http\Controllers\Portal\PortalLoginController;
use App\Http\Controllers\Portal\PortalSignupController;
use App\Http\Controllers\Portal\PortalPaymentController;
use App\Http\Controllers\Portal\PortalTicketController;
use App\Http\Controllers\Reseller\ResellerCommissionController;
use App\Http\Controllers\Reseller\ResellerCustomerController;
use App\Http\Controllers\Reseller\ResellerDashboardController;
use App\Http\Controllers\Reseller\ResellerLoginController;
use App\Http\Controllers\Reseller\ResellerCustomerManageController;
use App\Http\Controllers\Reseller\ResellerOnuController;
use App\Http\Controllers\Reseller\ResellerPaymentController;
use App\Http\Controllers\Reseller\ResellerRealtimeController;
use App\Http\Controllers\Reseller\ResellerSettlementController;
use App\Http\Controllers\Reseller\ResellerTwoFactorController;
use App\Http\Controllers\Reseller\ResellerWalletController;
use App\Support\ResellerPortalPermission;
use App\Http\Controllers\PipraPayPaymentController;
use App\Http\Controllers\RocketPaymentController;
use App\Http\Controllers\InventoryShopController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Webhooks\KhudeBartaDlrController;
use Illuminate\Support\Facades\Route;

$landingDomain = config('domains.landing');
$adminDomain = config('domains.admin');

// Legacy Filament URLs (resource slug is now `subscribers`).
// 308 (not 301) keeps POST method/body when following the redirect (old tabs posting to /admin/customers/.../edit).
Route::middleware(['web', 'auth'])->prefix('admin')->group(function (): void {
    Route::get('/system/backups/{id}/download', PlatformBackupDownloadController::class)
        ->name('admin.backups.download');
    Route::get('/google-drive/connect', [GoogleDriveOAuthController::class, 'connect'])
        ->name('admin.google-drive.connect');
    Route::get('/google-drive/callback', [GoogleDriveOAuthController::class, 'callback'])
        ->name('admin.google-drive.callback');
});

Route::redirect('/admin/customers', '/admin/subscribers', 308);
Route::redirect('/admin/customers/{path}', '/admin/subscribers/{path}', 308)->where('path', '.+');

// Primary admin login (HTML form — does not depend on Livewire / Rocket Loader).
Route::post('/admin/login', AdminSessionLoginController::class)
    ->middleware(['web', 'throttle:20,1'])
    ->name('admin.login.session');

// ISP Digital legacy URLs
Route::redirect('/AutomaticProcess', '/admin/automatic-processes', 302);
Route::redirect('/AutomaticProcess/Index', '/admin/automatic-processes', 302);
Route::redirect('/AutomaticProcess/{path}', '/admin/automatic-processes', 302)->where('path', '.*');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::middleware('throttle:60,1')->prefix('shop')->name('shop.')->group(function (): void {
    Route::get('/', [InventoryShopController::class, 'index'])->name('index');
    Route::post('/checkout', [InventoryShopController::class, 'checkout'])->name('checkout');
});

Route::get('/webhooks/sms/khudebarta/dlr', KhudeBartaDlrController::class)
    ->name('webhooks.sms.khudebarta.dlr');

Route::middleware('throttle:30,1')->prefix('rocket')->name('rocket.')->group(function (): void {
    Route::get('/pay', [RocketPaymentController::class, 'checkout'])->name('checkout');
    Route::post('/confirm', [RocketPaymentController::class, 'confirm'])->name('confirm');
});

Route::middleware('throttle:30,1')->prefix('mfs')->name('mfs.personal.')->group(function (): void {
    Route::get('/{gateway}/pay', [\App\Http\Controllers\PersonalMfsPaymentController::class, 'checkout'])->name('checkout');
    Route::post('/{gateway}/confirm', [\App\Http\Controllers\PersonalMfsPaymentController::class, 'confirm'])->name('confirm');
});

Route::middleware(['web', 'auth'])->prefix('admin')->group(function (): void {
    Route::get('/smart-search', \App\Http\Controllers\Admin\SmartSearchController::class)->name('admin.smart-search');
    Route::get('/dashboard-stream', \App\Http\Controllers\Admin\DashboardStreamController::class)->name('admin.dashboard-stream');
});

Route::middleware('auth')->get('/admin/reseller-commissions/{commission}/statement', [\App\Http\Controllers\ResellerCommissionStatementController::class, 'show'])
    ->name('admin.reseller-commissions.statement');

Route::middleware('auth')->get('/admin/hotspot-vouchers/print', [\App\Http\Controllers\HotspotVoucherPrintController::class, 'show'])
    ->name('admin.hotspot-vouchers.print');

Route::middleware('auth')->get('/admin/invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])
    ->name('invoices.pdf');

Route::middleware('auth')->get('/admin/payments/{payment}/receipt', [PaymentReceiptController::class, 'show'])
    ->name('payments.receipt');

Route::middleware('auth')->get('/collector', function () {
    return redirect(\App\Filament\Pages\CollectorMobile::getUrl());
})->name('collector.pwa');

Route::middleware(['guest:reseller', 'throttle:15,1'])->group(function () {
    Route::get('/reseller/login', [ResellerLoginController::class, 'create'])->name('reseller.login');
    Route::post('/reseller/login', [ResellerLoginController::class, 'store'])->name('reseller.login.store');
});

Route::middleware(['auth:reseller', 'reseller.2fa'])->prefix('reseller')->name('reseller.')->group(function () {
    Route::get('/two-factor/challenge', [ResellerTwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [ResellerTwoFactorController::class, 'verifyChallenge'])->name('two-factor.verify');
    Route::get('/two-factor/setup', [ResellerTwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [ResellerTwoFactorController::class, 'confirmSetup'])->name('two-factor.confirm');

    Route::get('/', [ResellerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/realtime/config', [ResellerRealtimeController::class, 'config'])->name('realtime.config');
    Route::get('/realtime/poll', [ResellerRealtimeController::class, 'poll'])->name('realtime.poll');
    Route::get('/customers', [ResellerCustomerController::class, 'index'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::CUSTOMER_VIEW)
        ->name('customers.index');
    Route::get('/customers/create', [ResellerCustomerManageController::class, 'create'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::CUSTOMER_CREATE)
        ->name('customers.create');
    Route::post('/customers', [ResellerCustomerManageController::class, 'store'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::CUSTOMER_CREATE)
        ->name('customers.store');
    Route::get('/customers/{customer}', [ResellerCustomerManageController::class, 'show'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::CUSTOMER_VIEW)
        ->name('customers.show');
    Route::get('/customers/{customer}/edit', [ResellerCustomerManageController::class, 'edit'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::CUSTOMER_EDIT)
        ->name('customers.edit');
    Route::put('/customers/{customer}', [ResellerCustomerManageController::class, 'update'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::CUSTOMER_EDIT)
        ->name('customers.update');
    Route::get('/customers/{customer}/collect', [ResellerPaymentController::class, 'create'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::PAYMENT_COLLECT)
        ->name('customers.collect');
    Route::post('/customers/{customer}/collect', [ResellerPaymentController::class, 'store'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::PAYMENT_COLLECT)
        ->name('customers.collect.store');
    Route::get('/onu', [ResellerOnuController::class, 'index'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::ONU_VIEW)
        ->name('onu.index');
    Route::get('/onu/{customer}', [ResellerOnuController::class, 'show'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::ONU_VIEW)
        ->name('onu.show');
    Route::get('/commissions', [ResellerCommissionController::class, 'index'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::COMMISSION_VIEW)
        ->name('commissions.index');
    Route::get('/wallet', [ResellerWalletController::class, 'index'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::WALLET_VIEW)
        ->name('wallet.index');
    Route::get('/settlements', [ResellerSettlementController::class, 'index'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::SETTLEMENT_MANAGE)
        ->name('settlements.index');
    Route::post('/settlements', [ResellerSettlementController::class, 'store'])
        ->middleware('reseller.permission:'.ResellerPortalPermission::SETTLEMENT_MANAGE)
        ->name('settlements.store');
    Route::post('/logout', [ResellerLoginController::class, 'destroy'])->name('logout');
});

Route::middleware(['guest:customer', 'throttle:15,1'])->group(function () {
    Route::get('/portal/signup', [PortalSignupController::class, 'create'])->name('portal.signup');
    Route::post('/portal/signup', [PortalSignupController::class, 'store'])->name('portal.signup.store');
    Route::get('/portal/signup/success', [PortalSignupController::class, 'success'])->name('portal.signup.success');
    Route::redirect('/signup', '/portal/signup', 301);
});

Route::middleware(['portal.enabled', 'guest:customer', 'throttle:15,1'])->group(function () {
    Route::get('/login', [PortalLoginController::class, 'create'])->name('portal.login');
    Route::post('/login', [PortalLoginController::class, 'store'])->name('portal.login.store');
    Route::get('/login/otp', [PortalLoginController::class, 'otpForm'])->name('portal.login.otp');
    Route::post('/login/otp', [PortalLoginController::class, 'otpVerify'])->name('portal.login.otp.verify');
    Route::redirect('/portal/login', '/login', 301);
    Route::redirect('/portal/login/otp', '/login/otp', 301);
});

Route::middleware('throttle:30,1')->prefix('hotspot')->name('hotspot.')->group(function (): void {
    Route::get('/', [HotspotPortalController::class, 'index'])->name('index');
    Route::post('/redeem', [HotspotPortalController::class, 'redeem'])->name('redeem');
});

Route::middleware(['portal.enabled', 'auth:customer'])->group(function () {
    Route::get('/portal', [PortalDashboardController::class, 'index'])->name('portal.dashboard');
    Route::get('/portal/dashboard/live', [PortalDashboardController::class, 'live'])->name('portal.dashboard.live');
    Route::post('/portal/logout', [PortalLoginController::class, 'destroy'])->name('portal.logout');
    Route::get('/portal/bills', [PortalBillController::class, 'index'])->name('portal.bills.index');
    Route::get('/portal/invoices', [PortalInvoiceController::class, 'index'])->name('portal.invoices.index');
    Route::get('/portal/invoices/{invoice}', [PortalInvoiceController::class, 'show'])->name('portal.invoices.show');
    Route::get('/portal/invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('portal.invoices.pdf');
    Route::post('/portal/invoices/{invoice}/pay', [PortalInvoicePaymentController::class, 'store'])->name('portal.invoices.pay');
    Route::get('/portal/invoices/{invoice}/pay', function (\App\Models\Invoice $invoice) {
        return redirect()->route('portal.invoices.show', $invoice);
    });
    Route::get('/portal/packages', [PortalPackageController::class, 'index'])->name('portal.packages.index');
    Route::post('/portal/packages/request', [PortalPackageController::class, 'requestChange'])->name('portal.packages.request');
    Route::get('/portal/profile', [PortalProfileController::class, 'index'])->name('portal.profile.index');
    Route::post('/portal/profile', [PortalProfileController::class, 'update'])->name('portal.profile.update');
    Route::get('/portal/payments', [PortalPaymentController::class, 'index'])->name('portal.payments.index');
    Route::get('/portal/payments/{payment}/receipt', [PaymentReceiptController::class, 'show'])->name('portal.payments.receipt');
    Route::get('/portal/usage', [PortalUsageController::class, 'index'])->name('portal.usage.index');
    Route::get('/portal/usage/live', [PortalUsageController::class, 'live'])->name('portal.usage.live');
    Route::get('/portal/onu', [PortalOnuController::class, 'index'])->name('portal.onu.index');
    Route::get('/portal/onu/live', [PortalOnuController::class, 'live'])->name('portal.onu.live');
    Route::get('/portal/notifications', [PortalNotificationController::class, 'index'])->name('portal.notifications.index');
    Route::get('/portal/speed-test', [PortalSpeedTestController::class, 'index'])->name('portal.speed-test.index');
    Route::get('/portal/speed-test/ping', [PortalSpeedTestController::class, 'ping'])->name('portal.speed-test.ping');
    Route::get('/portal/speed-test/download', [PortalSpeedTestController::class, 'download'])->name('portal.speed-test.download');
    Route::post('/portal/speed-test/upload', [PortalSpeedTestController::class, 'upload'])->name('portal.speed-test.upload');
    Route::get('/portal/account/password', [PortalAccountController::class, 'editPassword'])->name('portal.account.password');
    Route::post('/portal/account/password', [PortalAccountController::class, 'updatePassword'])->name('portal.account.password.update');
    Route::get('/portal/equipment', [PortalEquipmentController::class, 'index'])->name('portal.equipment.index');
    Route::get('/portal/tickets', [PortalTicketController::class, 'index'])->name('portal.tickets.index');
    Route::get('/portal/tickets/create', [PortalTicketController::class, 'create'])->name('portal.tickets.create');
    Route::post('/portal/tickets', [PortalTicketController::class, 'store'])->name('portal.tickets.store');
    Route::get('/portal/tickets/{ticket}', [PortalTicketController::class, 'show'])->name('portal.tickets.show');
    Route::post('/portal/tickets/{ticket}/reply', [PortalTicketController::class, 'reply'])->name('portal.tickets.reply');
    Route::post('/portal/tickets/{ticket}/rate', [PortalTicketController::class, 'rate'])->name('portal.tickets.rate');
    Route::get('/portal/kb', [PortalKnowledgeController::class, 'index'])->name('portal.kb.index');
    Route::get('/portal/kb/{slug}', [PortalKnowledgeController::class, 'show'])->name('portal.kb.show');
    Route::get('/portal/live-chat', [PortalLiveChatController::class, 'index'])->name('portal.live-chat');
    Route::post('/portal/live-chat/start', [PortalLiveChatController::class, 'start'])->name('portal.live-chat.start');
});

Route::middleware('auth')->get('/admin/invoices/{invoice}/bkash/pay', [BkashPaymentController::class, 'initiate'])
    ->name('bkash.invoice.initiate');

Route::get('/bkash/callback', [BkashPaymentController::class, 'callback'])
    ->name('bkash.callback');

Route::get('/sslcommerz/success', [SslCommerzPaymentController::class, 'success'])->name('sslcommerz.success');
Route::get('/sslcommerz/fail', [SslCommerzPaymentController::class, 'fail'])->name('sslcommerz.fail');
Route::get('/sslcommerz/cancel', [SslCommerzPaymentController::class, 'cancel'])->name('sslcommerz.cancel');

Route::get('/nagad/callback', [NagadPaymentController::class, 'callback'])->name('nagad.callback');

Route::middleware('throttle:60,1')->prefix('piprapay')->name('piprapay.')->group(function (): void {
    Route::get('/success', [PipraPayPaymentController::class, 'success'])->name('success');
    Route::get('/cancel', [PipraPayPaymentController::class, 'cancel'])->name('cancel');
    Route::post('/webhook', [PipraPayPaymentController::class, 'webhook'])->name('webhook');
});

Route::middleware('throttle:30,1')->prefix('pay')->name('bill-payment.')->group(function (): void {
    Route::get('/', [BillPaymentController::class, 'index'])->name('index');
    Route::get('/l/{token}', [BillPaymentController::class, 'openLink'])->name('link');
    Route::post('/lookup', [BillPaymentController::class, 'lookup'])->name('lookup');
    Route::get('/verify', [BillPaymentController::class, 'verify'])->name('verify');
    Route::post('/verify', [BillPaymentController::class, 'verifySubmit'])->name('verify.submit');
    Route::post('/verify/resend', [BillPaymentController::class, 'resendOtp'])->name('verify.resend');
    Route::get('/invoice', [BillPaymentController::class, 'invoice'])->name('invoice');
    Route::get('/invoice/{invoice}/pdf', [BillPaymentController::class, 'invoicePdf'])->name('invoice.pdf');
    Route::post('/invoice/{invoice}/pay', [BillPaymentController::class, 'pay'])->name('pay');
    Route::post('/wallet', [BillPaymentController::class, 'walletTopup'])->name('wallet');
    Route::post('/payment-link', [BillPaymentController::class, 'createPaymentLink'])->name('payment-link.create');
    Route::post('/payment-link/{paymentLink}/sms', [BillPaymentController::class, 'sendPaymentLinkSms'])->name('payment-link.sms');
    Route::get('/receipt/{payment}', [BillPaymentController::class, 'receipt'])->name('receipt');
    Route::post('/reset', [BillPaymentController::class, 'reset'])->name('reset');
});

Route::redirect('/BillPayment/Index', '/pay');
Route::redirect('/bill-payment', '/pay');

if (filled($landingDomain)) {
    Route::domain($landingDomain)->group(function (): void {
        Route::get('/', LandingPageController::class)->name('landing.home');
    });
}

if (filled($adminDomain)) {
    Route::domain($adminDomain)->group(function (): void {
        Route::get('/', fn () => redirect('/admin'));
        Route::permanentRedirect('/login', '/admin/login');
    });
}

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/admin');
    }

    $host = request()->getHost();
    $landingHost = (string) config('domains.landing');
    $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: $host;
    $isLandingHost = $landingHost !== ''
        && ($host === $landingHost || $host === $appHost || str_ends_with($host, '.'.ltrim($landingHost, '.')));

    if ($isLandingHost) {
        return app(LandingPageController::class)();
    }

    return redirect()->route('bill-payment.index');
});

Route::redirect('/app', \App\Support\MobileAppLinks::downloadUrl());
Route::get('/mobile-app', fn () => redirect(\App\Support\MobileAppLinks::downloadUrl()))->name('mobile.app');
