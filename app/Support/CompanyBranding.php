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
        $path = self::resolveLogoStoragePath();
        if ($path !== null) {
            return self::versionedAssetUrl(Storage::disk('public')->url($path));
        }

        $legacy = trim((string) config('isp.company_logo_url', ''));

        return $legacy !== '' ? self::versionedAssetUrl($legacy) : null;
    }

    public static function faviconUrl(): ?string
    {
        if (Storage::disk('public')->exists(\App\Services\Branding\FaviconGenerator::OUTPUT_32)) {
            return self::versionedAssetUrl(Storage::disk('public')->url(\App\Services\Branding\FaviconGenerator::OUTPUT_32));
        }

        $logo = self::logoUrl();
        if ($logo !== null) {
            return $logo;
        }

        $publicFavicon = public_path('favicon.png');
        if (is_file($publicFavicon) && filesize($publicFavicon) > 0) {
            return self::versionedAssetUrl(asset('favicon.png'));
        }

        return null;
    }

    private static function versionedAssetUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return $url;
        }

        $full = public_path(ltrim($path, '/'));
        if (is_file($full)) {
            return $url.'?v='.filemtime($full);
        }

        $storagePrefix = '/storage/';
        if (str_starts_with($path, $storagePrefix)) {
            $storageRelative = substr($path, strlen($storagePrefix));
            $diskPath = storage_path('app/public/'.$storageRelative);
            if (is_file($diskPath)) {
                return $url.'?v='.filemtime($diskPath);
            }
        }

        return $url;
    }

    public static function logoAbsolutePath(): ?string
    {
        $path = self::resolveLogoStoragePath();

        return $path !== null ? Storage::disk('public')->path($path) : null;
    }

    public static function brandInitial(): string
    {
        $name = trim(self::name());

        return $name !== ''
            ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'))
            : 'I';
    }

    /**
     * Configured logo path, or newest image in storage/app/public/company-branding.
     */
    public static function resolveLogoStoragePath(): ?string
    {
        $path = (string) config('isp.company_logo_path', '');
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return $path;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists('company-branding')) {
            return null;
        }

        $candidates = collect($disk->files('company-branding'))
            ->filter(fn (string $file): bool => (bool) preg_match('/\.(png|jpe?g|webp|gif)$/i', $file))
            ->sortByDesc(fn (string $file): int => $disk->lastModified($file))
            ->values();

        return $candidates->first();
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
