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

if (! function_exists('site_image')) {
    /**
     * Correctly resolve site images from public or storage.
     * Handles both string paths and Filament FileUpload array values.
     */
    function site_image($path, $fallback = 'images/logo.png')
    {
        // Filament FileUpload sometimes returns an array — unwrap it
        if (is_array($path)) {
            $path = array_values(array_filter($path))[0] ?? null;
        }

        if (! $path || ! is_string($path)) {
            return asset($fallback);
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // Path clean up
        $path = ltrim($path, '/');

        // Check 1: public folder
        if (file_exists(public_path($path))) {
            return asset($path);
        }

        // Check 2: storage folder (standard Filament/Laravel location)
        if (file_exists(public_path('storage/'.$path))) {
            return asset('storage/'.$path);
        }

        // Final fallback: asset with original path
        return asset($path);
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
        if (\Illuminate\Support\Facades\Route::has('filament.portal.auth.login')) {
            return route('filament.portal.auth.login');
        }

        return url('/portal/login');
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
