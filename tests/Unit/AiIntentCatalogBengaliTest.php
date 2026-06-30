<?php

namespace Tests\Unit;

use App\Services\Ai\AiIntentCatalog;
use PHPUnit\Framework\TestCase;

class AiIntentCatalogBengaliTest extends TestCase
{
    public function test_resolves_bengali_due_customers(): void
    {
        $catalog = new AiIntentCatalog;

        $this->assertSame('billing.due_customers', $catalog->resolve('বকেয়া কাস্টমার দেখাও'));
    }

    public function test_resolves_bengali_operational_summary(): void
    {
        $catalog = new AiIntentCatalog;

        $this->assertSame('bi.summary', $catalog->resolve('অপারেশন সারাংশ'));
    }

    public function test_resolves_bengali_offline_onu(): void
    {
        $catalog = new AiIntentCatalog;

        $this->assertSame('network.offline_onus', $catalog->resolve('অফলাইন ওনু'));
    }

    public function test_parses_bengali_zone_filter(): void
    {
        $catalog = new AiIntentCatalog;

        $filters = $catalog->parseFollowUpFilters('জোন মিরপুর');

        $this->assertSame('মিরপুর', $filters['zone'] ?? null);
    }
}
