<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Cache;

final class PublicCheckoutSession
{
    private const PREFIX = 'public_checkout:';

    private const TTL_SECONDS = 3600;

    /**
     * @param  array{invoice_id?: int|null, customer_id: int, amount: string, return_to: string, payment_type: string, gateway: string}  $payload
     */
    public static function put(string $sessionKey, array $payload): void
    {
        Cache::put(self::PREFIX.$sessionKey, $payload, self::TTL_SECONDS);
    }

    /**
     * @return array{invoice_id?: int|null, customer_id: int, amount: string, return_to: string, payment_type: string, gateway: string}|null
     */
    public static function get(string $sessionKey): ?array
    {
        $data = Cache::get(self::PREFIX.$sessionKey);

        return is_array($data) ? $data : null;
    }

    public static function forget(string $sessionKey): void
    {
        Cache::forget(self::PREFIX.$sessionKey);
    }

    public static function makeTranId(int $customerId, ?int $invoiceId = null): string
    {
        $part = $invoiceId ? 'INV'.$invoiceId : 'WAL'.$customerId;

        return 'ISP-'.$part.'-'.now()->format('YmdHis').'-'.substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
