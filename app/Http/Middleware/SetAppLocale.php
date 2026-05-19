<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('locales.supported', ['en']);

        if ($request->has('lang') && in_array($request->query('lang'), $supported, true)) {
            session(['locale' => $request->query('lang')]);
        }

        $locale = session('locale', config('app.locale', 'en'));

        if (! in_array($locale, $supported, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
