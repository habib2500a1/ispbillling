<?php

namespace App\Http\Middleware;

use App\Models\MainSiteData;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (class_exists(MainSiteData::class) && \Illuminate\Support\Facades\Schema::hasTable('main_site_data')) {
                $host = $request->getHost();
                $path = trim($request->path(), '/');
                $isAdminOrPortal = str_starts_with((string) $host, 'billing.')
                    || str_starts_with((string) $host, 'portal.')
                    || str_starts_with($path, 'portal')
                    || (! in_array($path, ['', 'all-packages', 'warning', 'billing'], true)
                        && ! str_starts_with($path, 'recharge/')
                        && ! str_starts_with($path, 'lang/'));

                if ($isAdminOrPortal) {
                    $locale = MainSiteData::getValue('site_locale', 'en');
                } elseif (session()->has('main_site_locale')) {
                    $locale = session()->get('main_site_locale');
                } else {
                    $locale = MainSiteData::getValue('main_site_locale', 'en');
                }
                App::setLocale($locale);
            }
        } catch (\Throwable $e) {
            // Avoid issues during early setup/migrations
        }

        return $next($request);
    }
}
