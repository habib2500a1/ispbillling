<?php

use App\Http\Controllers\Admin\ProfitSummaryController;
use App\Http\Controllers\Admin\ResellerController;
use App\Livewire\Admin\ExpenseManager;
use App\Livewire\Admin\ActivityLogViewer;
use App\Livewire\Admin\ManagePurchaseRequests;
use App\Livewire\Admin\LoginLogViewer;
use App\Livewire\Admin\ManageReviews;
use App\Livewire\Admin\AdminVoucherList;
use App\Livewire\Admin\SystemLogViewer;
use App\Livewire\Admin\ManageSaasOperators;
use App\Livewire\Admin\StaffCashDesk;
use App\Http\Controllers\SaasLockedController;
use App\Http\Controllers\SaasTlsAskController;
use App\Services\Saas\SaasDomain;
use App\Http\Controllers\CollectionInvoiceController;
use App\Http\Controllers\CollectionReportController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MikrotikImportController;
use App\Http\Controllers\MainSiteController;
use App\Http\Controllers\PublicPayController;
use App\Http\Controllers\RouterListController;
use App\Livewire\AdminControlCenter;
use App\Livewire\AccountsHub;
use App\Livewire\AutomaticProcesses;
use App\Livewire\CustomerExcelUpload;
use App\Livewire\FeatureModulePage;
use App\Livewire\GroupHub;
use App\Livewire\IspOsHub;
use App\Livewire\BandwidthHub;
use App\Livewire\BillingNotices;
use App\Livewire\HrHub;
use App\Livewire\InventoryHub;
use App\Livewire\InventoryPurchases;
use App\Livewire\InventorySales;
use App\Livewire\OltManager;
use App\Livewire\OnuManager;
use App\Livewire\SmsNotices;
use App\Http\Controllers\Payment\BkashPaymentController;
use App\Http\Controllers\Payment\NagadPaymentController;
use App\Http\Controllers\Payment\SslCommerzPaymentController;
use App\Http\Controllers\Portal\PortalVoucherController;
use App\Http\Controllers\Reseller\ResellerDashboardController;
use App\Livewire\AddressSetup;
use App\Livewire\Admin\ManageRole;
use App\Livewire\Admin\ManageTickets;
use App\Livewire\Admin\ManageUser;
use App\Livewire\CollectionEdit;
use App\Livewire\CommentSubmit;
use App\Livewire\CustomerList;
use App\Livewire\CustomerSummary;
use App\Livewire\CustomerView;
use App\Livewire\EditCustomer;
use App\Livewire\MainSiteSetup;
use App\Livewire\Mikrotik\BackupManager;
use App\Livewire\Mikrotik\FirewallSetup;
use App\Livewire\Mikrotik\HotspotManager;
use App\Livewire\Mikrotik\InterfaceSetup;
use App\Livewire\Mikrotik\IpSetup;
use App\Livewire\Mikrotik\PppoeSetup;
use App\Livewire\Mikrotik\QueueSetup;
use App\Livewire\Mikrotik\RadiusSetup;
use App\Livewire\Mikrotik\RouterLogViewer;
use App\Livewire\Mikrotik\TrafficMonitor;
use App\Livewire\Mikrotik\VpnSetup;
use App\Livewire\Mikrotik\WalledGardenSetup;
use App\Livewire\MikrotikSync;
use App\Livewire\NewCustomer;
use App\Livewire\NotificationListAll;
use App\Livewire\PackageListSetup;
use App\Livewire\Payment\Invoice;
use App\Livewire\PaymentCollection;
use App\Livewire\Report\DisReport;
use App\Livewire\Reseller\ResellerCustomerList;
use App\Livewire\Reseller\ResellerPackageManagement;
use App\Livewire\Reseller\ResellerVoucherManagement;
use App\Livewire\Reseller\ResellerWalletManagement;
use App\Livewire\SMSSetup;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Extract domain host from APP_URL for consistent subdomain routing
$baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url');

