<?php

namespace App\Http\Middleware;

use App\Filament\Pages\OnlineClientsMonitoring;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Full-page redirect so /admin/subscribers?preset=online never renders the clients directory.
 */
final class RedirectSubscribersOnlinePreset
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->is('admin/subscribers')
            && $request->query('preset') === 'online'
        ) {
            return redirect()->to(OnlineClientsMonitoring::getUrl());
        }

        return $next($request);
    }
}
