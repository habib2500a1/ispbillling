<?php

namespace App\Services\Payments;

use App\Exceptions\BkashApiException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class BkashCheckoutService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $appKey,
        private readonly string $appSecret,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeoutSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: (string) config('bkash.base_url'),
            appKey: (string) config('bkash.app_key'),
            appSecret: (string) config('bkash.app_secret'),
            username: (string) config('bkash.username'),
            password: (string) config('bkash.password'),
            timeoutSeconds: (int) config('bkash.http_timeout', 30),
        );
    }

    public function assertConfigured(): void
    {
        foreach (['app_key' => $this->appKey, 'app_secret' => $this->appSecret, 'username' => $this->username, 'password' => $this->password] as $k => $v) {
            if ($v === '' || $v === null) {
                throw new BkashApiException("bKash is not configured: missing {$k}.");
            }
        }
    }

    public function grantToken(): string
    {
        $this->assertConfigured();

        $url = $this->baseUrl.'/tokenized/checkout/token/grant';

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'username' => $this->username,
                    'password' => $this->password,
                ])
                ->post($url, [
                    'app_key' => $this->appKey,
                    'app_secret' => $this->appSecret,
                ])
                ->throw();
        } catch (RequestException $e) {
            throw new BkashApiException('bKash grant token request failed: '.$e->getMessage(), response: $e->response?->json());
        }

        $data = $response->json();
        $token = $data['id_token'] ?? null;
        if (! is_string($token) || $token === '') {
            $this->throwFromBody($data, 'grant token');
        }

        return $token;
    }

    /**
     * Checkout URL flow (mode 0011, intent sale).
     *
     * @return array{bkashURL: string, paymentID: string, raw: array}
     */
    public function createCheckoutPayment(
        string $idToken,
        string $amount,
        string $merchantInvoiceNumber,
        string $payerReference,
        string $callbackUrl,
    ): array {
        $url = $this->baseUrl.'/tokenized/checkout/create';

        $body = [
            'mode' => '0011',
            'payerReference' => $payerReference,
            'callbackURL' => $callbackUrl,
            'amount' => $amount,
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $merchantInvoiceNumber,
        ];

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => $idToken,
                    'X-App-Key' => $this->appKey,
                ])
                ->post($url, $body)
                ->throw();
        } catch (RequestException $e) {
            $message = 'bKash create payment failed: '.$e->getMessage();
            if ($e->response?->status() === 403) {
                $message .= ' Callback URL must be registered in bKash merchant panel (current: '.$callbackUrl.').';
            }

            throw new BkashApiException($message, response: $e->response?->json());
        }

        $data = $response->json();
        if (($data['statusCode'] ?? '') !== '0000') {
            $this->throwFromBody($data, 'create payment');
        }

        $bkashUrl = $data['bkashURL'] ?? null;
        $paymentId = $data['paymentID'] ?? null;
        if (! is_string($bkashUrl) || $bkashUrl === '' || ! is_string($paymentId) || $paymentId === '') {
            throw new BkashApiException('bKash create payment returned an unexpected payload.', response: $data);
        }

        return [
            'bkashURL' => $bkashUrl,
            'paymentID' => $paymentId,
            'raw' => $data,
        ];
    }

    /**
     * @return array{raw: array, trxID?: string, amount?: string, transactionStatus?: string}
     */
    public function executePayment(string $idToken, string $paymentId): array
    {
        $url = $this->baseUrl.'/tokenized/checkout/execute';

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => $idToken,
                    'X-App-Key' => $this->appKey,
                ])
                ->post($url, ['paymentID' => $paymentId])
                ->throw();
        } catch (RequestException $e) {
            throw new BkashApiException('bKash execute payment failed: '.$e->getMessage(), response: $e->response?->json());
        }

        $data = $response->json();
        if (($data['statusCode'] ?? '') !== '0000') {
            $this->throwFromBody($data, 'execute payment');
        }

        return [
            'raw' => $data,
            'trxID' => $data['trxID'] ?? null,
            'amount' => $data['amount'] ?? null,
            'transactionStatus' => $data['transactionStatus'] ?? null,
        ];
    }

    private function throwFromBody(?array $data, string $step): void
    {
        $msg = $data['statusMessage'] ?? $data['errorMessage'] ?? 'Unknown error';
        $code = $data['errorCode'] ?? $data['statusCode'] ?? null;

        throw new BkashApiException("bKash {$step}: {$msg}", errorCode: is_string($code) ? $code : null, response: $data);
    }
}
