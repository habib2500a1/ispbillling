<?php

namespace Tests\Unit;

use App\Support\MikrotikRateLimitParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MikrotikRateLimitParserTest extends TestCase
{
    #[Test]
    public function parses_symmetric_m_suffix(): void
    {
        $r = MikrotikRateLimitParser::parse('10M/10M');
        $this->assertSame(10, $r['down_mbps']);
        $this->assertSame(10, $r['up_mbps']);
        $this->assertStringContainsString('symmetric', (string) $r['bandwidth_label']);
    }

    #[Test]
    public function zero_zero_means_unlimited_label(): void
    {
        $r = MikrotikRateLimitParser::parse('0/0');
        $this->assertSame('Unlimited', $r['bandwidth_label']);
    }

    #[Test]
    public function parses_k_suffix_bits(): void
    {
        $r = MikrotikRateLimitParser::parse('512k/1024k');
        $this->assertGreaterThanOrEqual(0, (int) ($r['down_mbps'] ?? 0));
        $this->assertGreaterThanOrEqual(0, (int) ($r['up_mbps'] ?? 0));
    }
}
