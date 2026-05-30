<?php

namespace App\Services\Mikrotik;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Support\BandwidthDirection;
use App\Support\CustomerPppLoginResolver;
use App\Support\MikrotikRateLimitParser;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Exceptions\BadCredentialsException;
use RouterOS\Exceptions\ConnectException;
use RouterOS\Query;

final class MikrotikServerService
{
    public function makeClient(MikrotikServer $server): Client
    {
        $config = new Config([
            'host' => $server->host,
            'user' => $server->api_username,
            'pass' => $server->api_password,
            'port' => (int) $server->api_port,
            'ssl' => (bool) $server->use_ssl,
            'timeout' => (int) config('mikrotik.connection_timeout', 8),
            'socket_timeout' => (int) config('mikrotik.socket_timeout', 15),
            'legacy' => (bool) $server->legacy_login,
        ]);

        return new Client($config);
    }

    /**
     * Pull a structured snapshot from RouterOS API into `mikrotik_servers.meta` (merged under `api_detail`).
     *
     * @return array<string, mixed>
     */
    public function fetchRouterDetails(MikrotikServer $server): array
    {
        if (! $server->is_enabled) {
            $detail = [
                'fetched_at' => now()->toIso8601String(),
                'error' => 'Server disabled in panel.',
            ];
            $meta = array_merge($server->meta ?? [], ['api_detail' => $detail]);
            $server->forceFill(['meta' => $meta])->saveQuietly();

            return $detail;
        }

        $detail = ['fetched_at' => now()->toIso8601String()];

        try {
            $client = $this->makeClient($server);
            $detail['identity'] = $this->firstPrintRow($client, '/system/identity/print');
            $detail['resource'] = $this->firstPrintRow($client, '/system/resource/print');
            $detail['routerboard'] = $this->firstPrintRow($client, '/system/routerboard/print');
            $detail['clock'] = $this->firstPrintRow($client, '/system/clock/print');
            $packages = $client->query('/system/package/print')->read();
            $detail['packages'] = is_array($packages) ? array_slice($packages, 0, 40) : [];
        } catch (\Throwable $e) {
            $detail['error'] = $e->getMessage();
        }

        $meta = array_merge($server->meta ?? [], ['api_detail' => $detail]);
        $server->forceFill(['meta' => $meta])->saveQuietly();

        return $detail;
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    private function firstPrintRow(Client $client, string $path): mixed
    {
        $rows = $client->query($path)->read();

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $first = $rows[0];

        return is_array($first) ? $first : $rows;
    }

    public function probeAndPersist(MikrotikServer $server): void
    {
        if (! $server->is_enabled) {
            $server->forceFill([
                'last_api_status' => 'unknown',
                'last_error' => 'Disabled in panel.',
                'last_checked_at' => now(),
            ])->saveQuietly();

            return;
        }

        try {
            $client = $this->makeClient($server);
            $client->query('/system/identity/print')->read();

            $server->forceFill([
                'last_api_status' => 'online',
                'last_error' => null,
                'last_checked_at' => now(),
            ])->saveQuietly();
        } catch (ConnectException|BadCredentialsException $e) {
            $server->forceFill([
                'last_api_status' => 'offline',
                'last_error' => $e->getMessage(),
                'last_checked_at' => now(),
            ])->saveQuietly();
        } catch (\Throwable $e) {
            $server->forceFill([
                'last_api_status' => 'offline',
                'last_error' => $e->getMessage(),
                'last_checked_at' => now(),
            ])->saveQuietly();
        }
    }

    public function reboot(MikrotikServer $server): void
    {
        $client = $this->makeClient($server);
        $client->query('/system/reboot');
        try {
            $client->read(false, ['socket_timeout' => 3]);
        } catch (\Throwable) {
            // Connection often drops after reboot.
        }
    }

    /**
     * Create or update one PPPoE secret on a router for this customer (same tenant as server).
     * Secret name = radius_username if set, otherwise customer_code.
     *
     * @return bool false if skipped (no password, wrong tenant, server disabled)
     */
    public function upsertPppSecretForCustomer(MikrotikServer $server, Customer $customer): bool
    {
        if (! $server->is_enabled) {
            return false;
        }

        if ((int) $customer->tenant_id !== (int) $server->tenant_id) {
            return false;
        }

        $secretName = $customer->pppLoginName();
        $password = $this->resolvePppSecretPassword($customer, $server);
        if ($password === null) {
            return false;
        }

        $client = $this->makeClient($server);
        $existingId = $this->findPppSecretDotId($client, $secretName);
        $profile = $this->resolvePppProfileNameForCustomer($server, $customer);
        $this->writePppSecretRow($client, $secretName, $password, $profile, $existingId);

        return true;
    }

    /**
     * Enable/disable PPP secret on the router (by secret name).
     */
    public function setPppSecretDisabledForCustomer(MikrotikServer $server, Customer $customer, bool $disabled): void
    {
        if (! $server->is_enabled) {
            return;
        }

        if ((int) $customer->tenant_id !== (int) $server->tenant_id) {
            return;
        }

        $secretName = $customer->pppLoginName();

        $client = $this->makeClient($server);
        $id = $this->findPppSecretDotId($client, $secretName);
        if ($id === null) {
            if ($disabled) {
                return;
            }

            // Secret missing on router: create via API, then enable.
            if (! $this->upsertPppSecretForCustomer($server, $customer)) {
                return;
            }

            $client = $this->makeClient($server);
            $id = $this->findPppSecretDotId($client, $secretName);
            if ($id === null) {
                return;
            }
        }

        $query = new Query('/ppp/secret/set');
        $query->equal('.id', $id);
        $query->equal('disabled', $disabled ? 'yes' : 'no');

        // RouterOS sometimes applies config with a small delay; verify read-back to avoid
        // "panel says ON but secret still disabled" cases.
        $attempts = 3;
        while ($attempts-- > 0) {
            $client->query($query)->read();

            try {
                $check = new Query('/ppp/secret/print');
                $check->where('.id', $id);
                $rows = $client->query($check)->read();

                $row = is_array($rows) ? ($rows[0] ?? null) : null;
                $disabledNowRaw = $row['disabled'] ?? null;
                $disabledNow = is_string($disabledNowRaw)
                    ? in_array(strtolower($disabledNowRaw), ['yes', 'true', '1'], true)
                    : (($disabledNowRaw === true) ? true : false);

                if ($disabledNow === $disabled) {
                    return;
                }
            } catch (\Throwable) {
                // If read-back fails, retry the set.
            }

            // brief wait before retry
            usleep(200_000);
        }

        Log::channel('single')->warning('network.mikrotik.ppp_secret_set_verify_failed', [
            'customer_id' => $customer->id,
            'mikrotik_server_id' => $server->id,
            'login' => $secretName,
            'disabled_requested' => $disabled,
        ]);
    }

    /**
     * Push PPPoE secrets for all customers in the server tenant. Password = per-customer
     * `mikrotik_ppp_password` or server `default_ppp_password` if set.
     *
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function syncPppSecrets(MikrotikServer $server): array
    {
        $client = $this->makeClient($server);
        $tenantId = (int) $server->tenant_id;

        $print = $client->query('/ppp/secret/print')->read();
        $byName = [];
        if (is_array($print)) {
            foreach ($print as $row) {
                if (is_array($row) && isset($row['name'], $row['.id'])) {
                    $byName[(string) $row['name']] = (string) $row['.id'];
                }
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'suspended'])
            ->cursor();

        foreach ($customers as $customer) {
            $name = $customer->pppLoginName();
            $password = $this->resolvePppSecretPassword($customer, $server);
            if ($password === null) {
                $skipped++;

                continue;
            }

            try {
                $id = $byName[$name] ?? null;
                $profile = $this->resolvePppProfileNameForCustomer($server, $customer);
                $this->writePppSecretRow($client, $name, $password, $profile, $id);
                if ($id !== null) {
                    $updated++;
                } else {
                    $created++;
                    $newId = $this->findPppSecretDotId($client, $name);
                    if ($newId !== null) {
                        $byName[$name] = $newId;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "{$name}: ".$e->getMessage();
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function findPppSecretDotId(Client $client, string $secretName): ?string
    {
        $query = new Query('/ppp/secret/print');
        $query->where('name', $secretName);
        $rows = $client->query($query)->read();
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $first = $rows[0];

        return is_array($first) && isset($first['.id']) ? (string) $first['.id'] : null;
    }

    private function resolvePppProfileNameForCustomer(MikrotikServer $server, Customer $customer): ?string
    {
        if ($customer->package_id) {
            $pkg = Package::query()->withoutGlobalScopes()->find($customer->package_id);
            if ($pkg instanceof Package) {
                if ($pkg->mikrotik_server_id !== null
                    && (int) $pkg->mikrotik_server_id === (int) $server->id
                    && is_string($pkg->mikrotik_profile_name)
                    && trim($pkg->mikrotik_profile_name) !== '') {
                    return trim($pkg->mikrotik_profile_name);
                }
                if (is_string($pkg->name) && trim($pkg->name) !== '') {
                    return trim($pkg->name);
                }
            }
        }

        if (is_string($server->ppp_profile_default) && trim($server->ppp_profile_default) !== '') {
            return trim($server->ppp_profile_default);
        }

        return null;
    }

    private function writePppSecretRow(Client $client, string $secretName, string $password, ?string $profile, ?string $existingDotId): void
    {
        $query = new Query($existingDotId !== null ? '/ppp/secret/set' : '/ppp/secret/add');
        if ($existingDotId !== null) {
            $query->equal('.id', $existingDotId);
        }
        $query->equal('name', $secretName);
        $query->equal('password', $password);
        $query->equal('service', 'pppoe');
        if ($profile !== null && $profile !== '') {
            $query->equal('profile', $profile);
        }
        $client->query($query)->read();
    }

    /**
     * Import RouterOS /ppp/profile rows as catalog {@see Package} rows (same tenant as the server).
     * De-duplicates by tenant + MikroTik server + profile name. Refreshes BTRC bandwidth line from rate-limit.
     *
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function syncPackagesFromPppProfiles(MikrotikServer $server): array
    {
        if (! $server->is_enabled) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Server disabled in panel.']];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        try {
            $client = $this->makeClient($server);
            $rows = $client->query('/ppp/profile/print')->read();
        } catch (\Throwable $e) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [$e->getMessage()]];
        }

        if (! is_array($rows)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Invalid API response.']];
        }

        $tenantId = (int) $server->tenant_id;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;

                continue;
            }

            $name = $row['name'] ?? null;
            if (! is_string($name) || trim($name) === '') {
                $skipped++;

                continue;
            }

            $name = substr(trim($name), 0, 128);
            $rateLimit = $row['rate-limit'] ?? null;
            if (! is_string($rateLimit)) {
                $rateLimit = null;
            }

            $parsed = MikrotikRateLimitParser::parse($rateLimit);

            try {
                $package = Package::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('mikrotik_server_id', $server->id)
                    ->where('mikrotik_profile_name', $name)
                    ->first();

                $meta = [
                    'profile' => $row,
                    'parsed_rate_limit' => $parsed,
                ];
                $now = now();

                if ($package === null) {
                    Package::query()->withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'mikrotik_server_id' => $server->id,
                        'mikrotik_profile_name' => $name,
                        'name' => $name,
                        'btrc_label' => $name,
                        'btrc_bandwidth' => $parsed['bandwidth_label'],
                        'btrc_notes' => filled($row['comment'] ?? null) ? (string) $row['comment'] : null,
                        'type' => 'residential',
                        'pricing_model' => 'speed',
                        'download_mbps' => $parsed['down_mbps'] ?? 0,
                        'upload_mbps' => $parsed['up_mbps'] ?? $parsed['down_mbps'],
                        'price_monthly' => 0,
                        'setup_fee' => 0,
                        'vat_percent' => 0,
                        'sd_percent' => 0,
                        'withholding_percent' => 0,
                        'billing_cycle_days' => 30,
                        'billing_cycle_type' => 'monthly',
                        'is_active' => true,
                        'mikrotik_synced_at' => $now,
                        'mikrotik_sync_meta' => $meta,
                    ]);
                    $created++;
                } else {
                    $attrs = [
                        'mikrotik_synced_at' => $now,
                        'mikrotik_sync_meta' => $meta,
                        'btrc_bandwidth' => $parsed['bandwidth_label'],
                        'download_mbps' => $parsed['down_mbps'] ?? $package->download_mbps,
                    ];
                    if ($parsed['up_mbps'] !== null) {
                        $attrs['upload_mbps'] = $parsed['up_mbps'];
                    } elseif ($parsed['down_mbps'] !== null) {
                        $attrs['upload_mbps'] = $parsed['down_mbps'];
                    }
                    if (blank($package->btrc_label)) {
                        $attrs['btrc_label'] = $name;
                    }
                    if (filled($row['comment'] ?? null) && blank($package->btrc_notes)) {
                        $attrs['btrc_notes'] = (string) $row['comment'];
                    }
                    $package->forceFill($attrs);
                    $package->saveQuietly();
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$name}: ".$e->getMessage();
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Remove live PPP sessions whose login name matches the PPP secret (post-expiry / suspend kick).
     *
     * @return int Sessions removed
     */
    public function removeActivePppoeSessionsForSecret(MikrotikServer $server, string $secretName): int
    {
        if (! $server->is_enabled) {
            return 0;
        }

        $secretName = trim($secretName);
        if ($secretName === '') {
            return 0;
        }

        try {
            $client = $this->makeClient($server);
            $query = new Query('/ppp/active/print');
            $query->where('name', $secretName);
            $rows = $client->query($query)->read();
            if (! is_array($rows)) {
                return 0;
            }

            $removed = 0;
            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row['.id'])) {
                    continue;
                }
                try {
                    $remove = new Query('/ppp/active/remove');
                    $remove->equal('.id', (string) $row['.id']);
                    $client->query($remove)->read();
                    $removed++;
                } catch (\Throwable) {
                    // Try remaining sessions.
                }
            }

