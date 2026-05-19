<?php

namespace App\Support;

use App\Models\Customer;

final class CustomerCodeGenerator
{
    /**
     * Generate a new subscriber code for a tenant.
     *
     * @param  string|null  $secretName  PPP secret / login from MikroTik (used when format is secret_as_code or numeric hint)
     */
    public static function generate(int $tenantId, ?string $secretName = null): string
    {
        $format = (string) config('subscriber.code_format', 'prefixed_monthly');

        if ($format === 'secret_as_code' && filled($secretName)) {
            return self::sanitizeCode((string) $secretName);
        }

        if ($format === 'numeric') {
            return self::nextNumeric($tenantId, $secretName);
        }

        if ($format === 'prefix_sequential') {
            return self::nextPrefixSequential($tenantId);
        }

        return self::prefixedMonthly($tenantId);
    }

    public static function sanitizeCode(string $value): string
    {
        $value = trim($value);

        return substr(preg_replace('/\s+/', '', $value) ?? $value, 0, 64);
    }

    /**
     * Whether a manual code is allowed (digits-only policy when format is numeric).
     */
    public static function isValidManualCode(string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        $format = (string) config('subscriber.code_format', 'prefixed_monthly');

        if ($format === 'numeric') {
            return (bool) preg_match('/^\d+$/', $code);
        }

        return strlen($code) <= 64;
    }

    private static function prefixedMonthly(int $tenantId): string
    {
        $prefix = rtrim((string) config('subscriber.code_prefix', 'CUST'), '-').'-'.now()->format('ym').'-';
        $last = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_code', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('customer_code');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private static function nextPrefixSequential(int $tenantId): string
    {
        $prefix = rtrim((string) config('subscriber.code_prefix', 'CUST'), '-').'-';
        $last = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_code', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('customer_code');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private static function nextNumeric(int $tenantId, ?string $secretName): string
    {
        if (filled($secretName) && preg_match('/^\d+$/', trim($secretName))) {
            $candidate = trim($secretName);
            $exists = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_code', $candidate)
                ->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        $max = 0;
        foreach (Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('customer_code') as $code) {
            if (preg_match('/^\d+$/', (string) $code)) {
                $max = max($max, (int) $code);
            }
        }

        $start = (int) config('subscriber.numeric_start', 10001);
        $next = max($start, $max + 1);

        return (string) $next;
    }
}
