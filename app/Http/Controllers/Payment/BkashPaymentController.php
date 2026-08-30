<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CustomersInfo;
use App\Services\Billing\PublicPayCustomer;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BkashPaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public const SANDBOX_URL = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';

    public const LIVE_URL = 'https://tokenized.pay.bka.sh/v1.2.0-beta';

    private function getBkashConfig()
    {
        $trim = static fn ($value) => is_string($value) ? trim($value) : $value;
        $sandbox = (string) siteUrlSettings('payment_bkash_sandbox', '0') !== '0';
        $baseUrl = $sandbox ? self::SANDBOX_URL : self::LIVE_URL;

        return [
            'base_url' => $baseUrl,
            'sandbox' => $sandbox,
            'username' => $trim(siteUrlSettings('payment_bkash_username') ?: config('services.bkash.username')),
            'password' => $trim(siteUrlSettings('payment_bkash_password') ?: config('services.bkash.password')),
            'app_key' => $trim(siteUrlSettings('payment_bkash_app_key') ?: config('services.bkash.app_key')),
            'app_secret' => $trim(siteUrlSettings('payment_bkash_app_secret') ?: config('services.bkash.app_secret')),
        ];
    }

    private function decodeBkash(mixed $response): array
    {
        $json = method_exists($response, 'json') ? $response->json() : null;
        if (is_array($json)) {
            return $json;
        }

        $raw = method_exists($response, 'body') ? trim((string) $response->body()) : '';
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (is_string($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function generateToken()
    {
        $config = $this->getBkashConfig();

        if ($config['username'] === '' || $config['password'] === '' || $config['app_key'] === '' || $config['app_secret'] === '') {
            throw new \Exception('bKash username, password, app key, and app secret must be saved in Site Settings → Payment Gateways.');
        }

        // Official grant-token auth is username/password headers, not HTTP Basic.
        // https://developer.bka.sh/docs/grant-token-1
        $response = Http::timeout(25)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'username' => $config['username'],
                'password' => $config['password'],
            ])
            ->post($config['base_url'].'/tokenized/checkout/token/grant', [
                'app_key' => $config['app_key'],
                'app_secret' => $config['app_secret'],
            ]);

        $body = $this->decodeBkash($response);

        if (empty($body['id_token'])) {
            $detail = $body['statusMessage'] ?? $body['errorMessage'] ?? $body['message'] ?? null;
            if (! $detail) {
                $detail = $config['sandbox']
                    ? 'Sandbox rejected these keys. Switch to Live if this is a merchant account.'
                    : 'Live bKash rejected these keys. Check App Key, Secret, Username, and Password.';
            }
            Log::error('bKash token generation failed: '.json_encode([
                'http' => $response->status(),
                'url' => $config['base_url'],
                'sandbox' => $config['sandbox'],
                'statusCode' => $body['statusCode'] ?? null,
                'message' => $detail,
            ]));
            throw new \Exception($detail);
        }

        return $body['id_token'];
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $amount = $request->amount;
        $customer = PublicPayCustomer::current();

        if (! $customer) {
            return PublicPayCustomer::failRedirect('Unauthorized customer access.');
        }

        // Auto-detect local development to offer sandbox mock helper
        $host = request()->getHost();
        $isLocal = str_ends_with($host, '.test') || str_ends_with($host, '.local') || $host === 'localhost' || $host === '127.0.0.1';

        if ($isLocal && $request->has('mock')) {
            return $this->showMockPaymentPage('bKash', $customer, $amount);
        }

        try {
            $idToken = $this->generateToken();
            $config = $this->getBkashConfig();

            $callbackURL = url('/pay/callback/bkash');

            $payment = Http::timeout(25)
                ->acceptJson()
                ->asJson()
                ->withToken($idToken)
                ->withHeaders([
                    'X-APP-Key' => $config['app_key'],
                ])
                ->post($config['base_url'].'/tokenized/checkout/create', [
                    'mode' => '0011',
                    'payerReference' => $customer->customer_unique_id,
                    'callbackURL' => $callbackURL,
                    'amount' => (string) round((float) $amount, 2),
                    'currency' => 'BDT',
                    'merchantInvoiceNumber' => 'INV_'.uniqid(),
                    'intent' => 'sale',
                ]);

            $res = $this->decodeBkash($payment);

            if (! empty($res['bkashURL'])) {
                session(['bkash_amount' => $amount, 'bkash_customer_id' => $customer->id]);

                return redirect()->away($res['bkashURL']);
            }

            $createError = $res['statusMessage'] ?? $res['errorMessage'] ?? $res['message'] ?? 'Unknown error';
            Log::error('bKash Create Payment Failed: '.json_encode([
                'http' => $payment->status(),
                'statusCode' => $res['statusCode'] ?? null,
                'message' => $createError,
            ]));

            // If API fails on local development, redirect to mock page
            if ($isLocal) {
                return $this->showMockPaymentPage('bKash', $customer, $amount, 'bKash API returned error: '.$createError);
            }

            return PublicPayCustomer::failRedirect('bKash payment initiation failed: '.$createError);

        } catch (\Exception $e) {
            Log::error('bKash initiate exception: '.$e->getMessage());

            if ($isLocal) {
                return $this->showMockPaymentPage('bKash', $customer, $amount, 'Connection failed: '.$e->getMessage());
            }

            return PublicPayCustomer::failRedirect('bKash: '.$e->getMessage());
        }
    }

    public function callback(Request $request)
    {
        $status = $request->query('status');
        $paymentID = $request->query('paymentID');

        if ($status === 'success') {
            try {
                $idToken = $this->generateToken();
                $config = $this->getBkashConfig();

                $execution = Http::timeout(25)
                    ->acceptJson()
                    ->asJson()
                    ->withToken($idToken)
                    ->withHeaders([
                        'X-APP-Key' => $config['app_key'],
                    ])
                    ->post($config['base_url'].'/tokenized/checkout/execute', [
                        'paymentID' => $paymentID,
                    ]);

                $res = $this->decodeBkash($execution);

                if (isset($res['statusCode']) && $res['statusCode'] === '0000') {
                    $trxID = $res['trxID'] ?? 'BKASH_'.uniqid();
                    $amount = (float) $res['amount'];
                    $customerUniqueId = $res['payerReference'];

                    $customer = CustomersInfo::where('customer_unique_id', $customerUniqueId)->first();
                    if ($customer) {
                        $this->paymentService->processSuccessPayment($customer, $amount, 'bkash', $trxID);

                        return PublicPayCustomer::afterPayment(
                            $customer,
                            'Payment of BDT '.$amount.' received successfully via bKash. Your account is active.'
                        );
                    }
                }

                Log::error('bKash Execution Failed: '.json_encode($res));

                return PublicPayCustomer::failRedirect('Payment verification failed: '.($res['statusMessage'] ?? 'Unknown error'));

            } catch (\Exception $e) {
                Log::error('bKash callback execution exception: '.$e->getMessage());

                return PublicPayCustomer::failRedirect('Verification exception: '.$e->getMessage());
            }
        }

        return PublicPayCustomer::failRedirect('Payment process cancelled or failed. Status: '.$status);
    }

    public function mockSubmit(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers_infos,id',
            'amount' => 'required|numeric|min:1',
            'gateway' => 'required',
        ]);

        $customer = CustomersInfo::find($request->customer_id);
        $amount = (float) $request->amount;
        $gateway = strtolower($request->gateway);
        $trxID = strtoupper($gateway).'_MOCK_'.strtoupper(uniqid());

        try {
            $this->paymentService->processSuccessPayment($customer, $amount, $gateway, $trxID);

            return PublicPayCustomer::afterPayment(
                $customer,
                'Payment of BDT '.$amount.' simulated successfully via '.strtoupper($gateway).'. Your account is active.'
            );
        } catch (\Exception $e) {
            return PublicPayCustomer::failRedirect('Failed to process simulated payment: '.$e->getMessage());
        }
    }

    private function showMockPaymentPage($gateway, $customer, $amount, $reason = null)
    {
        return response()->view('payment.mock_checkout', [
            'gateway' => $gateway,
            'customer' => $customer,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }
}