Route::get('/saas/tls-ask', SaasTlsAskController::class)->name('saas.tls-ask');

// Unknown hosts (raw IP / expired NAT) stay on the warning page.
// Registered ISP domains and the platform host serve the full app.
$currentHost = request()->getHost();
if ($currentHost && ! SaasDomain::isAllowedHost($currentHost)) {
    Route::any('{any}', function () {
        return redirect()->away(config('app.url') . '/warning');
    })->where('any', '.*');
}

Route::get('/', [MainSiteController::class, 'index'])->name('welcome');
Route::get('/all-packages', [MainSiteController::class, 'allPackages'])->name('all-packages');
Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'bn'])) {
        session()->put('main_site_locale', $locale);
    }

    return redirect()->back();
})->name('welcome.lang');

Route::get('/warning', function () {
    return view('warning');
})->name('warning');

Route::get('/pay', [PublicPayController::class, 'lookup'])->name('pay.lookup');
Route::post('/pay', [PublicPayController::class, 'find'])->middleware('throttle:20,1')->name('pay.find');
Route::get('/pay/{ref}', [PublicPayController::class, 'show'])->where('ref', '[A-Za-z0-9._-]+')->name('pay.show');
Route::post('/pay/{ref}/checkout', [PublicPayController::class, 'checkout'])->middleware('throttle:10,1')->name('pay.checkout');
Route::get('/pay/start/bkash', [BkashPaymentController::class, 'initiate'])->name('pay.start.bkash');
Route::get('/pay/start/nagad', [NagadPaymentController::class, 'initiate'])->name('pay.start.nagad');
Route::get('/pay/start/sslcommerz', [SslCommerzPaymentController::class, 'initiate'])->name('pay.start.sslcommerz');
Route::any('/pay/callback/bkash', [BkashPaymentController::class, 'callback'])->name('pay.callback.bkash');
Route::any('/pay/callback/nagad', [NagadPaymentController::class, 'callback'])->name('pay.callback.nagad');
Route::any('/pay/callback/sslcommerz', [SslCommerzPaymentController::class, 'callback'])->name('pay.callback.sslcommerz');

Route::get('/recharge/voucher', [PortalVoucherController::class, 'showRechargeForm'])->name('welcome.voucher.recharge');
Route::post('/recharge/voucher', [PortalVoucherController::class, 'redeem'])->name('welcome.voucher.redeem');

Route::get('/portal', function () {
    return redirect()->to(portalLoginUrl());
})->name('portal.home');

Route::get('/portal/access/{token}', [CustomerPortalController::class, 'accessToken'])
    ->name('portal.access.token');

