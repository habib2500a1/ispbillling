<?php

namespace App\Services\Reseller;

use App\Models\Customer;
use App\Models\Reseller;

final class ResellerIntegrationSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function smsFormState(Reseller $reseller): array
    {
        return [
            'sms_enabled' => self::flag($reseller->id, 'notifications.sms.enabled', false),
            'sms_provider' => self::get($reseller->id, 'notifications.sms.provider', 'khudebarta'),
            'sms_api_url' => self::get($reseller->id, 'notifications.sms.api_url', 'http://portal.khudebarta.com:3775/sendtext'),
            'sms_sender_id' => self::get($reseller->id, 'notifications.sms.sender_id', 'ISP'),
            'sms_api_key_set' => filled(self::get($reseller->id, 'notifications.sms.api_key', '')),
            'sms_secret_key_set' => filled(self::get($reseller->id, 'notifications.sms.secret_key', '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function paymentFormState(Reseller $reseller): array
    {
        return [
            'bkash_enabled' => self::flag($reseller->id, 'bkash.personal_enabled', false),
            'bkash_personal_number' => self::get($reseller->id, 'bkash.personal_number', ''),
            'bkash_personal_name' => self::get($reseller->id, 'bkash.personal_name', $reseller->brand_name ?: $reseller->name),
            'nagad_enabled' => self::flag($reseller->id, 'nagad.personal_enabled', false),
            'nagad_personal_number' => self::get($reseller->id, 'nagad.personal_number', ''),
            'mfs_ingest_enabled' => self::flag($reseller->id, 'mfs_personal.sms_ingest.enabled', false),
            'mfs_device_key_set' => filled(self::get($reseller->id, 'mfs_personal.sms_ingest.api_key', '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Reseller $reseller): array
    {
        $sms = self::smsFormState($reseller);
        $pay = self::paymentFormState($reseller);

        return [
            'sms_active' => $sms['sms_enabled'] && $sms['sms_api_key_set'] && $sms['sms_secret_key_set'],
            'bkash_active' => $pay['bkash_enabled'] && filled($pay['bkash_personal_number']),
            'nagad_active' => $pay['nagad_enabled'] && filled($pay['nagad_personal_number']),
            'mfs_active' => $pay['mfs_ingest_enabled'] && $pay['mfs_device_key_set'],
        ];
    }

    public static function canManage(Reseller $reseller): bool
    {
        return $reseller->own_integrations_enabled
            && app(\App\Support\ResellerPortalSession::class)->canPortal(\App\Support\ResellerPortalPermission::INTEGRATIONS_MANAGE);
    }

    public static function usesOwnForCustomer(?Customer $customer): bool
    {
        if ($customer === null || ! $customer->reseller_id) {
            return false;
        }

        $reseller = Reseller::query()->withoutGlobalScopes()->find($customer->reseller_id);

        return $reseller instanceof Reseller && self::canManage($reseller);
    }

    public static function resellerForCustomer(?Customer $customer): ?Reseller
    {
        if ($customer === null || ! $customer->reseller_id) {
            return null;
        }

        $reseller = Reseller::query()->withoutGlobalScopes()->find($customer->reseller_id);

        return $reseller instanceof Reseller && self::canManage($reseller) ? $reseller : null;
    }

    /**
     * @param  array<string, mixed>  $smsInput
     */
    public static function saveSms(Reseller $reseller, array $smsInput): void
    {
        $id = (int) $reseller->id;
        $enabled = self::truthy($smsInput['sms_enabled'] ?? false);

        ResellerScopedConfig::put($id, 'notifications.sms.enabled', $enabled ? '1' : '0');
        ResellerScopedConfig::put($id, 'notifications.sms.provider', (string) ($smsInput['sms_provider'] ?? 'khudebarta'));
        ResellerScopedConfig::put($id, 'notifications.sms.api_url', rtrim((string) ($smsInput['sms_api_url'] ?? ''), '/'));
        ResellerScopedConfig::put($id, 'notifications.sms.sender_id', (string) ($smsInput['sms_sender_id'] ?? 'ISP'));

        $apiKey = trim((string) ($smsInput['sms_api_key'] ?? ''));
        if ($apiKey !== '') {
            ResellerScopedConfig::put($id, 'notifications.sms.api_key', $apiKey);
        }

        $secret = trim((string) ($smsInput['sms_secret_key'] ?? ''));
        if ($secret !== '') {
            ResellerScopedConfig::put($id, 'notifications.sms.secret_key', $secret);
        }
    }

    /**
     * @param  array<string, mixed>  $paymentInput
     */
    public static function savePayment(Reseller $reseller, array $paymentInput): void
    {
        $id = (int) $reseller->id;

        ResellerScopedConfig::put($id, 'bkash.personal_enabled', self::truthy($paymentInput['bkash_enabled'] ?? false) ? '1' : '0');
        ResellerScopedConfig::put($id, 'bkash.personal_number', trim((string) ($paymentInput['bkash_personal_number'] ?? '')));
        ResellerScopedConfig::put($id, 'bkash.personal_name', trim((string) ($paymentInput['bkash_personal_name'] ?? '')));

        ResellerScopedConfig::put($id, 'nagad.personal_enabled', self::truthy($paymentInput['nagad_enabled'] ?? false) ? '1' : '0');
        ResellerScopedConfig::put($id, 'nagad.personal_number', trim((string) ($paymentInput['nagad_personal_number'] ?? '')));
        ResellerScopedConfig::put($id, 'nagad.enabled', self::truthy($paymentInput['nagad_enabled'] ?? false) ? '1' : '0');
        ResellerScopedConfig::put($id, 'nagad.gateway_type', 'personal');

        ResellerScopedConfig::put($id, 'mfs_personal.sms_ingest.enabled', self::truthy($paymentInput['mfs_ingest_enabled'] ?? false) ? '1' : '0');

        $deviceKey = trim((string) ($paymentInput['mfs_device_key'] ?? ''));
        if ($deviceKey !== '') {
            ResellerScopedConfig::put($id, 'mfs_personal.sms_ingest.api_key', $deviceKey);
        }
    }

    public static function findResellerIdByDeviceKey(string $provided): ?int
    {
        $provided = trim($provided);
        if ($provided === '') {
            return null;
        }

        foreach (Reseller::query()->withoutGlobalScopes()->where('own_integrations_enabled', true)->where('is_active', true)->cursor() as $reseller) {
            $expected = self::get((int) $reseller->id, 'mfs_personal.sms_ingest.api_key', '');
            if ($expected !== '' && hash_equals($expected, $provided)) {
                return (int) $reseller->id;
            }
        }

        return null;
    }

    /**
     * @return array{bkash: bool, nagad: bool, bkash_number: string, nagad_number: string}
     */
    public static function personalPaymentSummary(Reseller $reseller): array
    {
        $state = self::paymentFormState($reseller);

        return [
            'bkash' => $state['bkash_enabled'] && filled($state['bkash_personal_number']),
            'nagad' => $state['nagad_enabled'] && filled($state['nagad_personal_number']),
            'bkash_number' => $state['bkash_personal_number'],
            'nagad_number' => $state['nagad_personal_number'],
        ];
    }

    private static function get(int $resellerId, string $configKey, string $default = ''): string
    {
        $stored = ResellerScopedConfig::get($resellerId, $configKey);

        return $stored !== null && $stored !== '' ? $stored : $default;
    }

    private static function flag(int $resellerId, string $configKey, bool $default): bool
    {
        $stored = ResellerScopedConfig::get($resellerId, $configKey);
        if ($stored === null || $stored === '') {
            return $default;
        }

        return in_array(strtolower($stored), ['1', 'true', 'yes', 'on'], true);
    }

    private static function truthy(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
