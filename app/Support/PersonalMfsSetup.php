<?php

namespace App\Support;

/**
 * Canonical URLs for personal bKash/Nagad checkout and MFS SMS device ingest.
 */
final class PersonalMfsSetup
{
    public static function siteBase(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function apiBase(): string
    {
        return self::siteBase().'/api/v1';
    }

    public static function deviceIngestUrl(): string
    {
        return self::apiBase().'/mfs/sms/ingest';
    }

    public static function staffIngestUrl(): string
    {
        return self::apiBase().'/staff/mfs/sms/ingest';
    }

    public static function customerPayUrl(string $gateway): string
    {
        return self::siteBase().'/mfs/'.strtolower($gateway).'/pay';
    }

    public static function ingestEnabled(): bool
    {
        return (bool) config('mfs_personal.sms_ingest.enabled', false);
    }

    public static function deviceKeyConfigured(): bool
    {
        return filled(config('mfs_personal.sms_ingest.api_key'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminPanelData(): array
    {
        return [
            'site_base' => self::siteBase(),
            'api_base' => self::apiBase(),
            'device_ingest_url' => self::deviceIngestUrl(),
            'staff_ingest_url' => self::staffIngestUrl(),
            'bkash_pay_url' => self::customerPayUrl('bkash'),
            'nagad_pay_url' => self::customerPayUrl('nagad'),
            'header_name' => 'X-MFS-Device-Key',
            'ingest_enabled' => self::ingestEnabled(),
            'device_key_set' => self::deviceKeyConfigured(),
            'curl_device' => self::curlDeviceExample(),
            'json_body' => self::sampleJsonBody(),
            'mfs_verify_apk_hint' => self::apiBase(),
        ];
    }

    public static function curlDeviceExample(): string
    {
        $url = self::deviceIngestUrl();

        return <<<CURL
curl -X POST '{$url}' \\
  -H 'Content-Type: application/json' \\
  -H 'X-MFS-Device-Key: YOUR_DEVICE_KEY' \\
  -d '{"gateway":"bkash","transaction_id":"ABC12345","amount":500,"raw_message":"Cash In..."}'
CURL;
    }

    public static function sampleJsonBody(): string
    {
        return (string) json_encode([
            'gateway' => 'bkash',
            'transaction_id' => 'ABC12345',
            'amount' => 500,
            'sender_phone' => '01700000000',
            'raw_message' => 'You have received Tk 500.00...',
            'device_name' => 'MFS Verify phone',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, string>
     */
    public static function mobileConfigLinks(): array
    {
        return [
            'api_base' => self::apiBase(),
            'mfs_device_ingest' => self::deviceIngestUrl(),
            'mfs_staff_ingest' => self::staffIngestUrl(),
            'personal_bkash_pay' => self::customerPayUrl('bkash'),
            'personal_nagad_pay' => self::customerPayUrl('nagad'),
        ];
    }
}
