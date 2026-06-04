<?php

namespace App\Services\Reseller;

use App\Models\Reseller;

final class ResellerBrandingSettings
{
    public static function canManage(Reseller $reseller): bool
    {
        return $reseller->white_label_enabled && $reseller->is_active;
    }

    /**
     * @return array{company_tagline: string, company_address: string, invoice_footer: string}
     */
    public static function formState(Reseller $reseller): array
    {
        $id = (int) $reseller->id;

        return [
            'company_tagline' => (string) (ResellerScopedConfig::get($id, 'isp.company_tagline') ?? ''),
            'company_address' => (string) (ResellerScopedConfig::get($id, 'isp.company_address') ?? ''),
            'invoice_footer' => (string) (ResellerScopedConfig::get($id, 'isp.invoice_footer') ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function save(Reseller $reseller, array $input): void
    {
        $id = (int) $reseller->id;

        ResellerScopedConfig::put($id, 'isp.company_tagline', trim((string) ($input['company_tagline'] ?? '')));
        ResellerScopedConfig::put($id, 'isp.company_address', trim((string) ($input['company_address'] ?? '')));
        ResellerScopedConfig::put($id, 'isp.invoice_footer', trim((string) ($input['invoice_footer'] ?? '')));
    }

    public static function scopedTagline(Reseller $reseller): ?string
    {
        $value = ResellerScopedConfig::get((int) $reseller->id, 'isp.company_tagline');

        return filled($value) ? (string) $value : null;
    }

    public static function scopedAddress(Reseller $reseller): ?string
    {
        $value = ResellerScopedConfig::get((int) $reseller->id, 'isp.company_address');

        return filled($value) ? (string) $value : null;
    }

    public static function scopedInvoiceFooter(Reseller $reseller): ?string
    {
        $value = ResellerScopedConfig::get((int) $reseller->id, 'isp.invoice_footer');

        return filled($value) ? (string) $value : null;
    }
}