            return $removed;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function kickPppoeActiveSessionsForCustomer(Customer $customer): int
    {
        $secretName = $customer->pppLoginName();
        $total = 0;
        foreach (MikrotikServer::query()->withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('is_enabled', true)
            ->cursor() as $server) {
            $total += $this->removeActivePppoeSessionsForSecret($server, $secretName);
        }

        return $total;
    }

    /**
     * PPP secret password for RouterOS: per-customer field, else MikroTik server default.
     * Uses blank-aware fallback (PHP ?? skips empty string; that left many users with no secret on the router).
     */
    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @return list<array{name: string, password: ?string, profile: ?string, service: ?string, disabled: bool, comment: ?string, raw: array<string, mixed>}>
     */
    public function fetchPppSecrets(MikrotikServer $server): array
    {
        if (! $server->is_enabled) {
            return [];
        }

        try {
            $client = $this->makeClient($server);
            $rows = $client->query('/ppp/secret/print')->read();
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($rows)) {
            return [];
        }

        $secrets = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $disabledRaw = strtolower((string) ($row['disabled'] ?? 'no'));

            $secrets[] = [
                'name' => $name,
                'password' => isset($row['password']) ? (string) $row['password'] : null,
                'profile' => isset($row['profile']) ? (string) $row['profile'] : null,
                'service' => isset($row['service']) ? (string) $row['service'] : null,
                'disabled' => in_array($disabledRaw, ['yes', 'true', '1'], true),
                'comment' => isset($row['comment']) ? (string) $row['comment'] : null,
                'raw' => $row,
            ];
        }

