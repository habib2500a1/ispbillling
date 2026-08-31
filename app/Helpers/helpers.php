<?php

use App\Http\Controllers\AvatarController;
use App\Models\MainSiteData;

/**
 * Created by Md. Jahangir Alam Rohan.
 * User: Md. Jahangir Alam Rohan.
 * Date: 25-Jun-2024
 * Time: 03.01 PM
 */

if (! function_exists('site_brand')) {
    function site_brand(): string
    {
        $tenant = \App\Services\Saas\SaasContext::hostOperator()
            ?? \App\Services\Saas\SaasContext::operator();
        if ($tenant && filled($tenant->company)) {
            return $tenant->company;
        }

        $name = siteUrlSettings('site_name');
        $legacy = ['sam online', 'samonline', 'code pagol', 'codepagol', 'isp billing', 'laravel'];
        if (! is_string($name) || trim($name) === '' || in_array(strtolower(trim($name)), $legacy, true)) {
            $fromConfig = (string) (config('app.name') ?: 'Anetbd');

            return in_array(strtolower(trim($fromConfig)), $legacy, true) ? 'Anetbd' : $fromConfig;
        }

        return $name;
    }
}

if (! function_exists('siteUrlSettings')) {
    function siteUrlSettings($key, $default = null)
    {
        static $hasTable = null;
        static $runtimeCache = [];

        try {
            if ($hasTable === null) {
                $hasTable = \Illuminate\Support\Facades\Schema::hasTable('main_site_data');
            }
            if (! $hasTable) {
                return $default;
            }

            $cacheKey = (\App\Services\Saas\SaasContext::tenantId() ?: 0).':'.$key;
            if (array_key_exists($cacheKey, $runtimeCache)) {
                $cached = $runtimeCache[$cacheKey];

                return $cached === null ? $default : $cached;
            }

            $value = MainSiteData::getValue($key);
            if (in_array($key, ['site_logo', 'site_icon', 'site_favicon', 'site_invoice_logo', 'site_invoice_signature'], true)) {
                $value = site_asset_path($value);
            }
            $runtimeCache[$cacheKey] = $value;

            return $value === null ? $default : $value;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (! function_exists('generate_avatar')) {
    function generate_avatar($name)
    {
        $controller = app(AvatarController::class);

        return $controller->generateAvatar($name);
    }
}

if (! function_exists('hasAccess')) {
    function hasAccess(array $roles = [], array $permissions = []): bool
    {
        $user = auth()->user();

        return $user && (
            (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) ||
            (method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission($permissions))
        );
    }
}

if (! function_exists('abortIfNoAccess')) {
    function abortIfNoAccess(array $roles = [], array $permissions = [], string $message = 'You do not have permission.'): bool
    {
        if (! hasAccess($roles, $permissions)) {
            flash()->error($message);

            return true;
        }

        return false;
    }
}

if (! function_exists('warningIfNoAccess')) {
    function warningIfNoAccess(array $roles = [], array $permissions = [], string $message = 'You do not have permission.'): bool
    {
        if (! hasAccess($roles, $permissions)) {
            flash()->warning($message);

            return true;
        }

        return false;
    }
}

if (! function_exists('site_asset_path')) {
    /**
     * Unwrap Filament FileUpload arrays / JSON leftovers into a stored relative path.
     */
    function site_asset_path(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = array_values(array_filter($path, fn ($item) => $item !== null && $item !== '' && $item !== []))[0] ?? null;
        }

        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);
        if ($path === '' || $path === '[]' || $path === '{}' || $path === 'null') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? $path : null;
    }
}

if (! function_exists('site_image')) {
    /**
     * Correctly resolve site images from public or storage.
     * Handles both string paths and Filament FileUpload array values.
     */
    function site_image($path, $fallback = 'images/logo.png')
    {
        $path = site_asset_path($path);

        if (! $path) {
            return asset($fallback);
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        if (file_exists(public_path('storage/'.$path))) {
            return asset('storage/'.$path);
        }

        try {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                return asset('storage/'.$path);
            }
        } catch (\Throwable $e) {
            // Ignore missing disk during install.
        }

        return asset('storage/'.$path);
    }
}

if (! function_exists('site_invoice_image')) {
    function site_invoice_image(?string $fallback = 'images/logo.png'): string
    {
        $invoice = siteUrlSettings('site_invoice_logo');
        if (site_asset_path($invoice)) {
            return site_image($invoice, $fallback);
        }

        return site_image(siteUrlSettings('site_logo'), $fallback);
    }
}

if (! function_exists('staffHomeUrl')) {
    function staffHomeUrl(): string
    {
        $user = auth()->user();
        if (! $user) {
            return url('/');
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Reseller')) {
            return route('reseller.dashboard');
        }

        return route('dashboard');
    }
}

if (! function_exists('canSellSaas')) {
    /**
     * Platform owner only — Super Admin who is not a sold Operator.
     */
    function canSellSaas(): bool
    {
        $user = auth()->user();
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('Super Admin') && ! $user->hasRole('Operator');
    }
}

if (! function_exists('canManageMasterSetup')) {
    function canManageMasterSetup(): bool
    {
        return hasAccess(['Super Admin', 'Operator'], ['site-settings', 'site-setup', 'payment-setup']);
    }
}

if (! function_exists('canReviewAllCollections')) {
    function canReviewAllCollections(): bool
    {
        $user = auth()->user();
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        return canSellSaas() || isOperatorAdmin() || $user->hasRole('Super Admin');
    }
}

if (! function_exists('collectionCollectorAliases')) {
    /**
     * @return list<string>
     */
    function collectionCollectorAliases(string $collector): array
    {
        $collector = trim($collector);
        if ($collector === '') {
            return [];
        }

        $aliases = [$collector];
        try {
            $user = \App\Models\User::query()
                ->where(function ($q) use ($collector) {
                    $q->where('email', $collector)->orWhere('name', $collector);
                })
                ->first(['name', 'email']);
            if ($user) {
                $aliases[] = (string) $user->email;
                $aliases[] = (string) $user->name;
            }
        } catch (\Throwable) {
        }

        return array_values(array_unique(array_filter($aliases)));
    }
}

if (! function_exists('collectionCollectorChoices')) {
    function collectionCollectorChoices(?bool $seeAll = null): \Illuminate\Support\Collection
    {
        $seeAll ??= canReviewAllCollections();
        if (! $seeAll) {
            return collect([auth()->user()])->filter();
        }

        $query = \App\Models\User::query()->select('id', 'name', 'email')->orderBy('name');
        $operator = \App\Services\Saas\SaasContext::operator();
        if ($operator && ! canSellSaas()) {
            $query->where(function ($q) use ($operator) {
                $q->where('saas_operator_id', $operator->id)
                    ->orWhere('id', $operator->user_id);
            });
        }

        $users = $query->get();
        $known = $users->pluck('email')->filter()->map(fn ($email) => strtolower((string) $email))->all();

        try {
            $extras = \App\Models\CollectionSummary::query()
                ->select('collected_by')
                ->whereNotNull('collected_by')
                ->where('collected_by', '!=', '')
                ->distinct()
                ->pluck('collected_by');
            foreach ($extras as $raw) {
                $raw = trim((string) $raw);
                if ($raw === '' || in_array(strtolower($raw), $known, true)) {
                    continue;
                }
                $users->push(\App\Models\User::make([
                    'name' => collectorDisplayName($raw),
                    'email' => $raw,
                ]));
                $known[] = strtolower($raw);
            }
        } catch (\Throwable) {
        }

        return $users->sortBy(fn ($user) => strtolower((string) $user->name))->values();
    }
}

if (! function_exists('collectorDisplayName')) {
    function collectorDisplayName(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '—';
        }

        $key = strtolower($value);
        static $map = [];
        if (array_key_exists($key, $map)) {
            return $map[$key];
        }

        try {
            $user = \App\Models\User::query()
                ->where(function ($q) use ($value) {
                    $q->where('email', $value)->orWhere('name', $value);
                })
                ->first(['name']);
            $map[$key] = $user?->name ?: $value;
        } catch (\Throwable) {
            $map[$key] = $value;
        }

        return $map[$key];
    }
}

