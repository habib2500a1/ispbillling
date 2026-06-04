<?php

namespace App\Support;

final class DemoMode
{
    public static function enabled(): bool
    {
        return (bool) config('isp.demo.enabled', false);
    }

    public static function label(): string
    {
        return trim((string) config('isp.demo.banner_label', 'DEMO'));
    }

    public static function message(): string
    {
        return trim((string) config('isp.demo.banner_message', 'Demo mode — SMS, MikroTik push, and live payments are disabled.'));
    }

    /**
     * Override risky config when demo is on (call early in boot).
     */
    public static function applySafetyOverrides(): void
    {
        if (! self::enabled()) {
            return;
        }

        config([
            'notifications.sms.enabled' => false,
            'network.mikrotik_push_enabled' => false,
            'network.mikrotik_always_push_ppp_on_customer_save' => false,
            'network.radius_push_enabled' => false,
            'network.auto_suspend_enabled' => false,
            'call_center.websip_enabled' => false,
        ]);
    }

    public static function blocksOutboundIntegrations(): bool
    {
        return self::enabled();
    }
}
