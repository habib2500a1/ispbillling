<?php

namespace Tests\Unit;

use App\Services\Payments\MfsCustomerReferenceMatcher;
use App\Support\MfsPaymentReferenceParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MfsPaymentReferenceParserTest extends TestCase
{
    #[DataProvider('referenceSamples')]
    public function test_extracts_subscriber_reference(string $sms, string $expected): void
    {
        $tokens = MfsPaymentReferenceParser::extractFromMessage($sms, 'BKASH12345678');

        $this->assertContains($expected, $tokens);
    }

    public function test_numeric_variants_strip_leading_zero(): void
    {
        $this->assertContains('0790', MfsPaymentReferenceParser::numericVariants('0790'));
        $this->assertContains('790', MfsPaymentReferenceParser::numericVariants('0790'));
        $this->assertContains('0782', MfsPaymentReferenceParser::numericVariants('782'));
    }

    public function test_normalize_reference_strips_trailing_period(): void
    {
        $this->assertSame('782', MfsPaymentReferenceParser::normalizeReferenceToken('782.'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function referenceSamples(): array
    {
        return [
            'ref_short_id' => [
                'You have received Tk 500.00 from 01712345678 Ref 0790 Fee Tk 0.00 Balance Tk 1500.00. TrxID BKASH12345678',
                '0790',
            ],
            'counter_id' => [
                'Nagad: Tk 300.00 received. Counter 790. TxnID NAG12345678. Balance Tk 2000.',
                '790',
            ],
            'reference_label' => [
                'bKash: Tk 99.50 received. Reference: user@realm. TrxID: ABC12XYZ99',
                'user@realm',
            ],
        ];
    }
}
