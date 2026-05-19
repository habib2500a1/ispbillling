<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class CompanyBranding
{
    public static function name(): string
    {
        return (string) config('isp.company_name', config('app.name', 'ISP'));
    }

    public static function tagline(): string
    {
        return (string) config('isp.company_tagline', '');
    }

    public static function phone(): string
    {
        return (string) config('isp.company_phone', '');
    }

    public static function email(): string
    {
        return (string) config('isp.company_email', '');
    }

    public static function address(): string
    {
        return (string) config('isp.company_address', '');
    }

    public static function website(): string
    {
        return (string) config('isp.company_website', '');
    }

    public static function taxId(): string
    {
        return (string) config('isp.company_tax_id', '');
    }

    public static function logoUrl(): ?string
    {
        $path = (string) config('isp.company_logo_path', '');
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        $legacy = trim((string) config('isp.company_logo_url', ''));

        return $legacy !== '' ? $legacy : null;
    }

    public static function logoAbsolutePath(): ?string
    {
        $path = (string) config('isp.company_logo_path', '');
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        return null;
    }

    public static function invoiceShowLogo(): bool
    {
        return (bool) config('isp.invoice_show_logo', true);
    }

    public static function invoiceFooter(): string
    {
        return (string) config('isp.invoice_footer', '');
    }

    public static function invoiceTerms(): string
    {
        return (string) config('isp.invoice_terms', '');
    }

    /**
     * @return list<string>
     */
    public static function contactLines(): array
    {
        $lines = [];
        if ($phone = self::phone()) {
            $lines[] = 'Phone: '.$phone;
        }
        if ($email = self::email()) {
            $lines[] = 'Email: '.$email;
        }
        if ($web = self::website()) {
            $lines[] = $web;
        }
        if ($tax = self::taxId()) {
            $lines[] = 'Tax / BIN: '.$tax;
        }

        return $lines;
    }
}
