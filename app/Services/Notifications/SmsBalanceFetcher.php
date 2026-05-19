<?php

namespace App\Services\Notifications;

use App\Models\AppSetting;
use App\Services\Notifications\Gateways\KhudeBartaSmsGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SmsBalanceFetcher
{
    private const CACHE_KEY = 'sms_gateway_balance_snapshot';

    /**
     * @return array{balance: ?float, label: string, error: ?string, fetched_at: ?string}
     */
    public function fetch(bool $refresh = false): array
    {
        if (! $refresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $provider = (string) config('notifications.sms.provider', 'bulksmsbd');
        $result = match ($provider) {
            'khudebarta' => $this->fetchKhudeBarta(),
            'bulksmsbd' => $this->fetchBulksmsbd(),
            default => [
                'balance' => $this->storedBalance(),
                'label' => 'N/A',
                'error' => 'Live balance is not available for this provider.',
                'fetched_at' => null,
            ],
        };

        if ($result['balance'] !== null) {
            AppSetting::putValue('notifications.sms.cached_balance', (string) $result['balance']);
            AppSetting::putValue('notifications.sms.cached_balance_at', now()->toIso8601String());
        } elseif (($stored = $this->storedBalance()) !== null && $result['error'] !== null) {
            $result['balance'] = $stored;
            $result['label'] = 'Cached';
            $result['error'] = $result['error'].' · showing last known balance.';
            $result['fetched_at'] = AppSetting::getStoredValue('notifications.sms.cached_balance_at');
        }

        Cache::put(self::CACHE_KEY, $result, now()->addMinutes((int) config('notifications.sms.balance_cache_minutes', 5)));

        return $result;
    }

    /**
     * @return array{balance: ?float, label: string, error: ?string, fetched_at: ?string}
     */
    private function fetchKhudeBarta(): array
    {
        $apiKey = (string) config('notifications.sms.api_key', '');
        $secretKey = (string) config('notifications.sms.secret_key', '');
        $callerId = (string) config('notifications.sms.sender_id', '');

        if ($apiKey === '' || $secretKey === '') {
            return [
                'balance' => null,
                'label' => '—',
                'error' => 'Configure API key and secret key first.',
                'fetched_at' => null,
            ];
        }

        $customUrl = trim((string) config('notifications.sms.khudebarta_balance_url', ''));
        $urls = array_filter([
            $customUrl !== '' ? $customUrl : null,
            'http://portal.khudebarta.com:3775/GetBalance',
            'http://portal.khudebarta.com:3775/getbalance',
            'http://portal.khudebarta.com:3775/checkbalance',
        ]);

        $gateway = app(KhudeBartaSmsGateway::class);
        $hashVariants = [
            $gateway->buildHash($apiKey, $secretKey, $callerId, '', ''),
            md5($apiKey.$secretKey.$callerId),
            md5($apiKey.$secretKey),
            strtoupper(md5($apiKey.$secretKey.$callerId)),
        ];

        foreach ($urls as $url) {
            foreach ($hashVariants as $hash) {
                $query = [
                    'apikey' => $apiKey,
                    'secretkey' => $secretKey,
                    'callerID' => $callerId,
                    'hash' => $hash,
                ];

                try {
                    $response = Http::timeout((int) config('notifications.sms.timeout', 15))
                        ->get($url, $query);

                    $parsed = $this->parseBalanceFromBody($response->json(), $response->body());
                    if ($parsed !== null) {
                        return [
                            'balance' => $parsed,
                            'label' => number_format($parsed, 1),
                            'error' => null,
                            'fetched_at' => now()->format('Y-m-d H:i'),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::debug('sms.balance.fetch_failed', ['url' => $url, 'error' => $e->getMessage()]);
                }
            }
        }

        return [
            'balance' => null,
            'label' => '—',
            'error' => 'Could not load balance from KhudeBarta API. Check credit on the KhudeBarta portal or set KHUDEBARTA_BALANCE_URL in .env if your vendor gave a balance endpoint.',
            'fetched_at' => null,
        ];
    }

    /**
     * @return array{balance: ?float, label: string, error: ?string, fetched_at: ?string}
     */
    private function fetchBulksmsbd(): array
    {
        $apiKey = (string) config('notifications.sms.api_key', '');
        if ($apiKey === '') {
            return [
                'balance' => null,
                'label' => '—',
                'error' => 'Configure API key first.',
                'fetched_at' => null,
            ];
        }

        try {
            $response = Http::timeout(15)
                ->get('https://bulksmsbd.net/api/getBalanceApi', ['api_key' => $apiKey]);

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status());
            }

            $json = $response->json();
            $balance = $this->parseBalanceFromBody($json, $response->body());
            if ($balance === null) {
                throw new \RuntimeException(is_array($json) ? (string) ($json['error_message'] ?? $response->body()) : $response->body());
            }

            return [
                'balance' => $balance,
                'label' => number_format($balance, 1),
                'error' => null,
                'fetched_at' => now()->format('Y-m-d H:i'),
            ];
        } catch (\Throwable $e) {
            return [
                'balance' => null,
                'label' => '—',
                'error' => 'BulkSMSBD balance: '.$e->getMessage(),
                'fetched_at' => null,
            ];
        }
    }

    private function storedBalance(): ?float
    {
        $raw = AppSetting::getStoredValue('notifications.sms.cached_balance');
        if ($raw === null || $raw === '') {
            return null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * @param  mixed  $json
     */
    private function parseBalanceFromBody(mixed $json, string $rawBody): ?float
    {
        if (is_array($json)) {
            foreach (['balance', 'Balance', 'credit', 'Credit', 'sms_balance', 'remaining_balance', 'amount', 'Amount'] as $key) {
                if (isset($json[$key]) && is_numeric($json[$key])) {
                    return (float) $json[$key];
                }
            }
            if (isset($json['data']) && is_array($json['data'])) {
                return $this->parseBalanceFromBody($json['data'], $rawBody);
            }
        }

        if (preg_match('/balance["\s:]*([0-9]+(?:\.[0-9]+)?)/i', $rawBody, $m)) {
            return (float) $m[1];
        }

        return null;
    }
}