if (! function_exists('isOperatorAdmin')) {
    function isOperatorAdmin(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'hasRole') && $user->hasRole('Operator');
    }
}

if (! function_exists('currentSaasOperator')) {
    function currentSaasOperator(): ?\App\Models\SaasOperator
    {
        return \App\Services\Saas\SaasContext::operator();
    }
}

if (! function_exists('saasAssertQuota')) {
    function saasAssertQuota(string $resource): bool
    {
        try {
            app(\App\Services\Saas\SaasQuotaService::class)->assert($resource);

            return true;
        } catch (\App\Services\Saas\SaasQuotaException $e) {
            flash()->error($e->getMessage());

            return false;
        }
    }
}

if (! function_exists('saasBillingWarning')) {
    function saasBillingWarning(): ?string
    {
        $operator = currentSaasOperator();
        if (! $operator || ! $operator->next_due_at) {
            return null;
        }

        if ($operator->isAccessBlocked()) {
            return __('Subscription is locked. Pay the SaaS bill to unlock.');
        }

        if ($operator->next_due_at->lte(now()->addDays(7))) {
            return __('SaaS bill due :date — unpaid accounts lock automatically.', [
                'date' => $operator->next_due_at->format('d M Y'),
            ]);
        }

        return null;
    }
}

if (! function_exists('publicPayUrl')) {
    function publicPayUrl(?string $customerUniqueId = null): string
    {
        return $customerUniqueId
            ? url('/pay/'.$customerUniqueId)
            : url('/pay');
    }
}

if (! function_exists('portalLoginUrl')) {
    function portalLoginUrl(): string
    {
        // Always keep client login on the current host (platform or ISP custom domain).
        return url('/portal/login');
    }
}

if (! function_exists('adminLoginUrl')) {
    function adminLoginUrl(): string
    {
        return url('/login');
    }
}

if (! function_exists('whatsapp_url')) {
    function whatsapp_url(?string $mobile, string $message = ''): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '88'.$digits;
        }
        $url = 'https://wa.me/'.$digits;
        if (trim($message) !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }
}

if (! function_exists('whatsapp_button')) {
    function whatsapp_button(?string $mobile, string $message = ''): string
    {
        $url = whatsapp_url($mobile, $message);
        if (! $url) {
            return '';
        }

        return '<a href="'.e($url).'" target="_blank" rel="noopener" title="WhatsApp"'
            .' class="d-inline-flex align-items-center justify-content-center rounded-circle text-decoration-none ms-1"'
            .' style="width:18px;height:18px;background:#25D366;color:#fff;font-size:10px;line-height:1;vertical-align:text-bottom;">'
            .'<i class="bi bi-whatsapp"></i></a>';
    }
}
