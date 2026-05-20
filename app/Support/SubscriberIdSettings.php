<?php

namespace App\Support;

final class SubscriberIdSettings
{
    public static function autoGenerateEnabled(): bool
    {
        return (bool) config('subscriber.auto_generate_customer_code', true);
    }

    public static function codeFormat(): string
    {
        return (string) config('subscriber.code_format', 'prefixed_monthly');
    }

    public static function codePrefix(): string
    {
        return (string) config('subscriber.code_prefix', 'CUST');
    }

    public static function numericStart(): int
    {
        return max(1, (int) config('subscriber.numeric_start', 10001));
    }

    /**
     * @return array<string, string>
     */
    public static function codeFormatOptions(): array
    {
        return [
            'prefixed_monthly' => 'CUST-YYMM-0001 (monthly sequence)',
            'prefix_sequential' => 'CUST-0001 (continuous sequence)',
            'numeric' => '10001, 10002 (digits only)',
            'secret_as_code' => 'Same as PPP username',
        ];
    }

    public static function previewNext(int $tenantId, ?string $secretName = null): string
    {
        return CustomerCodeGenerator::generate($tenantId, $secretName);
    }
}
