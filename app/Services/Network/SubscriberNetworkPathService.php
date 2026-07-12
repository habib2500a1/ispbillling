<?php

namespace App\Services\Network;

use App\Http\Controllers\MikrotikController;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use App\Services\Olt\IspbillingOpticalBridge;
use Throwable;

final class SubscriberNetworkPathService
{
    public function path(CustomersInfo $customer): array
    {
        $customer->loadMissing(['pppUser', 'onus']);
        $ppp = $customer->pppUser;
        $onu = $customer->primaryOnu();

        $session = $this->livePppSession($ppp);
        $callerId = $session['caller_id']
            ?? ($ppp?->caller_id ?: null)
            ?? ($onu?->mac_address ?: null);

        $rx = $onu?->rx_power_dbm;

        return [
            'mikrotik' => [
                'name' => $ppp?->router_name,
                'host' => null,
            ],
            'ppp' => [
                'login' => $ppp?->username,
                'status' => $ppp?->status ?? $customer->status,
                'framed_ip' => $session['framed_ip'] ?? ($ppp?->ppp_remote_ip ?: null),
                'caller_id' => $callerId,
                'online' => $session['online'] ?? false,
                'profile' => $ppp?->profile,
            ],
            'onu' => [
                'linked' => $onu !== null,
                'serial' => $onu?->serial_number,
                'mac' => $onu?->mac_address,
                'epon' => $onu?->pon_port,
                'rx_dbm' => $rx !== null ? (float) $rx : null,
                'tx_dbm' => $onu?->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null,
                'olt' => $onu?->olt_name,
                'oper_status' => $onu?->oper_status,
                'source' => $onu?->source,
            ],
            'customer_status' => $customer->status,
            'path_label' => $this->pathLabel(
                $ppp?->router_name,
                $session['framed_ip'] ?? $ppp?->ppp_remote_ip,
                $callerId,
                $onu?->pon_port,
                $rx
            ),
        ];
    }

    public function syncAndRefresh(CustomersInfo $customer): array
    {
        $bridge = app(IspbillingOpticalBridge::class);
        $bridge->syncForCustomer($customer);

        $customer->refresh()->load(['pppUser', 'onus']);
        $mac = $customer->pppUser?->caller_id ?: $customer->primaryOnu()?->mac_address;
        if (filled($mac) && ! $customer->primaryOnu()?->rx_power_dbm) {
            $bridge->syncByMac($customer, (string) $mac);
        }

        return $this->path($customer->fresh(['pppUser', 'onus']) ?? $customer);
    }

    /**
     * @return array{online: bool, framed_ip: ?string, caller_id: ?string}
     */
    private function livePppSession(?PPPSecrets $ppp): array
    {
        $empty = ['online' => false, 'framed_ip' => null, 'caller_id' => null];
        if (! $ppp || ! filled($ppp->router_name) || ! filled($ppp->username)) {
            return $empty;
        }

        try {
            $ctrl = app(MikrotikController::class);
            $rows = $ctrl->singleRead(
                $ppp->router_name,
                '/ppp/active/print',
                'ppp active print without-paging terse where name='.$ctrl->mtQuote($ppp->username),
                ['name' => $ppp->username],
                false,
                true
            );

            if (! is_array($rows) || $rows === [] || ! is_array($rows[0] ?? null)) {
                return $empty;
            }

            $row = $rows[0];

            return [
                'online' => true,
                'framed_ip' => $row['address'] ?? $row['caller-id'] ?? null,
                'caller_id' => $row['caller-id'] ?? $ppp->caller_id,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    private function pathLabel(?string $router, ?string $ip, ?string $mac, ?string $pon, $rx): string
    {
        $parts = array_filter([
            $router ? 'MT '.$router : null,
            $ip ? 'IP '.$ip : null,
            $mac ? 'MAC '.$mac : null,
            $pon ? 'PON '.$pon : null,
            $rx !== null && $rx !== '' ? 'RX '.number_format((float) $rx, 2).' dBm' : null,
        ]);

        return $parts !== [] ? implode(' → ', $parts) : '—';
    }
}