        return $secrets;
    }

    /**
     * Single PPP secret by login name (for optical hint bridge).
     *
     * @return array{name: string, password: ?string, profile: ?string, service: ?string, disabled: bool, comment: ?string, raw: array<string, mixed>}|null
     */
    public function fetchPppSecretForLogin(MikrotikServer $server, string $login): ?array
    {
        $login = trim($login);
        if ($login === '' || ! $server->is_enabled) {
            return null;
        }

        try {
            $client = $this->makeClient($server);
            $query = new Query('/ppp/secret/print');
            $query->where('name', $login);
            $rows = $client->query($query)->read();
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $disabledRaw = strtolower((string) ($row['disabled'] ?? 'no'));

            return [
                'name' => $name,
                'password' => isset($row['password']) ? (string) $row['password'] : null,
                'profile' => isset($row['profile']) ? (string) $row['profile'] : null,
                'service' => isset($row['service']) ? (string) $row['service'] : null,
                'disabled' => in_array($disabledRaw, ['yes', 'true', '1'], true),
                'comment' => isset($row['comment']) ? (string) $row['comment'] : null,
                'raw' => $row,
            ];
        }

        return null;
    }

    /**
     * @return array{sessions: list<array<string, mixed>>, error: ?string}
     */
    public function fetchActivePppSessions(MikrotikServer $server): array
    {
        if (! $server->is_enabled) {
            return ['sessions' => [], 'error' => 'Server disabled in panel'];
        }

        try {
            $client = $this->makeClient($server);
            $rows = $client->query('/ppp/active/print')->read();

            if (! is_array($rows) || $rows === []) {
                $query = new Query('/ppp/active/print');
                $query->equal('stats', '');
                try {
                    $rows = $client->query($query)->read();
                } catch (\Throwable) {
                    // stats not supported on this RouterOS — plain print is enough
                }
            }

            if (! is_array($rows)) {
                return ['sessions' => [], 'error' => 'Invalid API response from router'];
            }

            $trafficByLogin = [];
            if (! config('sync.mikrotik_smart_interface_walk', true)) {
                $trafficByLogin = $this->fetchPppoeInterfaceTrafficByLogin($client);
            }

            $sessions = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $name = trim((string) ($row['name'] ?? $row['user'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $routerIn = (int) ($row['bytes-in'] ?? $row['bytes_in'] ?? $row['rx-byte'] ?? 0);
                $routerOut = (int) ($row['bytes-out'] ?? $row['bytes_out'] ?? $row['tx-byte'] ?? 0);
                $rateDown = $this->parseInstantRateBps($row, [
                    'rate-down', 'rx-rate', 'rx-rate-bits-per-second',
                ]);
                $rateUp = $this->parseInstantRateBps($row, [
                    'rate-up', 'tx-rate', 'tx-rate-bits-per-second',
                ]);

                if ($routerIn === 0 && $routerOut === 0 && config('sync.mikrotik_smart_interface_walk', true)) {
                    if ($trafficByLogin === []) {
                        $trafficByLogin = $this->fetchPppoeInterfaceTrafficByLogin($client);
                    }
                    $iface = $trafficByLogin[CustomerPppLoginResolver::normalize($name)] ?? null;
                    if ($iface !== null) {
                        $routerIn = $iface['rx_byte'];
                        $routerOut = $iface['tx_byte'];
                    }
                } elseif ($trafficByLogin !== []) {
                    $iface = $trafficByLogin[CustomerPppLoginResolver::normalize($name)] ?? null;
                    if ($iface !== null) {
                        $routerIn = $iface['rx_byte'];
                        $routerOut = $iface['tx_byte'];
                    }
                }

                $sessions[] = [
                    'router_id' => (string) ($row['.id'] ?? ''),
                    'name' => $name,
                    'address' => (string) ($row['address'] ?? ''),
                    'caller_id' => (string) ($row['caller-id'] ?? $row['caller_id'] ?? ''),
                    'uptime' => (string) ($row['uptime'] ?? ''),
                    'bytes_in' => $routerIn,
                    'bytes_out' => $routerOut,
                    'rate_download_bps' => $rateDown,
                    'rate_upload_bps' => $rateUp,
                    'session_id' => (string) ($row['session-id'] ?? $row['session_id'] ?? ''),
                ];
            }

            $server->forceFill([
                'last_api_status' => 'online',
                'last_error' => null,
                'last_checked_at' => now(),
            ])->saveQuietly();

            return ['sessions' => $sessions, 'error' => null];
        } catch (\Throwable $e) {
            $server->forceFill([
                'last_api_status' => 'offline',
                'last_error' => $e->getMessage(),
                'last_checked_at' => now(),
            ])->saveQuietly();

            return ['sessions' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Lightweight fetch for one PPP login (live traffic graphs).
     *
     * @return array{
     *     found: bool,
     *     download_bytes: int,
     *     upload_bytes: int,
     *     rate_download_bps: ?int,
     *     rate_upload_bps: ?int,
     *     caller_id: ?string,
     *     error: ?string
     * }
     */
    public function fetchActivePppSessionForLogin(MikrotikServer $server, string $login): array
    {
        $empty = [
            'found' => false,
            'download_bytes' => 0,
            'upload_bytes' => 0,
            'rate_download_bps' => null,
            'rate_upload_bps' => null,
            'caller_id' => null,
            'error' => null,
        ];

        if (! $server->is_enabled) {
            return array_merge($empty, ['error' => 'Server disabled']);
        }

        $login = CustomerPppLoginResolver::normalize($login);
        if ($login === '') {
            return array_merge($empty, ['error' => 'Empty PPP login']);
        }

        try {
            $client = $this->makeClient($server);
            $row = $this->findPppActiveRowForLogin($client, $login);
            if ($row === null) {
                return $empty;
            }

            $routerIn = (int) ($row['bytes-in'] ?? $row['bytes_in'] ?? $row['rx-byte'] ?? 0);
            $routerOut = (int) ($row['bytes-out'] ?? $row['bytes_out'] ?? $row['tx-byte'] ?? 0);
            $rateDown = $this->parseInstantRateBps($row, [
                'rate-down', 'rx-rate', 'rx-rate-bits-per-second',
            ]);
            $rateUp = $this->parseInstantRateBps($row, [
                'rate-up', 'tx-rate', 'tx-rate-bits-per-second',
            ]);

            $iface = $this->fetchPppoeInterfaceTrafficForLogin($client, $login);
            if ($iface !== null) {
                $routerIn = $iface['rx_byte'];
                $routerOut = $iface['tx_byte'];
            }

            $counters = BandwidthDirection::fromMikrotikCounters($routerIn, $routerOut);

            $callerId = trim((string) ($row['caller-id'] ?? $row['caller_id'] ?? ''));

            return [
                'found' => true,
                'download_bytes' => $counters['download_bytes'],
                'upload_bytes' => $counters['upload_bytes'],
                'rate_download_bps' => $rateDown,
                'rate_upload_bps' => $rateUp,
                'caller_id' => $callerId !== '' ? $callerId : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return array_merge($empty, ['error' => $e->getMessage()]);
        }
    }

    public function sessionKey(MikrotikServer $server, string $routerSessionId): string
    {
        return 'mt'.$server->id.'-'.$routerSessionId;
    }

    /**
     * WAN / uplink ethernet counters (not PPPoE subscriber interfaces).
     *
     * @return list<array{name: string, rx_byte: int, tx_byte: int, running: bool}>
     */
    public function fetchWanInterfaceCounters(MikrotikServer $server): array
    {
        if (! $server->is_enabled) {
            return [];
        }

        try {
            $client = $this->makeClient($server);
            $rows = $client->query('/interface/print')->read();
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($rows)) {
            return [];
        }

        $patterns = $this->wanInterfacePatterns($server);
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = (string) ($row['name'] ?? '');
            if ($name === '' || str_starts_with($name, '<')) {
                continue;
            }

            if (($row['slave'] ?? 'false') === 'true' || ($row['slave'] ?? false) === true) {
                continue;
            }

            $type = strtolower((string) ($row['type'] ?? ''));
            if (in_array($type, ['bridge', 'loopback', 'ovpn-out', 'pptp-out', 'l2tp-out', 'sstp-out'], true)) {
                continue;
            }

            if (! $this->interfaceMatchesWan($name, $patterns, $type, $row)) {
                continue;
            }

            $out[] = [
                'name' => $name,
                'rx_byte' => (int) ($row['rx-byte'] ?? $row['rx_byte'] ?? 0),
                'tx_byte' => (int) ($row['tx-byte'] ?? $row['tx_byte'] ?? 0),
                'running' => ($row['running'] ?? 'false') === 'true' || ($row['running'] ?? false) === true,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function wanInterfacePatterns(MikrotikServer $server): array
    {
        $fromMeta = $server->meta['wan_interface'] ?? $server->meta['wan_interfaces'] ?? null;
        if (is_string($fromMeta) && $fromMeta !== '') {
            return [trim($fromMeta)];
        }
        if (is_array($fromMeta) && $fromMeta !== []) {
            return array_values(array_filter(array_map('strval', $fromMeta)));
        }

        return config('bandwidth.wan_interface_names', ['ether1']);
    }

    /**
     * @param  list<string>  $patterns
     * @param  array<string, mixed>  $row
     */
    private function interfaceMatchesWan(string $name, array $patterns, string $type, array $row): bool
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'wan')) {
            return true;
        }

        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }
            if ($lower === $pattern || str_starts_with($lower, $pattern)) {
                return true;
            }
        }

        if (config('bandwidth.wan_match_running_ether', false)
            && $type === 'ether'
            && (($row['running'] ?? 'false') === 'true' || ($row['running'] ?? false) === true)) {
            return true;
        }

        return false;
    }

    /**
     * RouterOS often omits byte counters on /ppp/active; they live on dynamic <pppoe-user> interfaces.
     *
     * @return array<string, array{rx_byte: int, tx_byte: int}>
     */
    private function fetchPppoeInterfaceTrafficByLogin(Client $client): array
    {
        try {
            $rows = $client->query('/interface/print')->read();
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $ifName = (string) ($row['name'] ?? '');
            if ($ifName === '' || ! str_starts_with($ifName, '<pppoe-')) {
                continue;
            }

            if (! preg_match('/^<pppoe-(.+)>$/', $ifName, $m)) {
                continue;
            }

            $login = CustomerPppLoginResolver::normalize($m[1]);
            if ($login === '') {
                continue;
            }

            $map[$login] = [
                'rx_byte' => (int) ($row['rx-byte'] ?? $row['rx_byte'] ?? 0),
                'tx_byte' => (int) ($row['tx-byte'] ?? $row['tx_byte'] ?? 0),
            ];
        }

        return $map;
    }

    /**
     * @return array{rx_byte: int, tx_byte: int}|null
     */
    private function fetchPppoeInterfaceTrafficForLogin(Client $client, string $login): ?array
    {
        $ifName = '<pppoe-'.$login.'>';

        try {
            $query = new Query('/interface/print');
            $query->where('name', $ifName);
            $rows = $client->query($query)->read();
        } catch (\Throwable) {
            try {
                $rows = $client->query('/interface/print')->read();
            } catch (\Throwable) {
                return null;
            }

            if (! is_array($rows)) {
                return null;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if ((string) ($row['name'] ?? '') !== $ifName) {
                    continue;
                }

                return [
                    'rx_byte' => (int) ($row['rx-byte'] ?? $row['rx_byte'] ?? 0),
                    'tx_byte' => (int) ($row['tx-byte'] ?? $row['tx_byte'] ?? 0),
                ];
            }

            return null;
        }

        if (! is_array($rows) || $rows === [] || ! is_array($rows[0] ?? null)) {
            return null;
        }

        $row = $rows[0];

        return [
            'rx_byte' => (int) ($row['rx-byte'] ?? $row['rx_byte'] ?? 0),
            'tx_byte' => (int) ($row['tx-byte'] ?? $row['tx_byte'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPppActiveRowForLogin(Client $client, string $login): ?array
    {
        try {
            $query = new Query('/ppp/active/print');
            $query->where('name', $login);
            $rows = $client->query($query)->read();
            if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
                return $rows[0];
            }
        } catch (\Throwable) {
            // fall through to full scan
        }

        try {
            $rows = $client->query('/ppp/active/print')->read();
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = CustomerPppLoginResolver::normalize((string) ($row['name'] ?? $row['user'] ?? ''));
            if ($name === $login) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function parseInstantRateBps(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! isset($row[$key])) {
                continue;
            }
            $raw = (string) $row[$key];
            if ($raw === '' || $raw === '0') {
                continue;
            }
            if (is_numeric($raw)) {
                $bps = (int) $raw;

                return $bps > 0 ? $bps : null;
            }
            if (preg_match('/^(\d+(?:\.\d+)?)([kKmMgG]?)(?:bps|bit)?$/i', $raw, $m)) {
                $n = (float) $m[1];
                $mult = match (strtolower($m[2] ?? '')) {
                    'k' => 1000,
                    'm' => 1_000_000,
                    'g' => 1_000_000_000,
                    default => 1,
                };

                return (int) round($n * $mult);
            }
        }

        return null;
    }

    private function resolvePppSecretPassword(Customer $customer, MikrotikServer $server): ?string
    {
        $fromCustomer = $customer->mikrotik_ppp_password;
        if (is_string($fromCustomer) && trim($fromCustomer) !== '') {
            return $fromCustomer;
        }

        $fromServer = $server->default_ppp_password;
        if (is_string($fromServer) && trim($fromServer) !== '') {
            return $fromServer;
        }

        return null;
    }
}
