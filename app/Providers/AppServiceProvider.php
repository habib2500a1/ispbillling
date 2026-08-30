<?php

namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Separate portal cookie only on portal.* subdomain — NOT on /portal path (main domain).
        // Otherwise admin staff-login writes billing_session but Filament portal reads portal_session.
        if (isset($_SERVER['HTTP_HOST']) && str_starts_with($_SERVER['HTTP_HOST'], 'portal.')) {
            config(['session.cookie' => 'portal_session']);
        }

        // Dynamically register Telescope only if the package is installed
        if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class) && class_exists(\App\Providers\TelescopeServiceProvider::class)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Set dynamic application locale from database settings
        try {
            if (class_exists(\App\Models\MainSiteData::class) && \Illuminate\Support\Facades\Schema::hasTable('main_site_data')) {
                if (app()->runningInConsole()) {
                    $locale = \App\Models\MainSiteData::getValue('site_locale', 'en');
                } else {
                    $host = request()->getHost();
                    if ($host && (str_starts_with($host, 'billing.') || str_starts_with($host, 'portal.'))) {
                        $locale = \App\Models\MainSiteData::getValue('site_locale', 'en');
                    } else {
                        $locale = \App\Models\MainSiteData::getValue('main_site_locale', 'en');
                    }
                }
                \Illuminate\Support\Facades\App::setLocale($locale);
            }
        } catch (\Throwable $e) {
            // Avoid issues during setup or migrations
        }

        Gate::before(function ($user, $ability) {
            if (! method_exists($user, 'hasRole')) {
                return null;
            }

            if ($user->hasRole('Operator') && $ability === 'saas-sell') {
                return false;
            }

            return $user->hasRole('Super Admin') ? true : null;
        });

        Gate::define('viewLogViewer', function ($user = null) {
            return auth()->check() && hasAccess(['Super Admin'], ['all-customer']);
        });

        // Optimize Livewire components in the specified directory
        Blaze::optimize()->in(resource_path('views/components'));

        Paginator::useBootstrapFive();

        Auth::provider('pppoe_provider', function ($app, array $config) {
            return new class($app['hash'], $config['model']) extends EloquentUserProvider
            {
                public function validateCredentials(Authenticatable $user, array $credentials)
                {
                    $plain = $credentials['password'];
                    // getAuthPassword() calls the model's custom getter, returning decrypted password if encrypted,
                    // or the raw value if it is legacy plaintext / bcrypt hash.
                    $auth_password = $user->getAuthPassword();

                    // Check plain text (includes newly decrypted passwords and legacy plain text)
                    if ($plain === $auth_password) {
                        return true;
                    }

                    // Fallback to standard hashing only if it looks like a legacy bcrypt hash
                    if (str_starts_with($auth_password, '$2y$') || str_starts_with($auth_password, '$2a$')) {
                        return parent::validateCredentials($user, $credentials);
                    }

                    return false;
                }
            };
        });

        // Listen to Login Event
        Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $user = $event->user;
            if ($user) {
                try {
                    $ip = request()->ip();
                    $userAgent = request()->userAgent();
                    $username = $user instanceof \App\Models\PPPSecrets ? $user->username : ($user->email ?? $user->name ?? 'Unknown');

                    \App\Models\UserLoginLog::create([
                        'authenticatable_id' => $user->id,
                        'authenticatable_type' => get_class($user),
                        'username' => $username,
                        'ip_address' => $ip,
                        'user_agent' => $userAgent,
                        'action' => 'login',
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('login log failed', ['error' => $e->getMessage()]);
                }

                // Track client portal login count and session duration
                if ($user instanceof \App\Models\PPPSecrets) {
                    $user->increment('login_count');
                    session(['portal_login_time' => now()]);
                }
            }
        });

        // Listen to Logout Event
        Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            $user = $event->user;
            if ($user) {
                try {
                    $ip = request()->ip();
                    $userAgent = request()->userAgent();
                    $username = $user instanceof \App\Models\PPPSecrets ? $user->username : ($user->email ?? $user->name ?? 'Unknown');

                    \App\Models\UserLoginLog::create([
                        'authenticatable_id' => $user->id,
                        'authenticatable_type' => get_class($user),
                        'username' => $username,
                        'ip_address' => $ip,
                        'user_agent' => $userAgent,
                        'action' => 'logout',
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('logout log failed', ['error' => $e->getMessage()]);
                }
            }
        });
    }
}
