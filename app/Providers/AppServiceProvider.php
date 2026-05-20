<?php

namespace App\Providers;

use App\Auth\CustomerUserProvider;
use App\Filament\Billing\BillingSidebarNavigation;
use App\Filament\Bw\BwSidebarNavigation;
use App\Filament\Hrm\HrmSidebarNavigation;
use App\Filament\Olt\OltSidebarNavigation;
use App\Filament\Settings\SettingsSidebarNavigation;
use App\Filament\Sms\SmsSidebarNavigation;
use App\Services\Sms\SmsTemplateService;
use App\Contracts\NetworkAccessProvisioner;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\SmsTemplate;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Observers\CustomerObserver;
use App\Observers\InvoiceItemObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use App\Observers\SupportTicketMessageObserver;
use App\Observers\SupportTicketObserver;
use App\Observers\UserObserver;
use App\Services\Network\CompositeNetworkProvisioner;
use App\Services\Network\LogNetworkProvisioner;
use App\Services\Network\MikrotikNetworkProvisioner;
use App\Services\Network\NetworkAccessCoordinator;
use App\Services\Network\NullNetworkProvisioner;
use App\Services\Network\RadiusNetworkProvisioner;
use App\Support\EnsureStorageWritable;
use App\Support\MobileAppLinks;
use App\Listeners\RecordStaffLogout;
use App\Models\User;
use App\View\Composers\BillPaymentViewComposer;
use App\View\Composers\PortalViewComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Laravel picks resources/lang when that folder exists; app strings live in /lang.
        if (is_dir($lang = base_path('lang'))) {
            $this->app->useLangPath($lang);
        }

        $this->app->singleton(NetworkAccessProvisioner::class, function ($app): NetworkAccessProvisioner {
            return match (config('network.provisioner_driver', 'null')) {
                'log' => new LogNetworkProvisioner,
                'mikrotik', 'radius', 'both' => new CompositeNetworkProvisioner(
                    $app->make(MikrotikNetworkProvisioner::class),
                    $app->make(RadiusNetworkProvisioner::class),
                ),
                default => new NullNetworkProvisioner,
            };
        });

        $this->app->singleton(NetworkAccessCoordinator::class, function ($app): NetworkAccessCoordinator {
            return new NetworkAccessCoordinator(
                $app->make(NetworkAccessProvisioner::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($storageIssues = EnsureStorageWritable::findIssues()) {
            Log::channel('single')->critical('storage_not_writable', [
                'issues' => $storageIssues,
                'hint' => 'Run: sudo scripts/fix-storage-permissions.sh',
            ]);
        }

        Auth::provider('customer', function ($app, array $config): CustomerUserProvider {
            return new CustomerUserProvider($app['hash'], $config['model']);
        });

        InvoiceItem::observe(InvoiceItemObserver::class);
        Payment::observe(PaymentObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Customer::observe(CustomerObserver::class);
        SupportTicket::observe(SupportTicketObserver::class);
        SupportTicketMessage::observe(SupportTicketMessageObserver::class);
        User::observe(UserObserver::class);

        Gate::before(function (?User $user, string $ability): ?bool {
            if ($user?->hasRole('super-admin')) {
                return true;
            }

            return null;
        });

        View::composer('bill-payment.*', BillPaymentViewComposer::class);
        View::composer('portal.*', PortalViewComposer::class);

        View::share('mobileAppDownloadUrl', MobileAppLinks::downloadUrl());

        try {
            if (Cache::remember('bootstrap.app_settings_table', 300, fn (): bool => Schema::hasTable('app_settings'))) {
                // Must run every request: caching sync caused OTP/toggles to revert to config defaults.
                AppSetting::syncToRuntimeConfig();
            }

            if (Schema::hasTable('sms_templates') && SmsTemplate::query()->count() === 0) {
                app(SmsTemplateService::class)->seedDefaults();
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning('bootstrap.app_settings_skipped', [
                'message' => $e->getMessage(),
            ]);
        }

        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(120)->by($key !== null ? 'user:'.$key : $request->ip());
        });

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        Event::listen(Logout::class, RecordStaffLogout::class);

        \App\Filament\Navigation\IspSidebarNavigation::register();
    }
}
