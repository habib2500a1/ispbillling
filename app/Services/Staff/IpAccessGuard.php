<?php

namespace App\Services\Staff;

use App\Models\StaffSecuritySetting;
use App\Models\User;

class IpAccessGuard
{
    public function allows(?User $user, ?string $ip): bool
    {
        if ($user === null || $ip === null || $ip === '') {
            return true;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        $userIps = $this->normalizeList($user->allowed_ips);
        if ($userIps !== [] && ! $this->ipMatches($ip, $userIps)) {
            return false;
        }

        if ($user->branch_id && $user->branch) {
            $branchIps = $this->normalizeList($user->branch->allowed_ips);
            if ($branchIps !== [] && ! $this->ipMatches($ip, $branchIps)) {
                return false;
            }
        }

        $tenantId = $user->tenant_id;
        if ($tenantId === null) {
            return true;
        }

        $settings = StaffSecuritySetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($settings === null || ! $settings->ip_restriction_enabled) {
            return true;
        }

        $tenantIps = $this->normalizeList($settings->allowed_ips);

        return $tenantIps === [] || $this->ipMatches($ip, $tenantIps);
    }

    /**
     * @param  list<string>|null  $list
     * @return list<string>
     */
    public function normalizeList(?array $list): array
    {
        if ($list === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            $list
        )));
    }

    /**
     * @param  list<string>  $allowed
     */
    public function ipMatches(string $ip, array $allowed): bool
    {
        foreach ($allowed as $rule) {
            if ($rule === $ip) {
                return true;
            }
            if (str_contains($rule, '/') && $this->cidrMatch($ip, $rule)) {
                return true;
            }
            if (str_ends_with($rule, '*') && str_starts_with($ip, rtrim($rule, '*'))) {
                return true;
            }
        }

        return false;
    }

    private function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($subnet === null || $bits === null || ! is_numeric($bits)) {
            return false;
        }

        $bits = (int) $bits;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $mask = -1 << (32 - $bits);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);

            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        return false;
    }
}
