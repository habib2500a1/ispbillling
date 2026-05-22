<?php

namespace App\Services\Hotspot;

use App\Models\HotspotVoucher;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Services\Mikrotik\MikrotikServerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RouterOS\Query;

final class MikrotikHotspotProvisioner
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
    ) {}

    /**
     * @return array{ok: bool, message: string, username?: string, password?: string, server?: string}
     */
    public function provisionForVoucher(HotspotVoucher $voucher): array
    {
        if (! config('hotspot.provision_enabled', true)) {
            return ['ok' => false, 'message' => 'Hotspot router provisioning is disabled in settings.'];
        }

        $server = $this->resolveServer($voucher);
        if ($server === null) {
            return ['ok' => false, 'message' => 'No MikroTik server configured for hotspot vouchers.'];
        }

        if (! $server->is_enabled) {
            return ['ok' => false, 'message' => "Router {$server->name} is disabled."];
        }

        $profile = $this->resolveProfile($voucher);
        $username = $this->hotspotUsername($voucher);
        $password = $this->generatePassword();

        try {
            $client = $this->mikrotik->makeClient($server);
            $this->removeHotspotUser($client, $username);

            $add = new Query('/ip/hotspot/user/add');
            $add->equal('name', $username);
            $add->equal('password', $password);
            $add->equal('profile', $profile);
            $add->equal('comment', 'ISP voucher '.$voucher->code);

            $uptime = $this->uptimeLimit($voucher);
            if ($uptime !== null) {
                $add->equal('limit-uptime', $uptime);
            }

            $bytes = $this->bytesLimit($voucher);
            if ($bytes !== null) {
                $add->equal('limit-bytes-total', $bytes);
            }

            $client->query($add)->read();

            $voucher->forceFill([
                'mikrotik_server_id' => $server->id,
                'hotspot_username' => $username,
                'hotspot_password' => $password,
                'provisioned_at' => now(),
                'provision_error' => null,
            ])->saveQuietly();

            return [
                'ok' => true,
                'message' => "Hotspot user created on {$server->name}.",
                'username' => $username,
                'password' => $password,
                'server' => $server->name,
            ];
        } catch (\Throwable $e) {
            Log::warning('hotspot.provision_failed', [
                'voucher_id' => $voucher->id,
                'server_id' => $server->id,
                'message' => $e->getMessage(),
            ]);

            $voucher->forceFill([
                'provision_error' => $e->getMessage(),
            ])->saveQuietly();

            return ['ok' => false, 'message' => 'Router error: '.$e->getMessage()];
        }
    }

    public function resolveServer(HotspotVoucher $voucher): ?MikrotikServer
    {
        $voucher->loadMissing('package');

        $packageServerId = $voucher->package?->mikrotik_server_id;
        if ($packageServerId) {
            return MikrotikServer::query()->withoutGlobalScopes()->find($packageServerId);
        }

        $defaultId = (int) config('hotspot.default_mikrotik_server_id', 0);

        return $defaultId > 0
            ? MikrotikServer::query()->withoutGlobalScopes()->find($defaultId)
            : MikrotikServer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $voucher->tenant_id)
                ->where('is_enabled', true)
                ->orderBy('id')
                ->first();
    }

    private function resolveProfile(HotspotVoucher $voucher): string
    {
        $voucher->loadMissing('package');
        $fromPackage = trim((string) ($voucher->package?->mikrotik_profile_name ?? ''));

        if ($fromPackage !== '') {
            return $fromPackage;
        }

        return (string) config('hotspot.default_profile', 'default');
    }

    private function hotspotUsername(HotspotVoucher $voucher): string
    {
        $raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $voucher->code) ?: $voucher->code);

        return Str::limit($raw, 32, '');
    }

    private function generatePassword(): string
    {
        return Str::upper(Str::random(8));
    }

    private function uptimeLimit(HotspotVoucher $voucher): ?string
    {
        $hours = (int) $voucher->duration_hours;
        if ($hours <= 0) {
            return null;
        }

        return sprintf('%d:00:00', $hours);
    }

    private function bytesLimit(HotspotVoucher $voucher): ?string
    {
        $mb = $voucher->data_limit_mb;
        if ($mb === null || $mb <= 0) {
            return null;
        }

        return (string) ((int) $mb * 1_000_000);
    }

    /**
     * @param  \RouterOS\Client  $client
     */
    private function removeHotspotUser($client, string $username): void
    {
        $print = new Query('/ip/hotspot/user/print');
        $print->where('name', $username);
        $rows = $client->query($print)->read();
        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['.id'])) {
                continue;
            }
            $remove = new Query('/ip/hotspot/user/remove');
            $remove->equal('.id', (string) $row['.id']);
            $client->query($remove)->read();
        }
    }
}
