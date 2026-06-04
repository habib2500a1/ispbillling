<?php

namespace App\Services\CallCenter;

use App\Models\CallCenterSetting;
use App\Support\BdwebsWebSipDefaults;
use App\Support\WebSipFeature;
use Illuminate\Support\Facades\Crypt;

/**
 * One SIP profile for PortSIP (UDP 5060) and browser WebSIP (WSS) — same user/pass/server.
 */
final class PortSipConnectionProfile
{
    public const DEFAULT_SIP_PORT = 5060;

    public function __construct(
        public readonly CallCenterSetting $settings,
    ) {}

    public static function forTenant(int $tenantId): ?self
    {
        $settings = CallCenterSetting::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->first();

        return $settings !== null ? new self($settings) : null;
    }

    public function sipDomain(): string
    {
        return WebSipFeature::sanitizeSipHost($this->settings->sip_domain);
    }

    public function sipServer(): string
    {
        return WebSipFeature::sanitizeSipHost($this->settings->sip_server);
    }

    public function sipPort(): int
    {
        $meta = is_array($this->settings->meta) ? $this->settings->meta : [];
        $port = (int) ($meta['sip_port'] ?? self::DEFAULT_SIP_PORT);

        return $port > 0 ? $port : self::DEFAULT_SIP_PORT;
    }

    public function username(): string
    {
        $meta = is_array($this->settings->meta) ? $this->settings->meta : [];

        return trim((string) ($meta['websip_username'] ?? $this->settings->default_extension ?? ''));
    }

    public function password(): string
    {
        $stored = is_array($this->settings->meta) ? ($this->settings->meta['websip_password'] ?? null) : null;
        if (! is_string($stored) || $stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return '';
        }
    }

    public function isConfigured(): bool
    {
        return $this->username() !== '' && $this->password() !== ''
            && ($this->sipDomain() !== '' || $this->sipServer() !== '');
    }

    /**
     * SIP URI host — PortSIP uses domain; fallback to server IP.
     */
    public function identityHost(): string
    {
        return $this->sipDomain() !== '' ? $this->sipDomain() : $this->sipServer();
    }

    /**
     * Registrar hosts to try (PortSIP: register to server IP or domain).
     *
     * @return list<string>
     */
    public function registrarHosts(): array
    {
        $hosts = [];
        if ($this->sipServer() !== '') {
            $hosts[] = $this->sipServer();
        }
        if ($this->sipDomain() !== '' && $this->sipDomain() !== $this->sipServer()) {
            $hosts[] = $this->sipDomain();
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * @return list<string>
     */
    public function registrarServers(): array
    {
        $servers = [];
        $port = $this->sipPort();

        foreach ($this->registrarHosts() as $host) {
            $servers[] = 'sip:'.$host;
            if ($port > 0) {
                $servers[] = 'sip:'.$host.':'.$port;
            }
        }

        return array_values(array_unique($servers));
    }

    /**
     * @return list<string>
     */
    public function wssUris(): array
    {
        return BdwebsWebSipDefaults::resolveWssUris(
            $this->settings->wss_uri,
            $this->sipDomain(),
            $this->sipServer(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebSipClientConfig(string $displayName): array
    {
        return [
            'enabled' => true,
            'configured' => $this->isConfigured(),
            'transport_portsip' => 'UDP '.self::DEFAULT_SIP_PORT,
            'transport_browser' => 'WebRTC WSS',
            'sip_domain' => $this->sipDomain(),
            'sip_server' => $this->sipServer() !== '' ? $this->sipServer() : null,
            'sip_port' => $this->sipPort(),
            'identity_host' => $this->identityHost(),
            'wss_uri' => $this->wssUris()[0] ?? null,
            'wss_uris' => $this->wssUris(),
            'registrar_servers' => $this->registrarServers(),
            'username' => $this->username(),
            'password' => $this->password(),
            'display_name' => $displayName,
            'default_extension' => $this->settings->default_extension,
            'outbound_caller_id' => $this->settings->outbound_caller_id ?: $this->username(),
            'country_code' => (string) config('call_center.default_country_code', '880'),
            'log_url' => url('/admin/websip/call-log'),
            'wss_connect_timeout_ms' => (int) config('call_center.websip_wss_connect_timeout_ms', 8000),
            'register_wait_ms' => (int) config('call_center.websip_register_wait_ms', 20000),
        ];
    }
}
