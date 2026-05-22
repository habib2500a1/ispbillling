<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    /**
     * Apply stored settings over config (runs after config files load).
     * Keys use dot notation matching config(), e.g. bkash.enabled, isp.tenant_base_domain.
     */
    public static function syncToRuntimeConfig(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        foreach (static::query()->cursor() as $row) {
            try {
                $raw = $row->value;
                if ($raw === null || $raw === '') {
                    continue;
                }
                config([$row->key => self::castValueForConfigKey($row->key, $raw)]);
            } catch (\Throwable $e) {
                Log::channel('single')->warning('app_settings.sync_row_failed', [
                    'key' => $row->key,
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }
        }

        self::syncPublicPaymentGatewayFlags();
        self::applyRadiusDatabaseConnection();
        self::applyApplicationTimezone();
    }

    /**
     * Overlay RADIUS DB credentials from panel (falls back to .env when keys empty).
     */
    public static function applyRadiusDatabaseConnection(): void
    {
        $host = (string) config('radius.db.host', '');
        if ($host === '') {
            return;
        }

        $connection = array_merge(
            config('database.connections.radius', []),
            array_filter([
                'driver' => config('radius.db.driver', env('RADIUS_DB_DRIVER', 'mysql')),
                'host' => $host,
                'port' => (string) config('radius.db.port', '3306'),
                'database' => (string) config('radius.db.database', 'radius'),
                'username' => (string) config('radius.db.username', 'radius'),
                'password' => (string) config('radius.db.password', ''),
            ], fn ($v): bool => $v !== null && $v !== ''),
        );

        config(['database.connections.radius' => $connection]);
    }

    public static function applyApplicationTimezone(): void
    {
        $zone = (string) config('app.timezone', 'Asia/Dhaka');

        if (! in_array($zone, timezone_identifiers_list(), true)) {
            $zone = 'Asia/Dhaka';
            config(['app.timezone' => $zone]);
        }

        config(['isp.timezone' => $zone]);

        date_default_timezone_set($zone);
    }

    /**
     * Public /pay page reads bill_payment.gateways; keep in sync with panel + AppSetting overrides.
     */
    public static function syncPublicPaymentGatewayFlags(): void
    {
        config([
            'bill_payment.gateways.bkash' => \App\Support\BkashSettings::isEnabledForChannel(
                \App\Support\BkashSettings::CHANNEL_PUBLIC_PAY
            ),
            'bill_payment.gateways.sslcommerz' => (bool) config('sslcommerz.enabled', false),
            'bill_payment.gateways.nagad' => (bool) config('nagad.enabled', false),
            'bill_payment.gateways.rocket' => (bool) config('rocket.enabled', false)
                && filled(config('rocket.merchant_number')),
            'bill_payment.gateways.piprapay' => \App\Services\Payments\PipraPayCheckoutService::isEnabled(),
        ]);
    }

    public static function getStoredValue(string $key): ?string
    {
        if (! Schema::hasTable('app_settings')) {
            return null;
        }

        $row = static::query()->where('key', $key)->first();

        return $row?->value;
    }

    public static function putValue(string $key, ?string $plainValue): void
    {
        if ($plainValue === null || $plainValue === '') {
            static::query()->where('key', $key)->delete();

            return;
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $plainValue],
        );

        Cache::forget('bootstrap.app_settings_sync');
        static::syncToRuntimeConfig();
    }

    public static function castValueForConfigKey(string $key, string $value): mixed
    {
        if ($key === 'isp.invoice_show_logo' || $key === 'billing.invoice_number_year_infix') {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        if (str_ends_with($key, '.enabled') || str_ends_with($key, '.auto_verify') || str_ends_with($key, '.sandbox')) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        if (str_ends_with($key, '.log_delivery_only')) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        if (str_ends_with($key, '.telegram_ops')) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        if (str_ends_with($key, '.channels')) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        if (str_starts_with($key, 'notifications.templates.')) {
            return $value;
        }

        if (str_ends_with($key, '.days_before')) {
            return max(1, min(30, (int) $value));
        }

        if ($key === 'portal.otp.ttl_seconds' || $key === 'bill_payment.otp.ttl_seconds') {
            $max = $key === 'bill_payment.otp.ttl_seconds' ? 1800 : 3600;

            return max(60, min($max, (int) $value));
        }

        if ($key === 'portal.otp.digits' || $key === 'bill_payment.otp.digits') {
            return max(4, min(8, (int) $value));
        }

        if (str_ends_with($key, '.http_timeout')) {
            return (int) $value;
        }

        if (str_ends_with($key, '.reminders_days_before') || $key === 'billing.default_billing_day') {
            return (int) $value;
        }

        if (str_ends_with($key, '.reminders_enabled')) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        if (str_ends_with($key, '.base_url')) {
            return rtrim($value, '/');
        }

        if ($key === 'bkash.channels') {
            return \App\Support\BkashSettings::channelsFromStorage($value);
        }

        if ($key === 'bkash.activation_date' || $key === 'bkash.expiry_date') {
            return $value === '' ? null : $value;
        }

        if ($key === 'subscriber.auto_generate_customer_code') {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        if ($key === 'subscriber.numeric_start') {
            return max(1, (int) $value);
        }

        if ($key === 'network.mikrotik_push_enabled' || $key === 'network.radius_push_enabled' || $key === 'network.service_expiry_enforced' || $key === 'network.mikrotik_always_push_ppp_on_customer_save' || $key === 'network.auto_suspend_enabled' || $key === 'radius.accounting_enabled' || $key === 'radius.merge_with_api' || $key === 'bandwidth.collection_enabled' || $key === 'mikrotik.poll_enabled' || $key === 'radius_admin.enabled') {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        if ($key === 'radius.db.port') {
            return max(1, min(65535, (int) $value));
        }

        if (str_starts_with($key, 'optical.') && (
            str_contains($key, 'threshold')
            || str_ends_with($key, '_min')
            || str_ends_with($key, '_max')
            || str_ends_with($key, '_above')
            || str_ends_with($key, '_db')
        )) {
            return (float) $value;
        }

        if (str_starts_with($key, 'optical.') && (
            str_ends_with($key, '.enabled')
            || str_contains($key, 'alert_on')
            || str_ends_with($key, 'auto_ticket_enabled')
            || str_ends_with($key, 'notify_ops')
            || str_ends_with($key, 'notify_customer_weak_signal')
        )) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $value;
    }

    /**
     * When a DB override is removed, fall back to the same env keys as config/*.php.
     */
    public static function restoreConfigKeyFromEnv(string $key): void
    {
        $map = [
            'bkash.enabled' => fn (): bool => (bool) config('bkash.env_defaults.enabled'),
            'bkash.gateway_type' => fn (): string => (string) config('bkash.env_defaults.gateway_type'),
            'bkash.environment' => fn (): string => (string) config('bkash.env_defaults.environment'),
            'bkash.base_url' => fn (): string => rtrim((string) config('bkash.env_defaults.base_url'), '/'),
            'bkash.activation_date' => fn (): ?string => config('bkash.env_defaults.activation_date'),
            'bkash.expiry_date' => fn (): ?string => config('bkash.env_defaults.expiry_date'),
            'bkash.channels' => fn (): array => config('bkash.env_defaults.channels', []),
            'bkash.app_key' => fn (): ?string => config('bkash.env_defaults.app_key'),
            'bkash.app_secret' => fn (): ?string => config('bkash.env_defaults.app_secret'),
            'bkash.username' => fn (): ?string => config('bkash.env_defaults.username'),
            'bkash.password' => fn (): ?string => config('bkash.env_defaults.password'),
            'bkash.http_timeout' => fn (): int => (int) config('bkash.env_defaults.http_timeout'),
            'bkash.callback_url' => fn (): ?string => config('bkash.env_defaults.callback_url'),
            'piprapay.base_url' => fn (): string => rtrim((string) config('piprapay.base_url'), '/'),
            'piprapay.public_url' => fn (): string => rtrim((string) env('PIPRAPAY_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),
            'app.timezone' => fn (): string => (string) config('isp.env_defaults.timezone', 'Asia/Dhaka'),
            'isp.timezone_label' => fn (): string => (string) config('isp.env_defaults.timezone_label', 'BDT'),
            'isp.tenant_base_domain' => fn (): string => (string) config('isp.env_defaults.tenant_base_domain'),
            'isp.company_name' => fn (): string => (string) config('isp.env_defaults.company_name'),
            'isp.company_tagline' => fn (): string => (string) config('isp.env_defaults.company_tagline'),
            'isp.company_phone' => fn (): string => (string) config('isp.env_defaults.company_phone'),
            'isp.company_email' => fn (): string => (string) config('isp.env_defaults.company_email'),
            'isp.company_address' => fn (): string => (string) config('isp.env_defaults.company_address'),
            'isp.company_website' => fn (): string => (string) config('isp.env_defaults.company_website'),
            'isp.company_tax_id' => fn (): string => (string) config('isp.env_defaults.company_tax_id'),
            'isp.company_logo_url' => fn (): string => (string) config('isp.env_defaults.company_logo_url'),
            'isp.company_logo_path' => fn (): string => (string) config('isp.env_defaults.company_logo_path'),
            'isp.company_favicon_path' => fn (): string => (string) config('isp.env_defaults.company_favicon_path'),
            'isp.platform_logo_path' => fn (): string => (string) config('isp.env_defaults.platform_logo_path'),
            'isp.platform_favicon_path' => fn (): string => (string) config('isp.env_defaults.platform_favicon_path'),
            'isp.invoice_show_logo' => fn (): bool => (bool) config('isp.env_defaults.invoice_show_logo'),
            'isp.invoice_footer' => fn (): string => (string) config('isp.env_defaults.invoice_footer'),
            'isp.invoice_terms' => fn (): string => (string) config('isp.env_defaults.invoice_terms'),
            'billing.invoice_number_prefix' => fn (): string => (string) config('billing.env_defaults.invoice_number_prefix'),
            'billing.invoice_number_year_infix' => fn (): bool => (bool) config('billing.env_defaults.invoice_number_year_infix'),
            'subscriber.auto_generate_customer_code' => fn (): bool => (bool) config('subscriber.auto_generate_customer_code', true),
            'subscriber.code_format' => fn (): string => (string) config('subscriber.code_format', 'prefixed_monthly'),
            'subscriber.code_prefix' => fn (): string => (string) config('subscriber.code_prefix', 'CUST'),
            'subscriber.numeric_start' => fn (): int => (int) config('subscriber.numeric_start', 10001),
            'sms.reminders_enabled' => fn (): bool => (bool) config('sms.env_defaults.reminders_enabled'),
            'sms.reminders_days_before' => fn (): int => (int) config('sms.env_defaults.reminders_days_before'),
            'notifications.log_delivery_only' => fn (): bool => (bool) config('notifications.env_defaults.log_delivery_only'),
            'notifications.email.enabled' => fn (): bool => (bool) config('notifications.env_defaults.email.enabled'),
            'notifications.sms.enabled' => fn (): bool => (bool) config('notifications.env_defaults.sms.enabled'),
            'notifications.sms.provider' => fn (): string => (string) config('notifications.env_defaults.sms.provider'),
            'notifications.sms.api_url' => fn (): string => (string) config('notifications.env_defaults.sms.api_url'),
            'notifications.sms.api_key' => fn (): ?string => config('notifications.env_defaults.sms.api_key'),
            'notifications.sms.secret_key' => fn (): ?string => config('notifications.env_defaults.sms.secret_key'),
            'notifications.sms.sender_id' => fn (): string => (string) config('notifications.env_defaults.sms.sender_id'),
            'notifications.whatsapp.enabled' => fn (): bool => (bool) config('notifications.env_defaults.whatsapp.enabled'),
            'notifications.whatsapp.phone_number_id' => fn (): ?string => config('notifications.env_defaults.whatsapp.phone_number_id'),
            'notifications.whatsapp.access_token' => fn (): ?string => config('notifications.env_defaults.whatsapp.access_token'),
            'notifications.telegram.enabled' => fn (): bool => (bool) config('notifications.env_defaults.telegram.enabled'),
            'notifications.telegram.bot_token' => fn (): ?string => config('notifications.env_defaults.telegram.bot_token'),
            'notifications.telegram.ops_chat_id' => fn (): ?string => config('notifications.env_defaults.telegram.ops_chat_id'),
            'notifications.events.payment_success.enabled' => fn (): bool => (bool) config('notifications.env_defaults.events.payment_success.enabled'),
            'notifications.events.invoice_due.enabled' => fn (): bool => (bool) config('notifications.env_defaults.events.invoice_due.enabled'),
            'notifications.events.invoice_due.days_before' => fn (): int => (int) config('notifications.env_defaults.events.invoice_due.days_before'),
            'network.provisioner_driver' => fn (): string => (string) config('network.env_defaults.provisioner_driver'),
            'network.mikrotik_push_enabled' => fn (): bool => (bool) config('network.env_defaults.mikrotik_push_enabled'),
            'network.radius_push_enabled' => fn (): bool => (bool) config('network.env_defaults.radius_push_enabled'),
            'network.service_expiry_enforced' => fn (): bool => (bool) config('network.env_defaults.service_expiry_enforced'),
            'network.mikrotik_always_push_ppp_on_customer_save' => fn (): bool => (bool) config('network.env_defaults.mikrotik_always_push_ppp_on_customer_save'),
            'network.auto_suspend_enabled' => fn (): bool => (bool) config('network.env_defaults.auto_suspend_enabled'),
            'radius.accounting_enabled' => fn (): bool => (bool) env('RADIUS_ACCOUNTING_ENABLED', false),
            'radius.merge_with_api' => fn (): bool => (bool) env('RADIUS_MERGE_WITH_API', true),
            'bandwidth.collection_enabled' => fn (): bool => (bool) env('BANDWIDTH_COLLECTION_ENABLED', true),
            'mikrotik.poll_enabled' => fn (): bool => (bool) env('MIKROTIK_POLL_STATUS_ENABLED', true),
            'radius_admin.enabled' => fn (): bool => (bool) env('RADIUS_ADMIN_ENABLED', false),
            'radius.db.host' => fn (): string => (string) env('RADIUS_DB_HOST', '127.0.0.1'),
            'radius.db.port' => fn (): string => (string) env('RADIUS_DB_PORT', '3306'),
            'radius.db.database' => fn (): string => (string) env('RADIUS_DB_DATABASE', 'radius'),
            'radius.db.username' => fn (): string => (string) env('RADIUS_DB_USERNAME', 'radius'),
            'radius.db.password' => fn (): string => (string) env('RADIUS_DB_PASSWORD', ''),
            'network.service_expiry_enforced' => fn (): bool => (bool) config('network.env_defaults.service_expiry_enforced'),
            'portal.enabled' => fn (): bool => (bool) config('portal.env_defaults.enabled', true),
            'portal.otp.enabled' => fn (): bool => (bool) config('portal.env_defaults.otp_enabled'),
            'portal.otp.log_delivery_only' => fn (): bool => (bool) config('portal.env_defaults.otp_log_delivery_only'),
            'portal.otp.ttl_seconds' => fn (): int => (int) config('portal.env_defaults.otp_ttl_seconds'),
            'portal.otp.digits' => fn (): int => (int) config('portal.env_defaults.otp_digits'),
            'bill_payment.otp.enabled' => fn (): bool => (bool) config('bill_payment.env_defaults.otp_enabled'),
            'bill_payment.otp.log_delivery_only' => fn (): bool => (bool) config('bill_payment.env_defaults.otp_log_delivery_only'),
            'bill_payment.otp.ttl_seconds' => fn (): int => (int) config('bill_payment.env_defaults.otp_ttl_seconds'),
            'bill_payment.otp.digits' => fn (): int => (int) config('bill_payment.env_defaults.otp_digits'),
            'optical.rx_thresholds.excellent_max' => fn (): float => (float) config('optical.rx_thresholds.excellent_max'),
            'optical.rx_thresholds.excellent_min' => fn (): float => (float) config('optical.rx_thresholds.excellent_min'),
            'optical.rx_thresholds.good_min' => fn (): float => (float) config('optical.rx_thresholds.good_min'),
            'optical.rx_thresholds.weak_min' => fn (): float => (float) config('optical.rx_thresholds.weak_min'),
            'optical.rx_high_warn_above' => fn (): float => (float) config('optical.rx_high_warn_above'),
            'optical.tx_normal_min' => fn (): float => (float) config('optical.tx_normal_min'),
            'optical.tx_normal_max' => fn (): float => (float) config('optical.tx_normal_max'),
            'optical.tx_high_warn_above' => fn (): float => (float) config('optical.tx_high_warn_above'),
            'optical.sudden_drop_db' => fn (): float => (float) config('optical.sudden_drop_db'),
            'optical.alert_on_high_rx' => fn (): bool => (bool) config('optical.alert_on_high_rx'),
            'optical.alert_on_high_tx' => fn (): bool => (bool) config('optical.alert_on_high_tx'),
            'optical.auto_ticket_enabled' => fn (): bool => (bool) config('optical.auto_ticket_enabled'),
            'optical.notify_ops' => fn (): bool => (bool) config('optical.notify_ops'),
        ];

        if (isset($map[$key])) {
            config([$key => $map[$key]()]);
        }
    }
}
