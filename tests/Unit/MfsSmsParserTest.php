<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Mirrors mobile/mfs_verify/lib/utils/mfs_sms_parser.dart — keep in sync when changing parser.
 */
final class MfsSmsParserTest extends TestCase
{
    #[DataProvider('validSamples')]
    public function test_parses_realistic_mfs_sms(string $body, string $gateway, string $trx, float $amount): void
    {
        $parsed = $this->parse($body);

        $this->assertTrue($parsed['valid'], 'Expected valid parse for: '.$body);
        $this->assertSame($gateway, $parsed['gateway']);
        $this->assertSame($trx, $parsed['transaction_id']);
        $this->assertEqualsWithDelta($amount, $parsed['amount'], 0.001);
    }

    public function test_rejects_non_payment_sms(): void
    {
        $parsed = $this->parse('Your OTP is 123456 for login');

        $this->assertFalse($parsed['valid']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: float}>
     */
    public static function validSamples(): array
    {
        return [
            'bkash_received' => [
                'You have received Tk 500.00 from 01712345678. Fee Tk 0.00. Balance Tk 1500.00. TrxID 8N3ABCD1234 at 21/05/26 10:30',
                'bkash',
                '8N3ABCD1234',
                500.00,
            ],
            'bkash_comma_amount' => [
                'Cash In Tk 1,250.50 received. TrxID CGA1A2B3C4 Fee Tk 0.00 Balance Tk 5000.00',
                'bkash',
                'CGA1A2B3C4',
                1250.50,
            ],
            'nagad' => [
                'Nagad: Tk 300.00 received from 01798765432. TxnID NAG12345678. Balance Tk 2000.',
                'nagad',
                'NAG12345678',
                300.00,
            ],
            'bkash_lowercase_trxid' => [
                'bKash: Tk 99.50 received. TrxID: abc12xyZ99',
                'bkash',
                'ABC12XYZ99',
                99.50,
            ],
        ];
    }

    /**
     * @return array{gateway: ?string, transaction_id: ?string, amount: ?float, valid: bool}
     */
    private function parse(string $text): array
    {
        $body = trim($text);
        if ($body === '') {
            return ['gateway' => null, 'transaction_id' => null, 'amount' => null, 'valid' => false];
        }

        if (preg_match('/\botp\b|verification code|one time password/i', $body)) {
            return ['gateway' => null, 'transaction_id' => null, 'amount' => null, 'valid' => false];
        }

        $lower = strtolower($body);
        $gateway = null;
        if (str_contains($lower, 'bkash') || str_contains($lower, 'b-kash')) {
            $gateway = 'bkash';
        } elseif (str_contains($lower, 'nagad')) {
            $gateway = 'nagad';
        } elseif (str_contains($lower, 'rocket')) {
            $gateway = 'rocket';
        } elseif (preg_match('/cash\s*in|you have received|received tk|send money/i', $body)) {
            $gateway = 'bkash';
        } else {
            return ['gateway' => null, 'transaction_id' => null, 'amount' => null, 'valid' => false];
        }

        $upper = strtoupper($body);
        $trx = null;
        if (preg_match('/(?:TRX(?:ID)?|TXN(?:ID)?|TRANSACTION)\s*(?:ID|NO)?[\s:#-]*([A-Z0-9]{6,20})/i', $upper, $m)) {
            $trx = strtoupper($m[1]);
        } elseif (preg_match('/\bREF[\s:#-]*([A-Z0-9]{6,20})\b/i', $upper, $m)) {
            $trx = strtoupper($m[1]);
        }

        $normalized = preg_replace('/(\d),(?=\d{3})/', '$1', $body) ?? $body;
        $amount = null;
        foreach ([
            '/(?:TK|TAKA|BDT|AMOUNT)\s*[:\s]*([0-9]+(?:\.[0-9]{1,2})?)/i',
            '/([0-9]+(?:\.[0-9]{1,2})?)\s*(?:TK|TAKA|BDT)/i',
            '/(?:received|credited|cash\s*in)\s*(?:tk|taka|bdt)?\s*([0-9]+(?:\.[0-9]{1,2})?)/i',
        ] as $pattern) {
            if (preg_match($pattern, $normalized, $m)) {
                $amount = (float) $m[1];
                break;
            }
        }

        $valid = $gateway !== null && $trx !== null && strlen($trx) >= 6 && $amount !== null && $amount > 0;

        return [
            'gateway' => $gateway,
            'transaction_id' => $trx,
            'amount' => $amount,
            'valid' => $valid,
        ];
    }
}
