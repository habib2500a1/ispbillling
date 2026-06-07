<?php

use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\DisconnectIdleDatabase;
use App\Http\Middleware\EnsureAppIsInstalled;
use App\Http\Middleware\EnsureCustomerPortalEnabled;
use App\Http\Middleware\EnsureDeployReady;
use App\Http\Middleware\ExpireLegacySessionCookie;
use App\Http\Middleware\GuardLivewireUpdateRequests;
use App\Http\Middleware\IdentifyTenantFromSubdomain;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAppLocale;
use App\Support\ResilientHttpErrors;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Mechanisms\ComponentRegistry;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

$basePath = dirname(__DIR__);
$publicPath = $basePath.'/public';
$siblingPublicHtml = dirname($basePath).'/public_html';
$siblingApp = dirname($basePath).'/isp-app';

if (is_dir($siblingPublicHtml) && is_file($siblingApp.'/artisan') && realpath($basePath) === realpath($siblingApp)) {
    $publicPath = $siblingPublicHtml;
}

$app = Application::configure(basePath: $basePath)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/install.php'));
        },
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->useCache('file');

        // Single entry point — schedules are defined in admin → Automatic process (DB).
        // Do not use runInBackground() here — mutex releases when the parent exits, so
        // overlapping isp:run-automatic-processes workers pile up and exhaust PHP-FPM (502).
        $schedule->command('isp:run-automatic-processes')
            ->everyMinute()
            ->withoutOverlapping(3600)
            ->onOneServer();

        $schedule->command('isp:scheduler-guard')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->onOneServer();

        $schedule->command('isp:prune-logs')
            ->daily()
            ->withoutOverlapping(30)
            ->onOneServer();

        $schedule->command('mfs:match-pending-payments')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->onOneServer()
            ->when(fn (): bool => (bool) config('mfs_personal.sms_ingest.enabled', false));

        $schedule->command('isp:sync-legacy-portal-daily')
            ->dailyAt((string) config('legacy_portal.daily_sync_at', '02:30'))
            ->withoutOverlapping(180)
            ->onOneServer()
            ->when(fn (): bool => (bool) config('legacy_portal.daily_sync_enabled', true));

        foreach ($schedule->events() as $event) {
            $event->appendOutputTo(storage_path('logs/scheduler.log'));
        }
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'webhooks/sms/khudebarta/dlr',
            'piprapay/webhook',
            'api/webhooks/*',
            'livewire/update',
            'livewire/upload-file',
        ]);

        $trusted = env('TRUSTED_PROXIES');
        $middleware->trustProxies(at: filled($trusted)
            ? array_values(array_filter(array_map(trim(...), explode(',', (string) $trusted))))
            : '*');

        $middleware->alias([
            'portal.enabled' => EnsureCustomerPortalEnabled::class,
            'reseller.permission' => \App\Http\Middleware\EnsureResellerPortalPermission::class,
            'reseller.owner' => \App\Http\Middleware\EnsureResellerOwner::class,
            'reseller.2fa' => \App\Http\Middleware\EnsureResellerTwoFactorVerified::class,
            'reseller.api' => \App\Http\Middleware\EnsureSanctumReseller::class,
            'reseller.api.auth' => \App\Http\Middleware\AuthenticateResellerApi::class,
            'reseller.api.readonly' => \App\Http\Middleware\EnsureResellerApiKeyReadOnly::class,
            'reseller.api.permission' => \App\Http\Middleware\EnsureResellerApiPermission::class,
            'reseller.ip' => \App\Http\Middleware\EnsureResellerIpAllowed::class,
            'reseller.api_key' => \App\Http\Middleware\AuthenticateResellerApiKey::class,
        ]);

        $middleware->appendToGroup('web', \App\Http\Middleware\ResolveResellerWhiteLabel::class);
        $middleware->appendToGroup('web', ExpireLegacySessionCookie::class);
        $middleware->prependToGroup('web', EnsureAppIsInstalled::class);
        $middleware->prependToGroup('web', EnsureDeployReady::class);
        $middleware->prependToGroup('web', GuardLivewireUpdateRequests::class);

        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->appendToGroup('api', SecurityHeaders::class);

        $middleware->prependToGroup('web', IdentifyTenantFromSubdomain::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsurePlatformLicense::class);
        $middleware->appendToGroup('web', SetAppLocale::class);
        $middleware->appendToGroup('web', DisconnectIdleDatabase::class);
        $middleware->prependToGroup('api', EnsureDeployReady::class);
        $middleware->prependToGroup('api', IdentifyTenantFromSubdomain::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\EnsurePlatformLicense::class);

        RedirectIfAuthenticated::redirectUsing(function () {
            if (Auth::guard('reseller')->check()) {
                return route('reseller.dashboard');
            }

            if (Auth::guard('customer')->check() && config('portal.enabled', true)) {
                return route('portal.dashboard');
            }

            return route('filament.admin.pages.dashboard');
        });

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('reseller') || $request->is('reseller/*')) {
                return route('reseller.login');
            }

            if (config('portal.enabled', true)
                && ($request->is('portal') || $request->is('portal/*')
                    || $request->is('login/customer') || $request->is('login/customer/*'))) {
                return route('portal.login');
            }

            return route('filament.admin.auth.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! ($request->is('livewire/update') || $request->is('livewire/update/*'))) {
                return null;
            }

            if (auth()->check()) {
                return redirect()->route('filament.admin.pages.dashboard');
            }

            return redirect()->route('filament.admin.auth.login');
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            $isSessionExpired = ($e instanceof HttpException && $e->getStatusCode() === 419)
                || $e instanceof TokenMismatchException;

            if (! $isSessionExpired) {
                return null;
            }

            $isLivewireRequest = $request->is('livewire') || $request->is('livewire/*');
            $isAdminLivewire = $isLivewireRequest
                && str_contains((string) $request->headers->get('referer', ''), '/admin');

            // Livewire update endpoints must always get a JSON response.
            // Redirecting these requests causes browser GET to /livewire/update -> 405.
            if ($isLivewireRequest) {
                $components = $request->input('components', []);
                $firstComponent = is_array($components) ? ($components[0] ?? null) : null;
                $snapshot = is_array($firstComponent) ? json_decode((string) ($firstComponent['snapshot'] ?? ''), true) : null;

                $componentName = $snapshot['memo']['name'] ?? null;
                $snapshotRelease = $snapshot['memo']['release'] ?? null;
                $expectedRelease = null;

                if (is_string($componentName) && $componentName !== '') {
                    try {
                        $componentClass = app(ComponentRegistry::class)->getClass($componentName);
                        $expectedRelease = ReleaseToken::generate($componentClass);
                    } catch (Throwable) {
                        // Component may have been removed between page load and save.
                    }
                }

                Log::warning('livewire.419', [
                    'url' => $request->fullUrl(),
                    'referer' => $request->headers->get('referer'),
                    'has_x_livewire' => $request->headers->has('X-Livewire'),
                    'component_name' => $componentName,
                    'snapshot_release' => $snapshotRelease,
                    'expected_release' => $expectedRelease,
                    'release_token_mismatch' => $expectedRelease !== null
                        && $snapshotRelease !== $expectedRelease,
                    'updates_keys' => array_keys((array) ($firstComponent['updates'] ?? [])),
                    'calls_count' => count((array) ($firstComponent['calls'] ?? [])),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => __('Your session expired. The page will refresh.'),
                ], 419);
            }

            if ($request->is('reseller') || $request->is('reseller/*')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => __('Your session expired. Please refresh the page and try again.'),
                    ], 419);
                }

                return redirect()
                    ->route('reseller.login')
                    ->withInput($request->except('password', '_token'));
            }

            if ($request->is('admin') || $request->is('admin/*') || $isAdminLivewire) {
                if ($request->expectsJson()
                    || $request->header('X-Livewire')
                    || $request->header('X-Livewire-Navigate')) {
                    return response()->json([
                        'message' => __('Your session expired. The page will refresh.'),
                    ], 419);
                }

                return redirect()
                    ->route('filament.admin.auth.login')
                    ->with('session_expired', true);
            }

            if ($request->is('pay') || $request->is('pay/*')
                || $request->is('shop') || $request->is('shop/*')
                || $request->is('hotspot') || $request->is('hotspot/*')) {
                return redirect()
                    ->back()
                    ->withInput($request->except('password', '_token', 'code'))
                    ->with('danger', __('Session expired. Please try again.'));
            }

            if (! $request->is('portal') && ! $request->is('portal/*')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => __('Your session expired. Please refresh the page and try again.'),
                    ], 419);
                }

                return redirect()
                    ->back()
                    ->withInput($request->except('password', '_token', 'code'))
                    ->with('danger', __('Session expired. Please try again.'));
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Your session expired. Please refresh the page and try again.'),
                ], 419);
            }

            return redirect()
                ->route('portal.login')
                ->with('portal_session_expired', true)
                ->withInput($request->except('password', '_token', 'code'));
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ResilientHttpErrors::shouldRenderFriendlyPage($e, $request)) {
                return null;
            }

            return ResilientHttpErrors::render($e, $request);
        });
    })->create();

$app->usePublicPath($publicPath);

return $app;
