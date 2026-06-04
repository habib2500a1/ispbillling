<?php

namespace App\Support;

final class PersonalMfsGateway
{
    public const MODE_PERSONAL = 'personal';

    public const MODE_API = 'api';

    public static function isPersonalEnabled(string $gateway): bool
    {
        return match ($gateway) {
            PaymentGateway::BKASH => self::bkashPersonalEnabled(),
            PaymentGateway::NAGAD => self::nagadPersonalEnabled(),
            PaymentGateway::ROCKET => (bool) config('rocket.enabled', false)
                && filled(config('rocket.merchant_number')),
            default => false,
        };
    }

    public static function bkashPersonalEnabled(): bool
    {
        return BkashSettings::isPersonalEnabled()
            && filled(config('bkash.personal_number'));
    }

    public static function nagadPersonalEnabled(): bool
    {
        if (! (bool) config('nagad.enabled', false)) {
            return false;
        }

        if ((string) config('nagad.gateway_type', self::MODE_API) !== self::MODE_PERSONAL) {
            return false;
        }

        return filled(config('nagad.personal_number') ?? config('nagad.merchant_number'));
    }

    public static function merchantNumber(string $gateway): ?string
    {
        return match ($gateway) {
            PaymentGateway::BKASH => config('bkash.personal_number'),
            PaymentGateway::NAGAD => config('nagad.personal_number') ?? config('nagad.merchant_number'),
            PaymentGateway::ROCKET => config('rocket.merchant_number'),
            default => null,
        };
    }

    public static function merchantName(string $gateway): string
    {
        return match ($gateway) {
            PaymentGateway::BKASH => (string) config('bkash.personal_name', config('app.name')),
            PaymentGateway::NAGAD => (string) config('nagad.personal_name', config('app.name')),
            PaymentGateway::ROCKET => (string) config('rocket.merchant_name', config('app.name')),
            default => (string) config('app.name'),
        };
    }

    public static function normalizeTrxId(string $trxId): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($trxId)) ?? '');
    }

    /**
     * @return array{ok: bool, reasons: list<string>}
     */
    public static function validateTrxFormat(string $gateway, string $trxId): array
    {
        $cfg = config("mfs_personal.gateways.{$gateway}", []);
        $min = max(4, (int) ($cfg['trx_min_length'] ?? 8));
        $pattern = (string) ($cfg['trx_pattern'] ?? '/^[A-Z0-9]{6,24}$/');

        $reasons = [];
        if (strlen($trxId) < $min) {
            $reasons[] = 'trx_too_short';
        }
        if ($pattern !== '' && ! preg_match($pattern, $trxId)) {
            $reasons[] = 'trx_format_invalid';
        }

        return ['ok' => $reasons === [], 'reasons' => $reasons];
    }

    public static function autoVerifyEnabled(string $gateway): bool
    {
        if ((bool) config('mfs_personal.sms_ingest.enabled', false)
            && (bool) config('mfs_personal.sms_ingest.auto_approve_sms', false)) {
            return true;
        }

        if ($gateway === \App\Support\PaymentGateway::ROCKET
            && (bool) config('rocket.auto_verify', false)) {
            return true;
        }

        return (bool) config("mfs_personal.gateways.{$gateway}.auto_verify", false);
    }

    /**
     * Human-readable hint when TrxID confirmation stays pending.
     *
     * @param  list<string>  $reasons
     */
    public static function pendingReasonMessage(array $reasons): string
    {
        $map = [
            'no_sms_match' => 'No matching SMS in ledger yet — customer may have submitted TrxID before SMS arrived; will auto-verify when MFS Verify sends SMS.',
            'trx_too_short' => 'Transaction ID is too short.',
            'trx_format_invalid' => 'Transaction ID format is invalid.',
            'amount_mismatch' => 'Amount does not match the payment session.',
            'sms_amount_mismatch' => 'SMS amount does not match.',
            'remote_verify_failed' => 'Rocket remote verify failed.',
        ];

        $parts = [];
        foreach ($reasons as $code) {
            $parts[] = $map[$code] ?? $code;
        }

        if ($parts === []) {
            return 'Waiting for admin approval or SMS match.';
        }

        return implode(' ', $parts);
    }

    /**
     * Customer-facing notice when TrxID could not be auto-verified (wrong TrxID / no SMS match).
     *
     * @param  array{message?: string, checks?: array<string, mixed>}  $result
     */
    public static function customerPendingNotice(string $gateway, array $result): string
    {
        $number = self::merchantNumber($gateway);
        $label = PaymentGateway::label($gateway);
        $detail = trim((string) ($result['message'] ?? ''));

        if ($number !== null && $number !== '') {
            $call = "সঠিক TrxID দিন, অথবা {$label} নম্বর {$number} এ কল করে পেমেন্ট নিশ্চিত করুন। অ্যাডমিন যাচাই করে অনুমোদন দিলে বিল ক্লিয়ার হবে।";

            return $detail !== '' ? $detail.' '.$call : $call;
        }

        return $detail !== ''
            ? $detail.' অ্যাডমিন যাচাই করলে বিল ক্লিয়ার হবে।'
            : 'পেমেন্ট যাচাইয়ের জন্য অ্যাডমিনের কাছে পাঠানো হয়েছে। অনুমোদনের পর বিল ক্লিয়ার হবে।';
    }

    public static function merchantTelUri(string $gateway): ?string
    {
        $raw = self::merchantNumber($gateway);
        if ($raw === null || $raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880')) {
            return 'tel:+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return 'tel:+88'.$digits;
        }

        return 'tel:'.$digits;
    }
}
