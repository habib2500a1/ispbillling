<?php

namespace App\Services\Mikrotik;

use App\Models\Customer;
use App\Models\MikrotikSessionAlert;
use App\Support\CustomerPppLoginResolver;
use App\Support\CustomerStatus;
final class MikrotikSessionIntegrityService
{
    public function __construct(
        private readonly MikrotikFleetCoordinator $fleet,
        private readonly \App\Services\Network\NetworkAccessCoordinator $networkAccess,
    ) {}

    /**
     * @return array{alerts_created: int, alerts_resolved: int, sessions: int}
     */
    public function scanTenant(int $tenantId): array
    {
        if (! config('network.session_integrity_enabled', true)) {
            return ['alerts_created' => 0, 'alerts_resolved' => 0, 'sessions' => 0];
        }

        CustomerPppLoginResolver::clearIndexCache();

        $payload = $this->fleet->collectActiveSessionsForTenant($tenantId);
        $sessions = $payload['sessions'] ?? [];

        /** @var array<string, list<array<string, mixed>>> $byLogin */
        $byLogin = [];
        foreach ($sessions as $session) {
            $login = CustomerPppLoginResolver::normalize((string) ($session['username'] ?? ''));
            if ($login === '') {
                continue;
            }
            $byLogin[$login][] = $session;
        }

        $activeFingerprints = [];
        $created = 0;

        foreach ($byLogin as $login => $rows) {
            $serverIds = array_values(array_unique(array_map(
                fn (array $r): int => (int) ($r['mikrotik_server_id'] ?? 0),
                $rows,
            )));

            $customer = CustomerPppLoginResolver::resolve(
                $tenantId,
                (string) ($rows[0]['username'] ?? $login),
                count($serverIds) === 1 ? $serverIds[0] : null,
            );

            if (count($serverIds) > 1) {
                $fp = $this->fingerprint(MikrotikSessionAlert::TYPE_MULTI_ROUTER, $login, null);
                $activeFingerprints[] = $fp;
                $msg = "PPP login {$login} is online on multiple routers simultaneously.";
                if ($this->upsertAlert($tenantId, $customer?->id, MikrotikSessionAlert::TYPE_MULTI_ROUTER, 'critical', $login, $msg,
                    ['server_ids' => $serverIds, 'session_count' => count($rows)])) {
                    $created++;
                    $this->onNewAlert($tenantId, MikrotikSessionAlert::TYPE_MULTI_ROUTER, $login, $msg, $customer);
                }
            }

            if ($customer !== null && count($serverIds) === 1) {
                $homeServer = (int) ($customer->mikrotik_server_id ?? 0);
                $activeServer = $serverIds[0];
                if ($homeServer > 0 && $homeServer !== $activeServer) {
                    $fp = $this->fingerprint(MikrotikSessionAlert::TYPE_WRONG_ROUTER, $login, $customer->id);
                    $activeFingerprints[] = $fp;
                    $msg = "Subscriber online on router #{$activeServer} but assigned to #{$homeServer}.";
                    if ($this->upsertAlert($tenantId, $customer->id, MikrotikSessionAlert::TYPE_WRONG_ROUTER, 'warning', $login, $msg,
                        ['expected_server_id' => $homeServer, 'actual_server_id' => $activeServer])) {
                        $created++;
                        $this->onNewAlert($tenantId, MikrotikSessionAlert::TYPE_WRONG_ROUTER, $login, $msg, $customer);
                    }
                }

                if ($this->networkAccess->hasOverdueOpenBalance($customer)
                    && CustomerStatus::normalize((string) $customer->status) === CustomerStatus::ACTIVE
                    && ($customer->network_access_state ?? 'active') === 'active') {
                    $fp = $this->fingerprint(MikrotikSessionAlert::TYPE_OVERDUE_ONLINE, $login, $customer->id);
                    $activeFingerprints[] = $fp;
                    $msg = 'Overdue subscriber still has an active PPP session.';
                    if ($this->upsertAlert($tenantId, $customer->id, MikrotikSessionAlert::TYPE_OVERDUE_ONLINE, 'warning', $login, $msg, [])) {
                        $created++;
                        $this->onNewAlert($tenantId, MikrotikSessionAlert::TYPE_OVERDUE_ONLINE, $login, $msg, $customer);
                    }
                }
            }
        }

        $resolved = $this->resolveStaleAlerts($tenantId, $activeFingerprints);

        return [
            'alerts_created' => $created,
            'alerts_resolved' => $resolved,
            'sessions' => count($sessions),
        ];
    }

    private function fingerprint(string $type, string $login, ?int $customerId): string
    {
        return sha1($type.'|'.$login.'|'.($customerId ?? 0));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function upsertAlert(
        int $tenantId,
        ?int $customerId,
        string $type,
        string $severity,
        string $login,
        string $message,
        array $meta,
    ): bool {
        $existing = MikrotikSessionAlert::query()
            ->where('tenant_id', $tenantId)
            ->where('alert_type', $type)
            ->where('login', $login)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->whereNull('resolved_at')
            ->first();

        if ($existing !== null) {
            $existing->forceFill([
                'message' => $message,
                'severity' => $severity,
                'meta' => $meta,
            ])->save();

            return false;
        }

        MikrotikSessionAlert::query()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'alert_type' => $type,
            'severity' => $severity,
            'login' => $login,
            'message' => $message,
            'meta' => $meta,
        ]);

        return true;
    }

    /**
     * @param  list<string>  $activeFingerprints
     */
    private function resolveStaleAlerts(int $tenantId, array $activeFingerprints): int
    {
        $open = MikrotikSessionAlert::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->get();

        $resolved = 0;
        foreach ($open as $alert) {
            $fp = $this->fingerprint(
                (string) $alert->alert_type,
                (string) ($alert->login ?? ''),
                $alert->customer_id,
            );
            if (! in_array($fp, $activeFingerprints, true)) {
                $alert->forceFill(['resolved_at' => now()])->save();
                $resolved++;
            }
        }

        return $resolved;
    }

    private function onNewAlert(int $tenantId, string $type, string $login, string $message, ?Customer $customer): void
    {
        app(\App\Services\Notifications\OpsAlertNotifier::class)->sessionIntegrity($tenantId, $type, $login, $message);

        if ($type === MikrotikSessionAlert::TYPE_OVERDUE_ONLINE
            && $customer !== null
            && config('network.integrity_auto_suspend_overdue', false)) {
            app(MikrotikSessionAlertService::class)->suspendCustomer($customer);
        }
    }
}
