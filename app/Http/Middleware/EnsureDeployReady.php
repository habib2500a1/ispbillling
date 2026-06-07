<?php

namespace App\Http\Middleware;

use App\Support\DeployReady;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDeployReady
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request) || DeployReady::isReady()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('livewire/*')) {
            return response()->json([
                'message' => 'Application is starting. Please retry in a few seconds.',
            ], 503);
        }

        return response()->view('errors.maintenance', [
            'title' => 'Starting up…',
            'message' => 'The system is finishing a deploy update. This page will refresh automatically.',
            'autoRefresh' => true,
        ], 503);
    }

    private function shouldBypass(Request $request): bool
    {
        return $request->is('up', 'health', 'health/*', 'install', 'install/*');
    }
}
