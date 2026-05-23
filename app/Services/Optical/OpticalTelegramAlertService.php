<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Support\Facades\Cache;

/**
 * Telegram NOC alerts for optical + OLT health with cooldown deduplication.
 */
final class OpticalTelegramAlertService
{
    public function notifyOnuAlert(Device $onu, string $severity, string $title, string $message): void
    {
        if (! $this->enabled() || ! $this->severityAllowed($severity)) {
            return;
        }

        $key = sprintf('optical_tg:%d:onu:%d:%s', $onu->tenant_id, $onu->id, md5($title));
        if (! $this->acquireCooldown($key)) {
            return;
        }

        try {
            app(NotificationDispatcher::class)->notifyOps(
                (int) $onu->tenant_id,
                NotificationEvent::OPTICAL_ALERT,
                array_merge(OpticalOpsAlertFormatter::variablesForOnu($onu, $message), [
                    'title' => $title,
                    'severity' => strtoupper($severity),
                    'emoji' => $this->severityEmoji($severity),
                    'time' => now()->format('d-M-Y H:i:s'),
                ]),
            );
        } catch (\Throwable) {
            //
        }
    }

    public function notifyOltHealth(Device $olt, string $severity, string $message): void
    {
        if (! $this->enabled() || ! config('optical.telegram.olt_health_enabled', true)) {
            return;
        }

        if (! $this->severityAllowed($severity)) {
            return;
        }

        $key = sprintf('optical_tg:%d:olt:%d:health', $olt->tenant_id, $olt->id);
        if (! $this->acquireCooldown($key)) {
            return;
        }

        $health = is_array($olt->olt_health) ? $olt->olt_health : [];

        try {
            app(NotificationDispatcher::class)->notifyOps(
                (int) $olt->tenant_id,
                'optical_olt_health',
                [
                    'severity' => strtoupper($severity),
                    'olt_name' => $olt->adminLabel(),
                    'olt_ip' => $olt->management_ip ?? '—',
                    'cpu' => $health['cpu_percent'] ?? '—',
                    'memory' => $health['memory_percent'] ?? '—',
                    'temp' => $health['temperature_c'] ?? '—',
                    'message' => $message,
                    'time' => now()->format('d-M-Y H:i:s'),
                ],
            );
        } catch (\Throwable) {
            //
        }
    }

    private function enabled(): bool
    {
        return config('optical.notify_ops', true)
            && config('optical.telegram.enabled', true)
            && config('notifications.telegram.enabled', false);
    }

    private function severityAllowed(string $severity): bool
    {
        $allowed = config('optical.telegram.severities', ['warning', 'critical', 'emergency']);

        return in_array(strtolower($severity), $allowed, true);
    }

    private function acquireCooldown(string $key): bool
    {
        $minutes = max(1, (int) config('optical.telegram.cooldown_minutes', 15));
        $ttl = $minutes * 60;

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, 1, $ttl);

        return true;
    }

    private function severityEmoji(string $severity): string
    {
        return match (strtolower($severity)) {
            'emergency' => '🚨',
            'critical' => '🔴',
            'warning' => '⚠️',
            default => '📡',
        };
    }
}
