<?php

namespace App\Support;

use App\Models\CallCenterSetting;
use App\Models\User;

final class WebSipFeature
{
    public static function isGloballyEnabled(): bool
    {
        return (bool) config('call_center.websip_enabled', false);
    }

    public static function isTenantEnabled(int $tenantId): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('call_center_settings')) {
            return false;
        }

        $settings = CallCenterSetting::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->first();

        return (bool) ($settings?->websip_enabled ?? false);
    }

    public static function tenantIdFor(?User $user): int
    {
        if ($user?->tenant_id !== null) {
            return (int) $user->tenant_id;
        }

        return \App\Support\TenantResolver::requiredTenantId();
    }

    /**
     * Live-call UI (bottom FAB, topbar, list icons) + WebSIP for the current tenant.
     * Controlled by Call center → SIP settings → «লাইভ কল চালু» (websip_enabled).
     */
    public static function isEnabledForUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! (bool) config('call_center.enabled', true)) {
            return false;
        }

        return self::isTenantEnabled(self::tenantIdFor($user));
    }

    public static function showsLiveCallUi(?User $user): bool
    {
        return self::isEnabledForUser($user);
    }

    public static function sanitizeSipHost(?string $value): string
    {
        $host = trim((string) $value);

        return str_replace(['\\', '/'], '', $host);
    }
}
