<?php

namespace App\Services\Portal;

use App\Models\Customer;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PortalOtpService
{
    public function isEnabled(): bool
    {
        return (bool) config('portal.otp.enabled', false);
    }

    public function startChallenge(Customer $customer): string
    {
        $digits = (int) config('portal.otp.digits', 6);
        $digits = max(4, min(8, $digits));
        $max = (10 ** $digits) - 1;
        $min = 10 ** ($digits - 1);
        $code = (string) random_int($min, $max);

        $ttl = (int) config('portal.otp.ttl_seconds', 600);
        Cache::put($this->cacheKey($customer->id), hash('sha256', $code), now()->addSeconds($ttl));

        $this->deliver($customer, $code);

        return $code;
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

    public function forget(int $customerId): void
    {
        Cache::forget($this->cacheKey($customerId));
    }

    protected function cacheKey(int $customerId): string
    {
        return 'portal_login_otp:'.$customerId;
    }

    protected function deliver(Customer $customer, string $plainCode): void
    {
        $logOnly = (bool) config('portal.otp.log_delivery_only', false);

        if ($logOnly || app()->environment('local')) {
            Log::channel('single')->notice('portal.otp_issued', [
                'customer_id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'phone' => $customer->phone,
                'log_delivery_only' => $logOnly,
                'code' => $plainCode,
            ]);
        }

        if ($logOnly) {
            return;
        }

        $ttl = (int) config('portal.otp.ttl_seconds', 600);
        $minutes = max(1, (int) ceil($ttl / 60));

        try {
            app(NotificationDispatcher::class)->notifyCustomer($customer, NotificationEvent::PORTAL_OTP, [
                'code' => $plainCode,
                'minutes' => $minutes,
            ], [
                'subject' => __('Your portal login code'),
            ]);
        } catch (Throwable $e) {
            Log::channel('single')->error('portal.otp_delivery_failed', [
                'customer_id' => $customer->id,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            Cache::forget($this->cacheKey($customer->id));

            throw $e;
        }
    }
}
