<?php

namespace App\Providers;

use App\Auth\CustomerUserProvider;
use App\Filament\Navigation\IspNavigationManager;
use App\Filament\Billing\BillingSidebarNavigation;
use Filament\Navigation\NavigationManager;
use App\Filament\Bw\BwSidebarNavigation;
use App\Filament\Hrm\HrmSidebarNavigation;
use App\Filament\Olt\OltSidebarNavigation;
use App\Filament\Settings\SettingsSidebarNavigation;
use App\Filament\Sms\SmsSidebarNavigation;
use App\Contracts\NetworkAccessProvisioner;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
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
use App\Support\AppInstalled;
use App\Support\DemoMode;
use App\Support\EnsureStorageWritable;
use App\Support\MobileAppLinks;
use App\Support\ResellerApiContext;
use App\Support\SafeCache;
use App\Support\TrustedAppUrl;
use App\Listeners\RecordStaffLogout;
use App\Models\User;
use App\Livewire\Filament\SafeGlobalSearch;
use App\View\Composers\BillPaymentViewComposer;
use App\View\Composers\PortalViewComposer;
use Filament\Livewire\GlobalSearch as FilamentGlobalSearch;
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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    private static bool $storageBootstrapped = false;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Laravel 11 has no cache "failover" driver — cached config with CACHE_STORE=failover bricks the site.
        if ((string) config('cache.default') === 'failover') {
            config(['cache.default' => 'redis']);
        }

        // NextDeploy panel may inject SESSION_DRIVER=file — prefer redis when available.
        $redisHost = (string) env('REDIS_HOST', '');
        if ($redisHost !== '' && $redisHost !== 'null') {
            if (in_array((string) env('SESSION_DRIVER', ''), ['', 'file'], true)) {
                config(['session.driver' => 'redis']);
            }
            if (in_array((string) env('CACHE_STORE', ''), ['', 'file'], true)) {
                config(['cache.default' => 'redis']);
            }
        }

        if (PHP_SAPI === 'cli') {
            $this->guardProductionArtisanCommands();
        }

        $this->app->bind(NavigationManager::class, IspNavigationManager::class);
        $this->app->singleton(ResellerApiContext::class);

        // Laravel picks resources/lang when that folder exists; app strings live in /lang.
        if (is_dir($lang = base_path('lang'))) {
            $this->app->useLangPath($lang);
        }

        $this->app->singleton(NetworkAccessProvisioner::class, function ($app): NetworkAccessProvisioner {
            $mikrotik = $app->make(MikrotikNetworkProvisioner::class);
            $radius = $app->make(RadiusNetworkProvisioner::class);

            return match (config('network.provisioner_driver', 'null')) {
                'log' => new LogNetworkProvisioner,
                'mikrotik', 'radius', 'both' => new CompositeNetworkProvisioner($mikrotik, $radius),
                default => static::optionalMikrotikProvisioner($mikrotik),
            };
        });

        $this->app->singleton(NetworkAccessCoordinator::class, function ($app): NetworkAccessCoordinator {
            return new NetworkAccessCoordinator(
                $app->make(NetworkAccessProvisioner::class),
            );
        });

        Auth::provider('customer', function ($app, array $config): CustomerUserProvider {
            return new CustomerUserProvider($app['hash'], $config['model']);
        });
    }

    /**
     * When provisioner_driver is null, still push PPP enable/disable if always_push is on (panel default).
     */
    private static function optionalMikrotikProvisioner(NetworkAccessProvisioner $mikrotik): NetworkAccessProvisioner
    {
        $alwaysPush = (bool) config('network.mikrotik_always_push_ppp_on_customer_save', true);
        $pushEnabled = (bool) config('network.mikrotik_push_enabled', true);

        return $alwaysPush && $pushEnabled ? $mikrotik : new NullNetworkProvisioner;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            $request = request();
            TrustedAppUrl::applyFromRequest($request);
            $appUrl = (string) config('app.url', '');

            if (
                str_starts_with($appUrl, 'https://')
                || $request->isSecure()
            ) {
                URL::forceScheme('https');
            }
        }

        if (! $this->app->runningInConsole() && AppInstalled::isInstalled() && ! is_file(AppInstalled::flagPath())) {
            try {
                AppInstalled::markInstalled();
            } catch (\Throwable $e) {
                Log::channel('single')->warning('bootstrap.app_installed_flag_skipped', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (! self::$storageBootstrapped) {
            self::$storageBootstrapped = true;

            foreach (EnsureStorageWritable::directories() as $dir) {
                if (! is_dir($dir)) {
                    \Illuminate\Support\Facades\File::ensureDirectoryExists($dir, 0775);
                }
            }

            if ($storageIssues = EnsureStorageWritable::findIssues()) {
                Log::channel('single')->critical('storage_not_writable', [
                    'issues' => $storageIssues,
                    'hint' => 'Run: sudo scripts/fix-storage-permissions.sh',
                ]);
            }
        }

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
        View::composer('reseller.*', function ($view): void {
            $view->with('portal', app(\App\Support\ResellerPortalSession::class));
        });

        View::share('mobileAppDownloadUrl', MobileAppLinks::downloadUrl());

        try {
            $isAuthRoute = ! $this->app->runningInConsole()
                && request()->routeIs(
                    'filament.admin.auth.*',
                    'admin.login.session',
                    'admin.login.complete',
                    'login.hub',
                    'login.hub.store',
                );

            if (! $isAuthRoute) {
                if (SafeCache::remember('bootstrap.app_settings_table', 300, fn (): bool => Schema::hasTable('app_settings'))) {
                    AppSetting::syncToRuntimeConfig();
                }
            }

            AppSetting::applyApplicationTimezone();

            DemoMode::applySafetyOverrides();
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

        // Cloudflare Rocket Loader rewrites script type and breaks Livewire → login POST hits /admin/login → 405.
        Livewire::useScriptTagAttributes([
            'data-cfasync' => 'false',
        ]);

        // Must run after Filament panel registers Livewire components (otherwise overwritten).
        $this->app->booted(function (): void {
            Livewire::component('filament.livewire.global-search', SafeGlobalSearch::class);
            Livewire::component(FilamentGlobalSearch::class, SafeGlobalSearch::class);
        });

        config([
            'livewire.temporary_file_upload.disk' => 'local',
            'livewire.temporary_file_upload.directory' => 'livewire-tmp',
            'livewire.temporary_file_upload.rules' => ['file', 'mimes:jpg,jpeg,png,webp,gif,pdf', 'max:10240'],
            'livewire.temporary_file_upload.max_upload_time' => 10,
        ]);

        if (! is_dir(storage_path('app/livewire-tmp'))) {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/livewire-tmp'), 0775);
        }
    }

    /**
     * Block destructive DB commands on production (migrate:fresh wiped live data once via --env=testing).
     */
    private function guardProductionArtisanCommands(): void
    {
        if (! is_file(storage_path('.production-live')) && config('app.env') !== 'production') {
            return;
        }

        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? null;

        $blocked = [
            'migrate:fresh',
            'migrate:reset',
            'db:wipe',
            'schema:drop',
        ];

        if (in_array($command, $blocked, true)) {
            fwrite(STDERR, "Blocked on production: php artisan {$command}\n");
            fwrite(STDERR, "Use migrate only. Data import: isp:import-legacy-portal-full (see docs).\n");
            exit(1);
        }

        if ($command === 'isp:demo-setup' && in_array('--fresh', $argv, true)) {
            fwrite(STDERR, "Blocked on production: isp:demo-setup --fresh (runs migrate:fresh).\n");
            exit(1);
        }

        if ($command === 'test') {
            fwrite(STDERR, "php artisan test is disabled on this production server.\n");
            fwrite(STDERR, "Remove storage/.production-live or run tests on a staging machine.\n");
            exit(1);
        }
    }
}
