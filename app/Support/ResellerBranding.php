<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Reseller;
use App\Services\Reseller\ResellerBrandingSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class ResellerBranding
{
    public static function customerFromContext(): ?Customer
    {
        $auth = auth('customer')->user();
        if ($auth instanceof Customer) {
            return $auth;
        }

        $customerId = session('bill_pay.customer_id');
        if ($customerId && session('bill_pay.verified')) {
            return Customer::query()->withoutGlobalScopes()->find((int) $customerId);
        }

        return null;
    }

    public static function activeReseller(?Customer $customer = null): ?Reseller
    {
        self::bindPartnerFromSession();

        if (app()->bound('reseller.white_label')) {
            $bound = app('reseller.white_label');
            if ($bound instanceof Reseller && $bound->white_label_enabled && $bound->is_active) {
                return $bound;
            }
        }

        $customer ??= self::customerFromContext();
        if ($customer === null || ! $customer->reseller_id) {
            return null;
        }

        $reseller = Reseller::query()->withoutGlobalScopes()->find($customer->reseller_id);
        if ($reseller instanceof Reseller && $reseller->white_label_enabled && $reseller->is_active) {
            return $reseller;
        }

        return null;
    }

    public static function capturePartnerFromRequest(Request $request): void
    {
        $partner = trim((string) $request->query('partner', ''));
        if ($partner === '') {
            self::bindPartnerFromSession();

            return;
        }

        $reseller = self::findPartnerByHint($partner);
        if ($reseller instanceof Reseller) {
            $request->session()->put('branding.reseller_id', (int) $reseller->id);
            app()->instance('reseller.white_label', $reseller);

            return;
        }

        self::bindPartnerFromSession();
    }

    public static function bindPartnerFromSession(): void
    {
        if (app()->bound('reseller.white_label')) {
            return;
        }

        $id = (int) session('branding.reseller_id', 0);
        if ($id < 1) {
            return;
        }

        $reseller = Reseller::query()->withoutGlobalScopes()->find($id);
        if ($reseller instanceof Reseller && $reseller->white_label_enabled && $reseller->is_active) {
            app()->instance('reseller.white_label', $reseller);
        }
    }

    /**
     * @return array{pay: string, portal_login: string, reseller_portal: string, subdomain_portal?: string, subdomain_pay?: string}
     */
    public static function shareableLinks(Reseller $reseller): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $partnerHint = filled($reseller->portal_subdomain)
            ? (string) $reseller->portal_subdomain
            : (string) $reseller->code;
        $query = '?partner='.urlencode($partnerHint);

        $links = [
            'pay' => $base.'/pay'.$query,
            'portal_login' => $base.'/login'.$query,
            'reseller_portal' => $base.'/reseller/login',
        ];

        $tenantBase = strtolower(trim((string) config('isp.tenant_base_domain', '')));
        if ($tenantBase !== '' && filled($reseller->portal_subdomain)) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            $host = strtolower((string) $reseller->portal_subdomain).'.'.$tenantBase;
            $links['subdomain_portal'] = $scheme.'://'.$host.'/login';
            $links['subdomain_pay'] = $scheme.'://'.$host.'/pay';
        }

        return $links;
    }

    public static function findPartnerByHint(string $hint): ?Reseller
    {
        $hint = trim($hint);
        if ($hint === '') {
            return null;
        }

        return Reseller::query()
            ->withoutGlobalScopes()
            ->where('white_label_enabled', true)
            ->where('is_active', true)
            ->where(function ($query) use ($hint): void {
                $query->where('code', $hint)
                    ->orWhere('portal_subdomain', $hint)
                    ->orWhere('portal_login', $hint);
            })
            ->first();
    }

    /**
     * @return array{
     *   companyName: string,
     *   companyLogo: ?string,
     *   companyTagline: string,
     *   companyPhone: string,
     *   whiteLabelReseller: ?Reseller,
     *   whiteLabelPrimaryColor: ?string
     * }
     */
    public static function forCustomer(?Customer $customer = null): array
    {
        $reseller = self::activeReseller($customer);

        if ($reseller === null) {
            return [
                'companyName' => CompanyBranding::name(),
                'companyLogo' => CompanyBranding::logoUrl(),
                'companyTagline' => CompanyBranding::tagline(),
                'companyPhone' => CompanyBranding::phone(),
                'companyAddress' => CompanyBranding::address(),
                'whiteLabelReseller' => null,
                'whiteLabelPrimaryColor' => null,
            ];
        }

        return [
            'companyName' => $reseller->displayName(),
            'companyLogo' => $reseller->logoUrl(),
            'companyTagline' => ResellerBrandingSettings::scopedTagline($reseller) ?? CompanyBranding::tagline(),
            'companyPhone' => filled($reseller->phone) ? (string) $reseller->phone : CompanyBranding::phone(),
            'companyAddress' => ResellerBrandingSettings::scopedAddress($reseller) ?? CompanyBranding::address(),
            'whiteLabelReseller' => $reseller,
            'whiteLabelPrimaryColor' => filled($reseller->brand_primary_color)
                ? (string) $reseller->brand_primary_color
                : null,
        ];
    }

    public static function sslSetupGuide(?Reseller $reseller = null): string
    {
        $base = strtolower(trim((string) config('isp.tenant_base_domain', '')));
        $sub = $reseller !== null && filled($reseller->portal_subdomain)
            ? strtolower((string) $reseller->portal_subdomain)
            : 'partner1';

        if ($base === '') {
            return 'Set ISP_TENANT_BASE_DOMAIN in .env to enable partner subdomains (e.g. isp.example.com). '
                .'Until then, share /pay?partner=CODE and /login?partner=CODE links — no extra DNS or SSL needed.';
        }

        return implode("\n", [
            '1. DNS: wildcard A/CNAME  *.'.$base.'  → your server IP',
            '2. SSL: wildcard certificate for *.'.$base.' (Let\'s Encrypt DNS challenge, or Cloudflare proxy)',
            '3. Nginx: server_name '.$sub.'.'.$base.'; use same Laravel root as main site',
            '4. Admin: set partner Portal subdomain to "'.$sub.'" (lowercase, no spaces)',
            '5. Test: https://'.$sub.'.'.$base.'/login — should show partner branding',
            'Fallback without DNS: '.$sub.' links via ?partner='.$sub.' on main domain',
        ]);
    }

    public static function logoUrlForReseller(Reseller $reseller): ?string
    {
        $path = trim((string) $reseller->brand_logo_path);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return self::versionedAssetUrl(Storage::disk('public')->url($path));
    }

    public static function logoAbsolutePathForReseller(Reseller $reseller): ?string
    {
        $path = trim((string) $reseller->brand_logo_path);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    /**
     * Letterhead variables for PDF views (invoice, receipt).
     *
     * @return array{
     *   letterheadName: string,
     *   letterheadLogoPath: ?string,
     *   letterheadShowLogo: bool,
     *   letterheadTagline: string,
     *   letterheadAddress: string,
     *   letterheadContactLines: list<string>,
     *   invoiceFooter: string
     * }
     */
    public static function letterheadVars(?Customer $customer = null): array
    {
        $branding = self::forCustomer($customer);
        $reseller = $branding['whiteLabelReseller'];

        if ($reseller instanceof Reseller) {
            $logoPath = self::logoAbsolutePathForReseller($reseller);
            $contactLines = [];
            if (filled($reseller->phone)) {
                $contactLines[] = 'Phone: '.(string) $reseller->phone;
            }
            if (filled($reseller->email)) {
                $contactLines[] = 'Email: '.(string) $reseller->email;
            }

            return [
                'letterheadName' => $branding['companyName'],
                'letterheadLogoPath' => $logoPath,
                'letterheadShowLogo' => $logoPath !== null,
                'letterheadTagline' => $branding['companyTagline'],
                'letterheadAddress' => $branding['companyAddress'] ?? '',
                'letterheadContactLines' => $contactLines,
                'invoiceFooter' => ResellerBrandingSettings::scopedInvoiceFooter($reseller)
                    ?? CompanyBranding::invoiceFooter(),
            ];
        }

        return [
            'letterheadName' => CompanyBranding::name(),
            'letterheadLogoPath' => CompanyBranding::logoAbsolutePath(),
            'letterheadShowLogo' => CompanyBranding::invoiceShowLogo() && CompanyBranding::logoAbsolutePath() !== null,
            'letterheadTagline' => CompanyBranding::tagline(),
            'letterheadAddress' => CompanyBranding::address(),
            'letterheadContactLines' => CompanyBranding::contactLines(),
            'invoiceFooter' => CompanyBranding::invoiceFooter(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mobileBrandingPayload(?Customer $customer = null): array
    {
        $branding = self::forCustomer($customer);

        return [
            'company_name' => $branding['companyName'],
            'tagline' => $branding['companyTagline'],
            'logo_url' => $branding['companyLogo'],
            'phone' => $branding['companyPhone'],
            'email' => CompanyBranding::email(),
            'website' => CompanyBranding::website(),
            'address' => $branding['companyAddress'] ?? CompanyBranding::address(),
            'primary_color' => $branding['whiteLabelPrimaryColor'],
            'white_label' => $branding['whiteLabelReseller'] !== null,
        ];
    }

    private static function versionedAssetUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return $url;
        }

        $storagePrefix = '/storage/';
        if (str_starts_with($path, $storagePrefix)) {
            $diskPath = storage_path('app/public/'.substr($path, strlen($storagePrefix)));
            if (is_file($diskPath)) {
                return $url.'?v='.filemtime($diskPath);
            }
        }

        return $url;
    }
}
