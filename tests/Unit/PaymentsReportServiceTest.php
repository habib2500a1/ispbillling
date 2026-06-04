<?php

namespace Tests\Unit;

use App\Services\Reports\PaymentsReportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentsReportServiceTest extends TestCase
{
    public function test_discount_sql_uses_postgres_json_operator_on_pgsql(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL-only assertion.');
        }

        $service = new PaymentsReportService;
        $method = new \ReflectionMethod($service, 'discountTotalSelectSql');
        $method->setAccessible(true);

        $sql = $method->invoke($service);

        $this->assertStringContainsString("meta->>'discount'", $sql);
        $this->assertStringNotContainsString('JSON_EXTRACT', $sql);
    }
}
