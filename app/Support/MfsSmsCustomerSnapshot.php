<?php

namespace App\Support;

use App\Models\Customer;

/**
 * Subscriber fields stored on MfsSmsRecord.meta for admin ledger display.
 */
final class MfsSmsCustomerSnapshot
{
    /**
     * @return array{
     *     matched_customer_id: int,
     *     matched_customer_code: ?string,
     *     matched_customer_name: ?string,
     *     matched_customer_phone: ?string,
     *     matched_customer_pppoe: ?string,
     * }
     */
    public static function from(Customer $customer): array
    {
        return [
            'matched_customer_id' => (int) $customer->id,
            'matched_customer_code' => $customer->customer_code,
            'matched_customer_name' => $customer->name,
            'matched_customer_phone' => $customer->phone,
            'matched_customer_pppoe' => $customer->pppLoginName(),
        ];
    }
}
