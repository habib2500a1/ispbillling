<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Support\Rbac\IspModuleCatalog;
use App\Support\SafeCache;
use App\Support\TenantResolver;

final class TenantModuleSettingsService
{
    private const CACHE_PREFIX = 'tenant_modules:';

    /** @return list<string> */
    public static function configurableKeys(): array
    {
        return array_keys(IspModuleCatalog::modules());
    }

    public function isEnabled(?int $tenantId, string $moduleKey): bool
    {
        if (! in_array($moduleKey, self::configurableKeys(), true)) {
            return true;
        }

        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $modules = $this->resolvedModules($tenantId);

        return (bool) ($modules[$moduleKey] ?? true);
    }

    /**
     * @return array<string, bool>
     */
    public function allForTenant(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return $this->resolvedModules($tenantId);
    }

    /**
     * @return array<string, array{label: string, hint: string, enabled: bool}>
     */
    public function labeledForTenant(?int $tenantId = null): array
    {
        $states = $this->allForTenant($tenantId);
        $labeled = [];

        foreach (IspModuleCatalog::modules() as $key => $meta) {
            $labeled[$key] = [
                'label' => $meta['label'],
                'hint' => $meta['hint'],
                'enabled' => $states[$key] ?? true,
            ];
        }

        return $labeled;
    }

    public function setEnabled(int $tenantId, string $moduleKey, bool $enabled): void
    {
        if (! in_array($moduleKey, self::configurableKeys(), true)) {
            return;
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $modules = is_array($settings['enabled_modules'] ?? null) ? $settings['enabled_modules'] : [];

        $defaults = $this->defaultModules();
        foreach (self::configurableKeys() as $key) {
            $modules[$key] = (bool) ($modules[$key] ?? $defaults[$key]);
        }

        $modules[$moduleKey] = $enabled;
        $settings['enabled_modules'] = $modules;

        $tenant->forceFill(['settings' => $settings])->save();
        $this->forgetCache($tenantId);
    }

    public function toggle(int $tenantId, string $moduleKey): bool
    {
        $enabled = ! $this->isEnabled($tenantId, $moduleKey);
        $this->setEnabled($tenantId, $moduleKey, $enabled);

        return $enabled;
    }

    /**
     * Seed all modules ON for a new tenant.
     */
    public function seedDefaults(int $tenantId): void
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return;
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        if (isset($settings['enabled_modules']) && is_array($settings['enabled_modules'])) {
            return;
        }

        $settings['enabled_modules'] = $this->defaultModules();
        $tenant->forceFill(['settings' => $settings])->save();
        $this->forgetCache($tenantId);
    }

    public function moduleKeyForPermission(string $permission): ?string
    {
        foreach (IspModuleCatalog::modules() as $key => $meta) {
            if (in_array($permission, $meta['permissions'], true) || $permission === $meta['gate']) {
                return $key;
            }
        }

        $prefix = explode('.', $permission, 2)[0] ?? '';

        return match ($prefix) {
            'olts', 'onu', 'devices', 'ports' => 'olt',
            'invoices' => 'billing',
            'collections' => 'payments',
            'payroll' => 'accounting',
            'franchise' => 'resellers',
            'technician', 'field_visits' => 'support',
            default => null,
        };
    }

    /**
     * @return array<string, bool>
     */
    private function resolvedModules(int $tenantId): array
    {
        return SafeCache::remember(
            self::CACHE_PREFIX.$tenantId,
            now()->addMinutes(5),
            function () use ($tenantId): array {
                $tenant = Tenant::query()->find($tenantId);
                $settings = is_array($tenant?->settings) ? $tenant->settings : [];
                $stored = is_array($settings['enabled_modules'] ?? null) ? $settings['enabled_modules'] : [];
                $merged = $this->defaultModules();

                foreach ($stored as $key => $value) {
                    if (array_key_exists($key, $merged)) {
                        $merged[$key] = (bool) $value;
                    }
                }

                return $merged;
            },
        );
    }

    /**
     * @return array<string, bool>
     */
    private function defaultModules(): array
    {
        $defaults = [];
        foreach (self::configurableKeys() as $key) {
            $defaults[$key] = true;
        }

        return $defaults;
    }

    private function forgetCache(int $tenantId): void
    {
        SafeCache::forget(self::CACHE_PREFIX.$tenantId);
        SafeCache::forget('tenant_org:snapshot:'.$tenantId);
    }
}
