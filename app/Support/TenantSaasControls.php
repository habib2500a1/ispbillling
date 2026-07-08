<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Per-tenant SaaS locks — rented/sub ISPs cannot resell the platform or spin up partners
 * unless the platform super-admin explicitly enables it on their tenant record.
 */
final class TenantSaasControls
{
    public const KEY_RESELLER_CREATION = 'allow_reseller_creation';

    public const KEY_STAFF_ADMIN_ROLES = 'allow_staff_admin_roles';

    /** @return array<string, bool> */
    public static function defaultsForNewTenant(bool $isPrimary): array
    {
        return [
            self::KEY_RESELLER_CREATION => $isPrimary,
            self::KEY_STAFF_ADMIN_ROLES => $isPrimary,
        ];
    }

    public static function allowsResellerCreation(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return true;
        }

        if (PrimaryTenant::isPrimary($tenant->getKey())) {
            return (bool) self::read($tenant, self::KEY_RESELLER_CREATION, true);
        }

        return (bool) self::read($tenant, self::KEY_RESELLER_CREATION, false);
    }

    public static function allowsStaffAdminRoles(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return true;
        }

        if (PrimaryTenant::isPrimary($tenant->getKey())) {
            return (bool) self::read($tenant, self::KEY_STAFF_ADMIN_ROLES, true);
        }

        return (bool) self::read($tenant, self::KEY_STAFF_ADMIN_ROLES, false);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function mergeIntoSettings(array $settings, bool $allowResellers, bool $allowStaffAdminRoles): array
    {
        $controls = is_array($settings['platform_controls'] ?? null) ? $settings['platform_controls'] : [];
        $controls[self::KEY_RESELLER_CREATION] = $allowResellers;
        $controls[self::KEY_STAFF_ADMIN_ROLES] = $allowStaffAdminRoles;
        $settings['platform_controls'] = $controls;

        return $settings;
    }

    private static function read(Tenant $tenant, string $key, bool $default): bool
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $controls = is_array($settings['platform_controls'] ?? null) ? $settings['platform_controls'] : [];

        if (! array_key_exists($key, $controls)) {
            return $default;
        }

        return (bool) $controls[$key];
    }
}
