<?php

namespace App\Support;

use App\Models\Customer;

final class CustomerPackageLabel
{
    public static function for(?Customer $record): string
    {
        if ($record === null) {
            return '—';
        }

        $meta = is_array($record->meta) ? $record->meta : [];
        $display = trim((string) ($meta['isp_digital_package_label'] ?? ''));
        $profile = trim((string) ($meta['mikrotik_profile'] ?? $record->package?->mikrotik_profile_name ?? ''));
        $monthly = (float) ($meta['isp_digital_monthly_bill'] ?? $record->package?->price_monthly ?? 0);
        $billSuffix = $monthly > 0 ? ' · '.number_format($monthly, 0).' BDT/mo' : '';

        if ($display !== '') {
            $label = $display;
            if ($profile !== '' && strcasecmp($display, $profile) !== 0) {
                $label = $display.' · '.$profile;
            }

            return $label.$billSuffix;
        }

        $name = trim((string) ($record->package?->name ?? ''));

        if ($name !== '') {
            return $name.$billSuffix;
        }

        return $monthly > 0 ? number_format($monthly, 0).' BDT/mo' : '—';
    }
}
