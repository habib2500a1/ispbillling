<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SslCommerzCheckoutService
{
    public function __construct(
        private readonly bool $sandbox,
        private readonly string $storeId,
        private readonly string $storePassword,
        private readonly int $timeoutSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            sandbox: (bool) config('sslcommerz.sandbox', true),
            storeId: (string) config('sslcommerz.store_id'),
            storePassword: (string) config('sslcommerz.store_password'),
            timeoutSeconds: (int) config('sslcommerz.http_timeout', 30),
        );
    }

    public function assertConfigured(): void
    {
        if ($this->storeId === '' || $this->storePassword === '') {
            throw new PaymentGatewayException('SSLCommerz is not configured: missing store credentials.');
        }
    }

    /**
     * @param  array<string, string>  $customer
     * @return array{redirect_url: string, tran_id: string, sessionkey: string|null, raw: array}
     */
    public function createSession(
        string $tranId,
        string $amount,
        string $productName,
        array $customer,
        string $successUrl,
        string $failUrl,
        string $cancelUrl,
    ): array {
        $this->assertConfigured();

        $host = $this->sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';

        $payload = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => $amount,
            'currency' => 'BDT',
            'tran_id' => $tranId,
            'product_category' => 'ISP',
            'product_name' => Str::limit($productName, 120),
            'success_url' => $successUrl,
            'fail_url' => $failUrl,
            'cancel_url' => $cancelUrl,
            'cus_name' => $customer['name'] ?? 'Customer',
            'cus_phone' => $customer['phone'] ?? '01700000000',
            'cus_email' => $customer['email'] ?? 'pay@customer.local',
            'cus_add1' => $customer['address'] ?? 'Bangladesh',
            'cus_city' => 'Dhaka',
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'num_of_item' => 1,
            'emi_option' => 0,
        ];

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->asForm()
                ->post($host.'/gwprocess/v4/api.php', $payload)
                ->throw();
        } catch (RequestException $e) {
            throw new PaymentGatewayException('SSLCommerz session failed: '.$e->getMessage(), $e->response?->json());
        }

        $data = $response->json();
        if (! is_array($data) || ($data['status'] ?? '') !== 'SUCCESS') {
            throw new PaymentGatewayException(
                'SSLCommerz did not create a session: '.(is_array($data) ? ($data['failedreason'] ?? json_encode($data)) : 'unknown'),
                is_array($data) ? $data : null,
            );
        }

        $url = $data['GatewayPageURL'] ?? $data['redirectGatewayURL'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new PaymentGatewayException('SSLCommerz returned no redirect URL.', $data);
        }

        return [
            'redirect_url' => $url,
            'tran_id' => $tranId,
            'sessionkey' => is_string($data['sessionkey'] ?? null) ? $data['sessionkey'] : null,
            'raw' => $data,
        ];
    }

    /**
     * @return array{status: string, tran_id: string|null, amount: string|null, val_id: string|null, raw: array}
     */
    public function validatePayment(string $valId): array
    {
        $this->assertConfigured();

        $host = $this->sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->get($host.'/validator/api/validationserverAPI.php', [
                    'val_id' => $valId,
                    'store_id' => $this->storeId,
                    'store_passwd' => $this->storePassword,
                    'format' => 'json',
                ])
                ->throw();
        } catch (RequestException $e) {
            throw new PaymentGatewayException('SSLCommerz validation failed: '.$e->getMessage(), $e->response?->json());
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new PaymentGatewayException('Invalid SSLCommerz validation response.');
        }

        return [
            'status' => (string) ($data['status'] ?? ''),
            'tran_id' => isset($data['tran_id']) ? (string) $data['tran_id'] : null,
            'amount' => isset($data['amount']) ? (string) $data['amount'] : null,
            'val_id' => $valId,
            'raw' => $data,
        ];
    }
}
