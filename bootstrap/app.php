<?php

use Illuminate\Console\Scheduling\Schedule;
use App\Http\Middleware\EnsureCustomerPortalEnabled;
use App\Http\Middleware\IdentifyTenantFromSubdomain;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAppLocale;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->useCache('file');

        // Single entry point — schedules are defined in admin → Automatic process (DB).
        $schedule->command('isp:run-automatic-processes')->everyMinute();

        foreach ($schedule->events() as $event) {
            $event->appendOutputTo(storage_path('logs/scheduler.log'));
        }
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'webhooks/sms/khudebarta/dlr',
            'piprapay/webhook',
            'api/webhooks/*',
        ]);

        $trusted = env('TRUSTED_PROXIES');
        $middleware->trustProxies(at: filled($trusted)
            ? array_values(array_filter(array_map(trim(...), explode(',', (string) $trusted))))
            : '*');

        $middleware->alias([
            'portal.enabled' => EnsureCustomerPortalEnabled::class,
        ]);

        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->appendToGroup('api', SecurityHeaders::class);

        $middleware->prependToGroup('web', IdentifyTenantFromSubdomain::class);
        $middleware->appendToGroup('web', SetAppLocale::class);
        $middleware->prependToGroup('api', IdentifyTenantFromSubdomain::class);

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
                && ($request->is('portal') || $request->is('portal/*') || $request->is('login') || $request->is('login/*'))) {
                return route('portal.login');
            }

            return route('filament.admin.auth.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
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

            if (! $request->is('portal') && ! $request->is('portal/*')) {
                return null;
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
    })->create();
