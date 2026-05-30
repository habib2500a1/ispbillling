<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Services\Payments\BkashCheckoutService;
use Carbon\Carbon;
use Throwable;

final class BkashSettings
{
    public const GATEWAY_TOKENIZED_WEB = 'tokenized_web';

    public const GATEWAY_PERSONAL = 'personal';

    public const ENV_SANDBOX = 'sandbox';

    public const ENV_LIVE = 'live';

    public const CHANNEL_ADMIN = 'admin';

    public const CHANNEL_PUBLIC_PAY = 'public_pay';

    public const CHANNEL_PORTAL = 'portal';

    public const CHANNEL_RESELLER = 'reseller';

    public const SANDBOX_BASE_URL = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';

    public const LIVE_BASE_URL = 'https://tokenized.pay.bka.sh/v1.2.0-beta';

    /**
     * @return array<string, string>
     */
    public static function channelLabels(): array
    {
        return [
            self::CHANNEL_ADMIN => 'Admin panel (staff pay on invoice)',
            self::CHANNEL_PUBLIC_PAY => 'Public bill payment (/pay)',
            self::CHANNEL_PORTAL => 'Customer portal (logged-in)',
            self::CHANNEL_RESELLER => 'Reseller portal',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allChannels(): array
    {
        return array_keys(self::channelLabels());
    }

    /**
     * @return list<string>
     */
    public static function enabledChannels(): array
    {
        $channels = config('bkash.channels');

        if (! is_array($channels) || $channels === []) {
            return self::allChannels();
        }

        return array_values(array_intersect(self::allChannels(), $channels));
    }

    public static function isPersonalEnabled(): bool
    {
        if (AppSetting::getStoredValue('bkash.personal_enabled') !== null) {
            return self::configFlag('bkash.personal_enabled');
        }

        return (string) config('bkash.gateway_type', self::GATEWAY_TOKENIZED_WEB) === self::GATEWAY_PERSONAL
            && filled(config('bkash.personal_number'));
    }

    public static function isMerchantEnabled(): bool
    {
        if (AppSetting::getStoredValue('bkash.merchant_enabled') !== null) {
            return self::configFlag('bkash.merchant_enabled');
        }

        return self::configFlag('bkash.enabled')
            && (string) config('bkash.gateway_type', self::GATEWAY_TOKENIZED_WEB) !== self::GATEWAY_PERSONAL;
    }

    public static function isPaymentEnabled(): bool
    {
        return self::isPersonalEnabled() || self::isMerchantEnabled();
    }

    public static function isWithinSchedule(): bool
    {
        $activation = config('bkash.activation_date');
        if (is_string($activation) && $activation !== '') {
            $start = Carbon::parse($activation)->startOfDay();
            if (now()->lt($start)) {
                return false;
            }
        }

        $expiry = config('bkash.expiry_date');
        if (is_string($expiry) && $expiry !== '') {
            $end = Carbon::parse($expiry)->endOfDay();
            if (now()->gt($end)) {
                return false;
            }
        }

        return true;
    }

    /** @deprecated Use isMerchantEnabled() */
    public static function isMerchantApiMode(): bool
    {
        return self::isMerchantEnabled();
    }

    public static function isConfigured(): bool
    {
        foreach (['app_key', 'app_secret', 'username', 'password'] as $key) {
            if (! filled(config("bkash.{$key}"))) {
                return false;
            }
        }

        return true;
    }

    public static function isPersonalActiveForChannel(string $channel): bool
    {
        return self::isPersonalEnabled()
            && filled(config('bkash.personal_number'))
            && in_array($channel, self::enabledChannels(), true);
    }

    public static function isMerchantActiveForChannel(string $channel): bool
    {
        return self::isMerchantEnabled()
            && self::isWithinSchedule()
            && self::isConfigured()
            && in_array($channel, self::enabledChannels(), true);
    }

    public static function isEnabledForChannel(string $channel): bool
    {
        return self::isPersonalActiveForChannel($channel)
            || self::isMerchantActiveForChannel($channel);
    }

    /** @deprecated Use isPersonalActiveForChannel / isMerchantActiveForChannel */
    public static function isActiveForChannel(string $channel): bool
    {
        return self::isEnabledForChannel($channel);
    }

    public static function isGloballyActive(): bool
    {
        return self::isConfigured()
            && self::isMerchantEnabled()
            && self::isWithinSchedule()
            && self::enabledChannels() !== [];
    }

    /**
     * @return array{personal: bool, merchant: bool, personal_number: string, merchant_configured: bool}
     */
    public static function statusSummary(): array
    {
        return [
            'personal' => self::isPersonalEnabled() && filled(config('bkash.personal_number')),
            'merchant' => self::isMerchantEnabled(),
            'personal_number' => (string) config('bkash.personal_number', ''),
            'merchant_configured' => self::isConfigured(),
        ];
    }

    public static function modeLabel(): string
    {
        $s = self::statusSummary();
        $parts = [];
        if ($s['personal']) {
            $parts[] = 'Personal (TrxID)';
        }
        if ($s['merchant']) {
            $parts[] = $s['merchant_configured'] ? 'Merchant API' : 'Merchant API (credentials missing)';
        }

        return $parts !== [] ? implode(' + ', $parts) : 'Off';
    }

    public static function syncMasterEnabledFlag(): void
    {
        $on = self::isPersonalEnabled() || self::isMerchantEnabled();
        config(['bkash.enabled' => $on]);
    }

    public static function baseUrlForEnvironment(string $environment): string
    {
        return $environment === self::ENV_LIVE
            ? self::LIVE_BASE_URL
            : self::SANDBOX_BASE_URL;
    }

    public static function detectEnvironmentFromBaseUrl(?string $baseUrl): string
    {
        $url = strtolower((string) $baseUrl);

        return str_contains($url, 'sandbox') ? self::ENV_SANDBOX : self::ENV_LIVE;
    }

    public static function callbackUrl(): string
    {
        $override = config('bkash.callback_url');

        if (is_string($override) && $override !== '') {
            return rtrim($override, '/');
        }

        return self::defaultCallbackUrl();
    }

    public static function callbackUrlHint(): string
    {
        return 'Must exactly match the URL registered in bKash merchant portal. '
            .'If your site is http://72.18.215.205, register that exact callback there — '
            .'bKash often rejects unlisted IPs/domains with HTTP 403.';
    }

    public static function defaultCallbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/bkash/callback';
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function testConnection(): array
    {
        if (! self::isConfigured()) {
            return ['ok' => false, 'message' => 'App key, secret, username and password are required.'];
        }

        try {
            $service = BkashCheckoutService::fromConfig();
            $service->assertConfigured();
            $token = $service->grantToken();

            if ($token === '') {
                return ['ok' => false, 'message' => 'bKash returned an empty token.'];
            }

            return ['ok' => true, 'message' => 'Connected successfully. Token received from bKash API.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $channels
     */
    public static function channelsToStorage(array $channels): string
    {
        $valid = array_values(array_intersect(self::allChannels(), $channels));

        return implode(',', $valid);
    }

    /**
     * @return list<string>
     */
    public static function channelsFromStorage(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return self::allChannels();
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $value))));

        return array_values(array_intersect(self::allChannels(), $parts));
    }

    private static function configFlag(string $key): bool
    {
        return filter_var(config($key), FILTER_VALIDATE_BOOLEAN);
    }
}
