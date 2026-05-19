<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NagadCheckoutService
{
    public function __construct(
        private readonly bool $sandbox,
        private readonly string $merchantId,
        private readonly ?string $accountNumber,
        private readonly NagadCrypto $crypto,
        private readonly int $timeoutSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            sandbox: (bool) config('nagad.sandbox', true),
            merchantId: (string) config('nagad.merchant_id'),
            accountNumber: config('nagad.merchant_number') ?: null,
            crypto: NagadCrypto::fromConfig(),
            timeoutSeconds: (int) config('nagad.http_timeout', 30),
        );
    }

    public function assertConfigured(): void
    {
        if ($this->merchantId === '' || ! config('nagad.merchant_private_key') || ! config('nagad.pg_public_key')) {
            throw new PaymentGatewayException('Nagad is not configured: set merchant ID, private key, and PG public key.');
        }
    }

    /**
     * @return array{redirect_url: string, order_id: string, payment_ref_id: string|null}
     */
    public function createCheckout(
        string $orderId,
        string $amount,
        string $callbackUrl,
    ): array {
        $this->assertConfigured();

        $challenge = Str::lower(Str::random(40));
        $dateTime = now()->format('YmdHis');

        $initPayload = [
            'merchantId' => $this->merchantId,
            'datetime' => $dateTime,
            'orderId' => $orderId,
            'challenge' => $challenge,
        ];
        $initJson = json_encode($initPayload, JSON_THROW_ON_ERROR);

        $initPost = [
            'dateTime' => $dateTime,
            'sensitiveData' => $this->crypto->encryptSensitive($initJson),
            'signature' => $this->crypto->sign($initJson),
        ];

        if (filled($this->accountNumber)) {
            $initPost['accountNumber'] = $this->accountNumber;
        }

        $initUrl = $this->baseUrl().'check-out/initialize/'.$this->merchantId.'/'.$orderId;
        $initResponse = $this->post($initUrl, $initPost);

        if (isset($initResponse['error'])) {
            throw new PaymentGatewayException('Nagad initialize failed: '.$initResponse['error']);
        }

        if (empty($initResponse['sensitiveData']) || empty($initResponse['signature'])) {
            throw new PaymentGatewayException(
                'Nagad initialize rejected: '.($initResponse['message'] ?? json_encode($initResponse)),
                is_array($initResponse) ? $initResponse : null,
            );
        }

        $plainInit = json_decode($this->crypto->decryptSensitive($initResponse['sensitiveData']), true);
        $paymentRefId = $plainInit['paymentReferenceId'] ?? null;
        $orderChallenge = $plainInit['challenge'] ?? null;

        if (! is_string($paymentRefId) || ! is_string($orderChallenge)) {
            throw new PaymentGatewayException('Nagad initialize response missing payment reference.');
        }

        $completePayload = [
            'merchantId' => $this->merchantId,
            'orderId' => $orderId,
            'currencyCode' => '050',
            'amount' => $amount,
            'challenge' => $orderChallenge,
        ];
        $completeJson = json_encode($completePayload, JSON_THROW_ON_ERROR);

        $completePost = [
            'sensitiveData' => $this->crypto->encryptSensitive($completeJson),
            'signature' => $this->crypto->sign($completeJson),
            'merchantCallbackURL' => $callbackUrl,
        ];

        $completeUrl = $this->baseUrl().'check-out/complete/'.$paymentRefId;
        $completeResponse = $this->post($completeUrl, $completePost);

        if (($completeResponse['status'] ?? '') !== 'Success') {
            throw new PaymentGatewayException(
                'Nagad complete failed: '.($completeResponse['message'] ?? json_encode($completeResponse)),
                is_array($completeResponse) ? $completeResponse : null,
            );
        }

        $redirect = $completeResponse['callBackUrl'] ?? null;
        if (! is_string($redirect) || $redirect === '') {
            throw new PaymentGatewayException('Nagad did not return a payment URL.');
        }

        return [
            'redirect_url' => $redirect,
            'order_id' => $orderId,
            'payment_ref_id' => $paymentRefId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayment(string $paymentRefId): array
    {
        $this->assertConfigured();

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($this->baseUrl().'verify/payment/'.$paymentRefId)
                ->throw();
        } catch (RequestException $e) {
            throw new PaymentGatewayException('Nagad verify failed: '.$e->getMessage(), $e->response?->json());
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new PaymentGatewayException('Invalid Nagad verify response.');
        }

        return $data;
    }

    private function baseUrl(): string
    {
        return $this->sandbox
            ? 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/'
            : 'https://api.mynagad.com/remote-payment-gateway-1.0/api/dfs/';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $url, array $payload): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-KM-Api-Version' => 'v-0.2.0',
                    'X-KM-IP-V4' => request()->ip() ?? '127.0.0.1',
                    'X-KM-Client-Type' => 'PC_WEB',
                ])
                ->post($url, $payload)
                ->throw();
        } catch (RequestException $e) {
            throw new PaymentGatewayException('Nagad HTTP error: '.$e->getMessage(), $e->response?->json());
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
