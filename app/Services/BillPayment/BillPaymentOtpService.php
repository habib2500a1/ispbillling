<?php

namespace App\Services\BillPayment;

use App\Models\Customer;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BillPaymentOtpService
{
    public function isEnabled(): bool
    {
        return (bool) config('bill_payment.otp.enabled', false);
    }

    public function startChallenge(Customer $customer): void
    {
        $digits = max(4, min(8, (int) config('bill_payment.otp.digits', 6)));
        $max = (10 ** $digits) - 1;
        $min = 10 ** ($digits - 1);
        $code = (string) random_int($min, $max);

        $ttl = (int) config('bill_payment.otp.ttl_seconds', 600);
        Cache::put($this->cacheKey($customer->id), hash('sha256', $code), now()->addSeconds($ttl));

        $this->deliver($customer, $code);
    }

    public function verify(int $customerId, string $code): bool
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if ($code === '') {
            return false;
        }

        $key = $this->cacheKey($customerId);
        $hash = Cache::get($key);
        if (! is_string($hash) || $hash === '') {
            return false;
        }

        if (! hash_equals($hash, hash('sha256', $code))) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    public function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) < 6) {
            return 'your registered mobile';
        }

        return substr($digits, 0, 3).'****'.substr($digits, -3);
    }

    protected function cacheKey(int $customerId): string
    {
        return 'bill_payment_otp:'.$customerId;
    }

    protected function deliver(Customer $customer, string $plainCode): void
    {
        $logOnly = (bool) config('bill_payment.otp.log_delivery_only', false);

        if ($logOnly || app()->environment('local')) {
            Log::channel('single')->notice('bill_payment.otp_issued', [
                'customer_id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'code' => $plainCode,
            ]);
        }

        if ($logOnly) {
            return;
        }

        $minutes = max(1, (int) ceil(((int) config('bill_payment.otp.ttl_seconds', 600)) / 60));

        try {
            app(NotificationDispatcher::class)->notifyCustomer($customer, NotificationEvent::PORTAL_OTP, [
                'code' => $plainCode,
                'minutes' => $minutes,
            ], [
                'subject' => 'Bill payment verification code',
            ]);
        } catch (Throwable $e) {
            Cache::forget($this->cacheKey($customer->id));
            throw $e;
        }
    }
}
