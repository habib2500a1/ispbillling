<?php

namespace Tests\Unit;

use App\Support\BdcomOnuDescriptionHeuristic;
use PHPUnit\Framework\TestCase;

class BdcomOnuDescriptionHeuristicTest extends TestCase
{
    public function test_detects_olt_placeholder_labels(): void
    {
        $this->assertTrue(BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel('ONU'));
        $this->assertTrue(BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel('010H'));
        $this->assertTrue(BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel('----'));
    }

    public function test_accepts_real_ppp_style_logins(): void
    {
        $this->assertTrue(BdcomOnuDescriptionHeuristic::looksLikePppUsername('ak-mehedi'));
        $this->assertFalse(BdcomOnuDescriptionHeuristic::looksLikePppUsername('010T'));
    }

    public function test_numeric_client_code_still_looks_like_placeholder(): void
    {
        $this->assertTrue(BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel('252'));
    }

    public function test_skip_linking_without_tenant_customer_is_unknown_placeholder(): void
    {
        $this->assertTrue(BdcomOnuDescriptionHeuristic::shouldSkipDescriptionForLinking(0, '010H'));
        $this->assertTrue(BdcomOnuDescriptionHeuristic::shouldSkipDescriptionForLinking(0, '252'));
    }
}
