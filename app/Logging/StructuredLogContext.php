<?php

namespace App\Logging;

use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class StructuredLogContext
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $context = [
            'app' => config('app.name'),
            'env' => config('app.env'),
        ];

        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            $context['staff_user_id'] = $user?->id;
            $context['tenant_id'] = $user?->tenant_id;
        } elseif (Auth::guard('customer')->check()) {
            $context['customer_id'] = Auth::guard('customer')->id();
            $context['tenant_id'] = Auth::guard('customer')->user()?->tenant_id;
        } else {
            $tid = TenantResolver::currentTenantId();
            if ($tid !== null) {
                $context['tenant_id'] = $tid;
            }
        }

        if (app()->runningInConsole()) {
            $context['channel'] = 'cli';
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request): array
    {
        return array_merge(self::defaults(), [
            'http_method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'request_id' => $request->headers->get('X-Request-Id'),
        ]);
    }
}
