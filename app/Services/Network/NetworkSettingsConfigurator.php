<?php

namespace App\Services\Network;

use App\Models\AppSetting;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\TenantResolver;

final class NetworkSettingsConfigurator
{
    public const MODE_OFF = 'off';

    public const MODE_MIKROTIK = 'mikrotik';

    public const MODE_RADIUS = 'radius';

    public const MODE_BOTH = 'both';

    /** @return array<string, string> */
    public static function modeLabels(): array
    {
        return [
            self::MODE_OFF => 'Off — no live sync or PPP push',
            self::MODE_MIKROTIK => 'MikroTik API only (RouterOS)',
            self::MODE_RADIUS => 'RADIUS only (radacct + user DB)',
            self::MODE_BOTH => 'Both — MikroTik API + RADIUS (recommended)',
        ];
    }

    /**
     * Apply one-click presets for quick setup mode.
     */
    public function applySetupMode(string $mode): void
    {
        $presets = match ($mode) {
            self::MODE_OFF => [
                'bandwidth.collection_enabled' => '0',
                'mikrotik.poll_enabled' => '0',
                'radius.accounting_enabled' => '0',
                'radius.merge_with_api' => '0',
                'network.mikrotik_push_enabled' => '0',
                'network.radius_push_enabled' => '0',
                'network.provisioner_driver' => 'null',
            ],
            self::MODE_MIKROTIK => [
                'bandwidth.collection_enabled' => '1',
                'mikrotik.poll_enabled' => '1',
                'radius.accounting_enabled' => '0',
                'radius.merge_with_api' => '0',
                'network.mikrotik_push_enabled' => '1',
                'network.radius_push_enabled' => '0',
                'network.provisioner_driver' => 'mikrotik',
            ],
            self::MODE_RADIUS => [
                'bandwidth.collection_enabled' => '1',
                'mikrotik.poll_enabled' => '0',
                'radius.accounting_enabled' => '1',
                'radius.merge_with_api' => '0',
                'network.mikrotik_push_enabled' => '0',
                'network.radius_push_enabled' => '1',
                'network.provisioner_driver' => 'radius',
            ],
            self::MODE_BOTH => [
                'bandwidth.collection_enabled' => '1',
                'mikrotik.poll_enabled' => '1',
                'radius.accounting_enabled' => '1',
                'radius.merge_with_api' => '1',
                'network.mikrotik_push_enabled' => '1',
                'network.radius_push_enabled' => '1',
                'network.provisioner_driver' => 'both',
            ],
            default => [],
        };

        foreach ($presets as $key => $value) {
            AppSetting::putValue($key, $value);
        }

        AppSetting::putValue('network.setup_mode', $mode);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function persistFromForm(array $state): void
    {
        $mode = (string) ($state['network_setup_mode'] ?? self::MODE_BOTH);
        if (! array_key_exists($mode, self::modeLabels())) {
            $mode = self::MODE_BOTH;
        }

        $previousMode = AppSetting::getStoredValue('network.setup_mode');
        if ($previousMode !== $mode) {
            $this->applySetupMode($mode);
        }
        AppSetting::putValue('network.setup_mode', $mode);

        AppSetting::putValue('bandwidth.collection_enabled', $this->bool($state['bandwidth_collection_enabled'] ?? true));
        AppSetting::putValue('mikrotik.poll_enabled', $this->bool($state['mikrotik_poll_enabled'] ?? true));
        AppSetting::putValue('radius.accounting_enabled', $this->bool($state['radius_accounting_enabled'] ?? false));
        AppSetting::putValue('radius.merge_with_api', $this->bool($state['radius_merge_with_api'] ?? true));
        AppSetting::putValue('network.mikrotik_push_enabled', $this->bool($state['network_mikrotik_push_enabled'] ?? true));
        AppSetting::putValue('network.radius_push_enabled', $this->bool($state['network_radius_push_enabled'] ?? true));
        AppSetting::putValue(
            'network.mikrotik_always_push_ppp_on_customer_save',
            $this->bool($state['network_mikrotik_always_push_ppp_on_customer_save'] ?? true),
        );
        AppSetting::putValue('network.auto_suspend_enabled', $this->bool($state['network_auto_suspend_enabled'] ?? false));
        AppSetting::putValue('network.service_expiry_enforced', $this->bool($state['network_service_expiry_enforced'] ?? true));
        AppSetting::putValue('radius_admin.enabled', $this->bool($state['radius_admin_enabled'] ?? false));

        AppSetting::putValue('radius.db.host', trim((string) ($state['radius_db_host'] ?? '')));
        AppSetting::putValue('radius.db.port', (string) max(1, min(65535, (int) ($state['radius_db_port'] ?? 3306))));
        AppSetting::putValue('radius.db.database', trim((string) ($state['radius_db_database'] ?? '')));
        AppSetting::putValue('radius.db.username', trim((string) ($state['radius_db_username'] ?? '')));

        $dbPass = trim((string) ($state['radius_db_password'] ?? ''));
        if ($dbPass !== '') {
            AppSetting::putValue('radius.db.password', $dbPass);
        }

        AppSetting::syncToRuntimeConfig();

        if (! (bool) config('mikrotik.poll_enabled', true) || ! (bool) config('bandwidth.collection_enabled', true)) {
            $tenantId = TenantResolver::currentTenantId() ?? 1;
            app(BandwidthCollectionService::class)->refreshOnlineFlagsForTenant((int) $tenantId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function formDefaults(): array
    {
        return [
            'network_setup_mode' => $this->detectCurrentMode(),
            'bandwidth_collection_enabled' => (bool) config('bandwidth.collection_enabled', true),
            'mikrotik_poll_enabled' => (bool) config('mikrotik.poll_enabled', true),
            'radius_accounting_enabled' => (bool) config('radius.accounting_enabled', false),
            'radius_merge_with_api' => (bool) config('radius.merge_with_api', true),
            'network_mikrotik_push_enabled' => (bool) config('network.mikrotik_push_enabled', true),
            'network_radius_push_enabled' => (bool) config('network.radius_push_enabled', true),
            'network_mikrotik_always_push_ppp_on_customer_save' => (bool) config('network.mikrotik_always_push_ppp_on_customer_save', true),
            'network_auto_suspend_enabled' => (bool) config('network.auto_suspend_enabled', false),
            'network_service_expiry_enforced' => (bool) config('network.service_expiry_enforced', true),
            'radius_admin_enabled' => (bool) config('radius_admin.enabled', false),
            'radius_db_host' => (string) (AppSetting::getStoredValue('radius.db.host')
                ?? config('radius.db.host', env('RADIUS_DB_HOST', '127.0.0.1'))),
            'radius_db_port' => (int) (AppSetting::getStoredValue('radius.db.port')
                ?? config('radius.db.port', env('RADIUS_DB_PORT', 3306))),
            'radius_db_database' => (string) (AppSetting::getStoredValue('radius.db.database')
                ?? config('radius.db.database', env('RADIUS_DB_DATABASE', 'radius'))),
            'radius_db_username' => (string) (AppSetting::getStoredValue('radius.db.username')
                ?? config('radius.db.username', env('RADIUS_DB_USERNAME', 'radius'))),
            'radius_db_password' => '',
        ];
    }

    public function detectCurrentMode(): string
    {
        $stored = AppSetting::getStoredValue('network.setup_mode');
        if (is_string($stored) && array_key_exists($stored, self::modeLabels())) {
            return $stored;
        }

        $api = (bool) config('network.mikrotik_push_enabled', true)
            || (bool) config('bandwidth.collection_enabled', true);
        $radius = (bool) config('radius.accounting_enabled', false)
            || (bool) config('network.radius_push_enabled', true);

        if ($api && $radius) {
            return self::MODE_BOTH;
        }
        if ($radius) {
            return self::MODE_RADIUS;
        }
        if ($api) {
            return self::MODE_MIKROTIK;
        }

        return self::MODE_OFF;
    }

    private function bool(mixed $value): string
    {
        if ($value === true || $value === 1 || $value === '1') {
            return '1';
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        return '0';
    }
}
