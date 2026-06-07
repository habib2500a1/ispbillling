<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Device;

/**
 * Company-provided vs customer-owned ONU (stored in customer meta).
 */
final class OnuOwnership
{
    public const COMPANY = 'company';

    public const CUSTOMER = 'customer';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::COMPANY => 'Company ONU',
            self::CUSTOMER => 'Customer ONU',
        ];
    }

    public static function forCustomer(Customer $customer): string
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $stored = strtolower(trim((string) ($meta['onu_ownership'] ?? '')));

        if (in_array($stored, [self::COMPANY, self::CUSTOMER], true)) {
            return $stored;
        }

        return self::infer($customer, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function infer(Customer $customer, array $meta): string
    {
        foreach (['onu_rent', 'onu_deposit', 'onu_installment'] as $key) {
            if (isset($meta[$key]) && (float) $meta[$key] > 0) {
                return self::COMPANY;
            }
        }

        $onu = $customer->relationLoaded('onuDevice')
            ? $customer->onuDevice
            : $customer->onuDevice()->first();

        if ($onu instanceof Device && ($onu->lease_status ?? 'none') === 'active') {
            return self::COMPANY;
        }

        if ($onu instanceof Device || filled($meta['onu_mac'] ?? null)) {
            return self::CUSTOMER;
        }

        return self::COMPANY;
    }

    public static function label(string $ownership): string
    {
        return self::options()[$ownership] ?? 'ONU';
    }

    public static function badgeTone(string $ownership): string
    {
        return $ownership === self::CUSTOMER ? 'customer' : 'company';
    }

}

