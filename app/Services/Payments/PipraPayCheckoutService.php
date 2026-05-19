<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;
use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class PipraPayCheckoutService
{
    public const MODE_REDIRECT = 'redirect';

    public const MODE_LEGACY = 'legacy';

    public static function fromConfig(): self
    {
        return new self(
            apiKey: (string) config('piprapay.api_key', ''),
            baseUrl: (string) config('piprapay.base_url', 'https://sandbox.piprapay.com/api'),
            currency: (string) config('piprapay.currency', 'BDT'),
            timeout: (int) config('piprapay.http_timeout', 30),
            apiMode: (string) config('piprapay.api_mode', self::MODE_REDIRECT),
        );
    }

    public static function isEnabled(): bool
    {
        return (bool) config('piprapay.enabled', false)
            && filled(config('piprapay.api_key'));
    }

    /**
     * Callback URLs must use a whitelisted hostname (not a bare IP).
     *
     * @param  array<string, scalar|null>  $query
     */
    public static function publicUrl(string $path, array $query = []): string
    {
        $base = rtrim((string) config('piprapay.public_url', config('app.url')), '/');
        $url = $base.'/'.ltrim($path, '/');
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return $url;
    }

    public static function publicHost(): string
    {
        return (string) parse_url((string) config('piprapay.public_url', config('app.url')), PHP_URL_HOST);
    }

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $currency = 'BDT',
        private readonly int $timeout = 30,
        private readonly string $apiMode = self::MODE_REDIRECT,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{redirect_url: string, pp_id: ?string, raw: array<string, mixed>}
     */
    public function createCharge(
        Customer $customer,
        float $amount,
        string $orderId,
        string $redirectUrl,
        string $cancelUrl,
        string $webhookUrl,
        array $metadata = [],
    ): array {
        $mergedMeta = array_merge(['order_id' => $orderId], $metadata);

        if ($this->apiMode === self::MODE_LEGACY) {
            $payload = [
                'full_name' => $customer->name,
                'email_mobile' => $customer->email ?: $customer->phone ?: 'customer@isp.local',
                'amount' => (string) round($amount, 2),
                'currency' => $this->currency,
                'metadata' => $mergedMeta,
                'redirect_url' => $redirectUrl,
                'cancel_url' => $cancelUrl,
                'webhook_url' => $webhookUrl,
                'return_type' => 'GET',
            ];
            $endpoint = '/create-charge';
        } else {
            $payload = [
                'full_name' => $customer->name,
                'email_address' => $customer->email ?: 'pay@customer.local',
                'mobile_number' => $customer->phone ?: '01700000000',
                'amount' => (string) round($amount, 2),
                'currency' => $this->currency,
                'metadata' => json_encode($mergedMeta, JSON_THROW_ON_ERROR),
                'return_url' => $redirectUrl,
                'webhook_url' => $webhookUrl,
            ];
            $endpoint = '/checkout/redirect';
        }

        $response = $this->post($endpoint, $payload);
        $redirect = $this->resolveRedirectUrl($response);
        if ($redirect === null) {
            throw new PaymentGatewayException('PipraPay did not return a payment URL.', $response);
        }

        $ppId = $this->resolvePpId($response);
        if ($ppId !== null) {
            Cache::put(self::ppCacheKey($ppId), $orderId, 3600);
        }

        return [
            'redirect_url' => $redirect,
            'pp_id' => $ppId,
            'raw' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayment(string $ppId): array
    {
        $endpoint = $this->apiMode === self::MODE_LEGACY
            ? '/verify-payments'
            : '/verify-payment';

        $id = trim($ppId);
        if ($id === '') {
            throw new PaymentGatewayException('PipraPay payment reference is required.');
        }

        try {
            return $this->post($endpoint, ['pp_id' => $id]);
        } catch (PaymentGatewayException $e) {
            $message = strtolower($e->getMessage());
            if (! str_contains($message, 'bp id') && ! str_contains($message, 'pp_id')) {
                throw $e;
            }

            return $this->post($endpoint, ['bp_id' => $id]);
        }
    }

    /**
     * @return array{status: bool, data: array<string, mixed>}
     */
    public function handleWebhook(?string $receivedApiKey): array
    {
        if ($receivedApiKey === null || $receivedApiKey === '' || ! hash_equals($this->apiKey, $receivedApiKey)) {
            return ['status' => false, 'data' => ['message' => 'Unauthorized']];
        }

        return ['status' => true, 'data' => request()->all()];
    }

    public static function ppCacheKey(string $ppId): string
    {
        return 'piprapay_pp:'.$ppId;
    }

    public static function orderIdForPpId(string $ppId): ?string
    {
        $orderId = Cache::get(self::ppCacheKey($ppId));

        return is_string($orderId) && $orderId !== '' ? $orderId : null;
    }

    public function isPaymentSuccessful(array $verify): bool
    {
        if (isset($verify['error']) && is_array($verify['error'])) {
            return false;
        }

        if ($this->truthy($verify['status'] ?? null) && isset($verify['data']) && is_array($verify['data'])) {
            return $this->isPaymentSuccessful($verify['data']);
        }

        $status = strtolower((string) (
            $verify['payment_status']
            ?? $verify['status']
            ?? ($verify['data']['payment_status'] ?? null)
            ?? ($verify['data']['status'] ?? null)
            ?? ''
        ));

        return in_array($status, [
            'completed',
            'success',
            'paid',
            'approved',
            'successful',
            'manual_approved',
            'manually_approved',
            '1',
        ], true);
    }

    public function isPaymentPending(array $verify): bool
    {
        if ($this->isPaymentSuccessful($verify)) {
            return false;
        }

        $status = strtolower((string) (
            $verify['payment_status']
            ?? $verify['status']
            ?? ($verify['data']['payment_status'] ?? null)
            ?? ($verify['data']['status'] ?? null)
            ?? ''
        ));

        return in_array($status, [
            'pending',
            'processing',
            'awaiting_approval',
            'awaiting approval',
            'manual_review',
            'manual review',
            'under_review',
            'under review',
            'submitted',
            'initiated',
        ], true);
    }

    public function verifiedAmount(array $verify, string $fallback): string
    {
        $amount = $verify['amount']
            ?? ($verify['data']['amount'] ?? null)
            ?? ($verify['payment_amount'] ?? null);

        if ($amount === null) {
            return $fallback;
        }

        return number_format((float) $amount, 2, '.', '');
    }

    public function orderIdFromVerify(array $verify, ?string $ppId = null): ?string
    {
        $metadata = $verify['metadata'] ?? ($verify['data']['metadata'] ?? null);
        if (is_string($metadata) && $metadata !== '') {
            try {
                $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    if (filled($decoded['order_id'] ?? null)) {
                        return (string) $decoded['order_id'];
                    }
                }
            } catch (\JsonException) {
                // ignore
            }
        }

        if (is_array($metadata) && filled($metadata['order_id'] ?? null)) {
            return (string) $metadata['order_id'];
        }

        if ($ppId !== null) {
            return self::orderIdForPpId($ppId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $data): array
    {
        if ($this->apiKey === '') {
            throw new PaymentGatewayException('PipraPay API key is not configured.');
        }

        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'MHS-PIPRAPAY-API-KEY' => $this->apiKey,
                'mh-piprapay-api-key' => $this->apiKey,
            ])
            ->post($url, $data);

        if (! $response->successful()) {
            Log::warning('piprapay.http_error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $json = $response->json();
            $message = is_array($json)
                ? (string) ($json['error']['message'] ?? $json['message'] ?? 'PipraPay API error.')
                : 'PipraPay API error (HTTP '.$response->status().').';

            throw new PaymentGatewayException($message, is_array($json) ? $json : ['body' => $response->body()]);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new PaymentGatewayException('Invalid PipraPay response.');
        }

        if ($this->responseIndicatesFailure($json)) {
            throw new PaymentGatewayException(
                (string) ($json['error']['message'] ?? $json['message'] ?? $json['error'] ?? 'PipraPay request failed.'),
                $json,
            );
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function responseIndicatesFailure(array $response): bool
    {
        if (isset($response['error']) && is_array($response['error'])) {
            return true;
        }

        if (array_key_exists('status', $response) && $response['status'] === false) {
            return true;
        }

        if (isset($response['success']) && $response['success'] === false) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function resolveRedirectUrl(array $response): ?string
    {
        $candidates = [
            $response['pp_url'] ?? null,
            $response['payment_url'] ?? null,
            $response['redirect_url'] ?? null,
            $response['checkout_url'] ?? null,
            is_array($response['data'] ?? null) ? ($response['data']['pp_url'] ?? $response['data']['payment_url'] ?? null) : null,
        ];

        foreach ($candidates as $url) {
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function resolvePpId(array $response): ?string
    {
        $candidates = [
            $response['pp_id'] ?? null,
            $response['bp_id'] ?? null,
            $response['payment_id'] ?? null,
            is_array($response['data'] ?? null) ? ($response['data']['pp_id'] ?? $response['data']['bp_id'] ?? null) : null,
        ];

        foreach ($candidates as $id) {
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'success', 'completed', 'paid'], true);
        }

        return (bool) $value;
    }
}