Route::get('/billing', function () {
    return redirect('/dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'restrict.profile',
])->group(function () {
    Route::get('/system/db-backup/download/{filename}', function ($filename) {
            if (str_contains($filename, '/') || str_contains($filename, '\\')) {
                abort(403, 'Invalid filename.');
            }
            $path = base_path('backups/'.$filename);
            if (file_exists($path)) {
                return response()->download($path);
            }
            abort(404, 'Backup file not found.');
        })->name('system.db-backup.download');

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/subscription-locked', SaasLockedController::class)->name('saas.locked');

        Route::get('/admin-center', AdminControlCenter::class)->name('admin-center');
        Route::get('/isp-os', IspOsHub::class)->name('isp-os');
        Route::get('/isp/{module}', FeatureModulePage::class)->name('isp.module');
        Route::get('/hub/{group}', GroupHub::class)->name('group-hub')->where('group', '[a-z0-9\-]+');
        Route::resources([
            'collection-report' => CollectionReportController::class,
        ]);
        Route::get('search/live', [GlobalSearchController::class, 'customers'])->name('search.live');
        Route::match(['get', 'post'], 'customers/data', [CustomerList::class, 'getData'])->name('customers.data');
        Route::get('customers/{id}/edit', [CustomerList::class, 'edit'])->name('customers.edit');
        Route::get('customers/{id}/portal-login', [CustomerPortalController::class, 'login'])->name('customers.portal-login');
        Route::post('customers/{id}/portal-token', [CustomerPortalController::class, 'regenerateToken'])->name('customers.portal-token.regenerate');
        Route::get('/subscriber-portal-login/{customer}', [CustomerPortalController::class, 'loginById'])
            ->name('staff.subscribers.portal-login');
        Route::get('customers/{id}', CustomerView::class)->name('customers.show');
        Route::patch('customers/{id}', [CustomerList::class, 'update'])->name('customers.update');
        Route::get('customers', CustomerList::class)->name('customers.index');

        Route::get('/new/customers', CustomerList::class)->name('customers-new');
        Route::get('/admin-users', ManageUser::class)->name('admin-users');
        Route::get('/admin-roles', ManageRole::class)->name('admin-roles');
        Route::get('/support-tickets', ManageTickets::class)->name('admin-tickets');
        Route::get('/mikrotik', MikrotikSync::class)->name('mikrotik-sync');
        Route::post('/mikrotik', [RouterListController::class, 'store'])->name('mikrotik.store');
        Route::delete('/mikrotik/{id}', [RouterListController::class, 'destroy'])->name('mikrotik.destroy');
        Route::get('/mikrotik/{id}', function (int $id) {
            return redirect()->route('mikrotik-sync', ['edit' => $id]);
        })->whereNumber('id');
        Route::get('/mikrotik/{id}/import', [MikrotikImportController::class, 'show'])->name('mikrotik.import');
        Route::post('/mikrotik/{id}/import', [MikrotikImportController::class, 'store'])->name('mikrotik.import.store');
        Route::get('/online-clients', \App\Livewire\OnlineClients::class)->name('online-clients');

        // Optical / OLT (Phase 1)
        Route::get('/olts', OltManager::class)->name('olt-management');
        Route::get('/onus', OnuManager::class)->name('onu-management');
        Route::get('/noc', fn () => redirect()->route('dashboard'))->name('noc-overview');

        // Billing extras (Phase 3)
        Route::get('/billing-notices', BillingNotices::class)->name('billing-notices');
        Route::get('/sms-notices', SmsNotices::class)->name('sms-notices');
        Route::get('/automatic-processes', AutomaticProcesses::class)->name('automatic-processes');

        // NOC / Bandwidth (Phase 4)
        Route::get('/bandwidth-hub', BandwidthHub::class)->name('bandwidth-hub');
        Route::get('/noc-outage', fn () => redirect()->route('dashboard'))->name('noc-outage');

        // Ops hubs (Phase 5)
        Route::get('/accounts-hub', AccountsHub::class)->name('accounts-hub');
        Route::get('/hr-hub', HrHub::class)->name('hr-hub');
        Route::get('/call-desk', fn () => redirect()->route('dashboard'))->name('call-desk');
        Route::get('/inventory-hub', InventoryHub::class)->name('inventory-hub');
        Route::get('/inventory-purchases', InventoryPurchases::class)->name('inventory-purchases');
        Route::get('/inventory-sales', InventorySales::class)->name('inventory-sales');
        Route::get('/ops-insights', fn () => redirect()->route('dashboard'))->name('ops-insights');

        // Mikrotik Setup Routes
        Route::prefix('mikrotik-setup')->group(function () {
            Route::get('/ip', IpSetup::class)->name('mikrotik-ip-setup');
            Route::get('/pppoe', PppoeSetup::class)->name('mikrotik-pppoe-setup');
            Route::get('/queue', QueueSetup::class)->name('mikrotik-queue-setup');
            Route::get('/firewall', FirewallSetup::class)->name('mikrotik-firewall-setup');
            Route::get('/hotspot', HotspotManager::class)->name('mikrotik-hotspot-setup'); // merged → HotspotManager
            Route::get('/hotspot-manager', HotspotManager::class)->name('mikrotik-hotspot-manager');
            Route::get('/radius', RadiusSetup::class)->name('mikrotik-radius-setup');
            Route::get('/vpn', VpnSetup::class)->name('mikrotik-vpn-setup');
            Route::get('/interface', InterfaceSetup::class)->name('mikrotik-interface-setup');
            Route::get('/traffic', TrafficMonitor::class)->name('mikrotik-traffic-monitor');
            Route::get('/logs', RouterLogViewer::class)->name('mikrotik-log-viewer');
            Route::get('/backup', BackupManager::class)->name('mikrotik-backup-setup');
            Route::get('/walled-garden', WalledGardenSetup::class)->name('mikrotik-walled-garden');
        });

        Route::get('/address', AddressSetup::class)->name('address-setup');
        Route::get('/packages', PackageListSetup::class)->name('package-list-setup');
        Route::get('/sms', SMSSetup::class)->name('sms-setup');
        Route::get('/create-customer', NewCustomer::class)->name('new-customer');
        Route::get('/upload-users', CustomerExcelUpload::class)->name('customers.excel-upload');

        // payment routes
        Route::get('/payment-collection', PaymentCollection::class)->name('payment-collection');
        Route::get('/payment-collection-edit', CollectionEdit::class)->name('collection-edit');
        Route::get('/payment-invoice', Invoice::class)->name('payment-invoice');
        Route::get('/invoices/{id}', [CollectionInvoiceController::class, 'show'])->name('collection-invoice.show')->whereNumber('id');
        Route::get('/invoices/{id}/download', [CollectionInvoiceController::class, 'download'])->name('collection-invoice.download')->whereNumber('id');

        // all report
        Route::get('/customer-summary', CustomerSummary::class)->name('customer-summary');
        Route::get('/report/dis-report-table', [DisReport::class, 'getData'])->name('dis-report-table');
        Route::get('/report/dis-report', DisReport::class)->name('dis-report');

        // site settings (consolidated)
        Route::get('/site-settings', MainSiteSetup::class)
            ->middleware(DispatchServingFilamentEvent::class)
            ->name('site-settings');

        // main site content management (deprecate name but keeps route if needed or redirection)
        Route::get('/main-site-setup', function () {
            return redirect()->route('site-settings');
        });

        Route::get('/all-notifications', NotificationListAll::class)->name('notifications');
        // Route::get('/edit-customer', EditCustomer::class);
        // Route::get('/customers', CustomerList::class);

        Route::get('import-form', [ImportController::class, 'importForm'])->name('import.form');
        Route::post('collection-form', [ImportController::class, 'collectionForm'])->name('collection.form');
        Route::post('monthly-bill-form', [ImportController::class, 'monthlyBillForm'])->name('monthly.bill.form');
        // Route::post('import-preview', [ImportController::class, 'importView'])->name('import.preview');
        Route::post('import-store', [ImportController::class, 'import'])->name('import');

        // Route::get('/user/profile', [UserProfileController::class, 'index'])->name('user.profile');
        // Route::post('/user/profile/upload', [UserProfileController::class, 'uploadFile'])->name('user.profile.upload');
        // Route::get('/user/profile/update', [UserProfileController::class, 'update'])->name('user.profile.update');
        // Route::get('/user/password/update', [UserProfileController::class, 'updatePassword'])->name('user.password.update');

        Route::get('/submit-comment', CommentSubmit::class)->name('submit.comment');

        // Admin Reseller Management
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('resellers', ResellerController::class)->except(['show']);
            Route::post('resellers/{reseller}/adjust-balance', [ResellerController::class, 'adjustBalance'])->name('resellers.adjust-balance');
            Route::get('resellers/{reseller}/transactions', [ResellerController::class, 'getTransactionsJson'])->name('resellers.transactions');

            // Expense tracking
            Route::get('expenses', ExpenseManager::class)->name('expenses');

            // Profit & Loss summary
            Route::get('profit-summary', [ProfitSummaryController::class, 'index'])->name('profit-summary');

            // Activity Log Viewer
            Route::get('activity-logs', ActivityLogViewer::class)->name('activity-logs');

            // System Logs Viewer
            Route::get('system-logs', SystemLogViewer::class)->name('system-logs');

            // Login Logs Viewer
            Route::get('login-logs', LoginLogViewer::class)->name('login-logs');

            // Customer Reviews Management
            Route::get('reviews', ManageReviews::class)->name('reviews');

            // Reseller Vouchers Audit
            Route::get('vouchers', AdminVoucherList::class)->name('vouchers');

            // Package purchase requests
            Route::get('purchase-requests', ManagePurchaseRequests::class)->name('purchase-requests');
            Route::get('saas-operators', ManageSaasOperators::class)->name('saas-operators');
            Route::get('staff-cash', StaffCashDesk::class)->name('staff-cash');
        });

        // Reseller Portal Routes
        Route::prefix('reseller')->middleware(['reseller'])->name('reseller.')->group(function () {
            Route::get('/dashboard', [ResellerDashboardController::class, 'index'])->name('dashboard');
            Route::get('customers/data', [ResellerCustomerList::class, 'getData'])->name('customers.data');
            Route::get('customers', ResellerCustomerList::class)->name('customers.index');
            Route::get('customers/create', NewCustomer::class)->name('customers.create');
            Route::get('customers/{customerId}/edit', EditCustomer::class)->name('customers.edit');

            Route::get('packages', ResellerPackageManagement::class)->name('packages.index');

            // Vouchers & Wallet — always accessible to all resellers
            Route::get('vouchers', ResellerVoucherManagement::class)->name('vouchers.index');
            Route::get('wallet', ResellerWalletManagement::class)->name('wallet.index');
        });
});

