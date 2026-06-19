<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use Carbon\Carbon;

/**
 * Offline ONU handling — last seen, alarms, customer warnings. Never deletes MAC.
 */
final class OnuOfflineHandlingService
{
    private const ONLINE_STATUSES = ['online', 'active', 'up', 'working'];

    private const OFFLINE_STATUSES = ['offline', 'los', 'power_fail', 'dying_gasp', 'power_off', 'unauthorized'];

    public function recordStatus(Device $onu, string $operStatus, ?Carbon $at = null): void
    {
        if (! config('onu_management.offline_handling.save_last_seen', true)) {
            return;
        }

        $at ??= now();
        $oper = strtolower(trim($operStatus));
        $meta = is_array($onu->meta) ? $onu->meta : [];
        $previous = strtolower((string) ($meta['last_oper_status'] ?? ''));

        if (in_array($oper, self::ONLINE_STATUSES, true)) {
            $meta['onu_last_seen_at'] = $at->toIso8601String();
            if (empty($meta['onu_online_since']) || in_array($previous, self::OFFLINE_STATUSES, true)) {
                $meta['onu_online_since'] = $at->toIso8601String();
            }
            unset($meta['onu_offline_since']);
            $onu->offline_reason = null;
        } elseif (in_array($oper, self::OFFLINE_STATUSES, true)) {
            if (empty($meta['onu_offline_since'])) {
                $meta['onu_offline_since'] = $at->toIso8601String();
            }
            unset($meta['onu_online_since']);
            $onu->offline_reason = $this->offlineReasonLabel($oper);
        }

        $meta['last_oper_status'] = $oper;
        $onu->forceFill(['meta' => $meta])->saveQuietly();

        if ($onu->customer_id !== null && config('onu_management.offline_handling.customer_profile_warning', true)) {
            $this->syncCustomerOnuWarning($onu->fresh(), $oper);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function customerWarning(?Customer $customer): array
    {
        if ($customer === null) {
            return ['active' => false];
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $warning = is_array($meta['onu_warning'] ?? null) ? $meta['onu_warning'] : [];

        return [
            'active' => ! empty($warning['active']),
            'level' => $warning['level'] ?? 'info',
            'message' => $warning['message'] ?? null,
            'oper_status' => $warning['oper_status'] ?? null,
            'last_seen' => $warning['last_seen'] ?? null,
            'offline_since' => $warning['offline_since'] ?? null,
            'suggest_ticket' => (bool) ($warning['suggest_ticket'] ?? false),
        ];
    }

    private function syncCustomerOnuWarning(Device $onu, string $oper): void
    {
        $customer = $onu->customer;
        if ($customer === null) {
            return;
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];
        $customerMeta = is_array($customer->meta) ? $customer->meta : [];

        $isOffline = in_array($oper, self::OFFLINE_STATUSES, true);
        $isUnauthorized = in_array($oper, config('onu_management.unauthorized_onu.status_values', []), true);

        if (! $isOffline && ! $isUnauthorized) {
            unset($customerMeta['onu_warning']);
            $customer->forceFill(['meta' => $customerMeta])->saveQuietly();

            return;
        }

        $level = $isUnauthorized ? 'critical' : (in_array($oper, ['los', 'dying_gasp'], true) ? 'critical' : 'warning');
        $offlineSince = $meta['onu_offline_since'] ?? null;
        $hoursOffline = $offlineSince ? Carbon::parse($offlineSince)->diffInHours(now()) : 0;
        $suggestTicket = $isOffline
            && config('onu_management.offline_handling.ticket_suggest_on_offline', true)
            && $hoursOffline >= (int) config('onu_management.smart_automation.offline_ticket_hours', 24);

        $customerMeta['onu_warning'] = [
            'active' => true,
            'level' => $level,
            'oper_status' => $oper,
            'message' => $this->offlineReasonLabel($oper).' · ONU '.($onu->mac_address ?? $onu->serial_number ?? '#'.$onu->id),
            'last_seen' => $meta['onu_last_seen_at'] ?? null,
            'offline_since' => $offlineSince,
            'suggest_ticket' => $suggestTicket,
            'updated_at' => now()->toIso8601String(),
        ];

        $customer->forceFill(['meta' => $customerMeta])->saveQuietly();
    }

    private function offlineReasonLabel(string $oper): string
    {
        return match ($oper) {
            'los' => 'Loss of signal (fiber cut?)',
            'dying_gasp' => 'Dying gasp (power loss)',
            'power_fail', 'power_off' => 'Power off',
            'unauthorized', 'auth_fail', 'illegal' => 'Unauthorized ONU',
            'offline' => 'ONU offline',
            default => ucfirst(str_replace('_', ' ', $oper)),
        };
    }
}
