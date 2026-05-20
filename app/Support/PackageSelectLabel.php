<?php

namespace App\Support;

use App\Models\Package;

final class PackageSelectLabel
{
    public static function for(Package $package): string
    {
        $parts = [trim((string) $package->name)];

        if (filled($package->mikrotik_profile_name)) {
            $parts[] = 'MT: '.$package->mikrotik_profile_name;
        }

        if ($package->download_mbps > 0 || $package->upload_mbps > 0) {
            $parts[] = sprintf(
                '%s/%s Mbps',
                rtrim(rtrim(number_format((float) $package->download_mbps, 1), '0'), '.'),
                rtrim(rtrim(number_format((float) ($package->upload_mbps ?? $package->download_mbps), 1), '0'), '.'),
            );
        }

        if ((float) $package->price_monthly > 0) {
            $parts[] = number_format((float) $package->price_monthly, 0).' BDT';
        }

        return implode(' · ', array_filter($parts));
    }

    /**
     * Hide raw MikroTik profile imports until renamed (friendly name + price set).
     */
    public static function eligibleForCustomerSelectQuery(): \Closure
    {
        return function (\Illuminate\Database\Eloquent\Builder $query): void {
            $query->where('is_active', true)
                ->where(function (\Illuminate\Database\Eloquent\Builder $q): void {
                    $q->whereNull('mikrotik_synced_at')
                        ->orWhereColumn('name', '!=', 'mikrotik_profile_name')
                        ->orWhere('price_monthly', '>', 0);
                })
                ->orderBy('name');
        };
    }
}