// Legacy billing subdomain → main domain
Route::domain('billing.'.$baseDomain)->group(function () use ($baseDomain) {
    Route::any('{any?}', function () use ($baseDomain) {
        $path = request()->path();
        $target = 'https://'.$baseDomain.($path && $path !== '/' ? '/'.$path : '/dashboard');

        return redirect()->away($target);
    })->where('any', '.*');
});

// portal domain routes
Route::domain('portal.'.$baseDomain)->group(function () use ($baseDomain) {
    Route::get('/', function () use ($baseDomain) {
        return redirect()->away('https://'.$baseDomain.'/portal/login');
    });

    // Authenticated portal payment initiation routes
    Route::middleware(['auth:ppp'])->group(function () {
        Route::get('/payment/bkash/initiate', [BkashPaymentController::class, 'initiate'])->name('payment.bkash.initiate');
        Route::get('/payment/nagad/initiate', [NagadPaymentController::class, 'initiate'])->name('payment.nagad.initiate');
        Route::get('/payment/sslcommerz/initiate', [SslCommerzPaymentController::class, 'initiate'])->name('payment.sslcommerz.initiate');
    });

    // Public payment callback routes (Gateways redirect here, CSRF is disabled for POSTs)
    Route::any('/payment/bkash/callback', [BkashPaymentController::class, 'callback'])->name('payment.bkash.callback');
    Route::any('/payment/nagad/callback', [NagadPaymentController::class, 'callback'])->name('payment.nagad.callback');
    Route::any('/payment/sslcommerz/callback', [SslCommerzPaymentController::class, 'callback'])->name('payment.sslcommerz.callback');
    Route::post('/payment/mock/submit', [BkashPaymentController::class, 'mockSubmit'])->name('payment.mock.submit');

    // Public voucher redemption route
    Route::get('/recharge/voucher', [PortalVoucherController::class, 'showRechargeForm'])->name('portal.voucher.recharge');
    Route::post('/recharge/voucher', [PortalVoucherController::class, 'redeem'])->name('portal.voucher.redeem');
});
