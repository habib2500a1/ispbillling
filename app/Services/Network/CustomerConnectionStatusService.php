<?php

namespace App\Services\Network;

use App\Models\Customer;
use App\Models\PppSessionLog;

/**
 * PPP session timing for admin, staff app, and customer portal.
 */
final class CustomerConnectionStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Customer $customer): array
    {
        $active = PppSessionLog::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first(['started_at', 'ended_at', 'framed_ip', 'status']);

        $lastEnded = PppSessionLog::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->first(['ended_at', 'started_at']);

        $online = $active !== null;
        $lastDisconnect = $online
            ? null
            : ($lastEnded?->ended_at ?? $customer->ppp_last_seen_at);

        return [
            'online' => $online,
            'status_label' => $online ? 'Online' : 'Offline',
            'framed_ip' => $active?->framed_ip,
            'session_started_at' => $active?->started_at?->toIso8601String(),
            'session_started_formatted' => $active?->started_at?->format('d M Y, h:i A'),
            'connection_duration' => $online ? $active->formattedDuration() : null,
            'connection_duration_seconds' => $online ? $active->durationSeconds() : 0,
            'last_disconnect_at' => $lastDisconnect?->toIso8601String(),
            'last_disconnect_formatted' => $lastDisconnect?->format('d M Y, h:i A') ?? '—',
            'last_disconnect_human' => $lastDisconnect?->diffForHumans() ?? '—',
            'ppp_last_seen_at' => $customer->ppp_last_seen_at?->format('d M Y, h:i A'),
            'ppp_last_seen_human' => $customer->ppp_last_seen_at?->diffForHumans(),
            'portal_last_login_at' => $customer->portal_last_login_at?->format('d M Y, h:i A'),
            'portal_last_login_human' => $customer->portal_last_login_at?->diffForHumans(),
            'portal_last_logout_at' => $customer->portal_last_logout_at?->format('d M Y, h:i A'),
            'portal_last_logout_human' => $customer->portal_last_logout_at?->diffForHumans(),
        ];
    }
}
