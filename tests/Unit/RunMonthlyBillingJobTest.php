<?php

namespace Tests\Unit;

use App\Jobs\RunMonthlyBillingJob;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunMonthlyBillingJobTest extends TestCase
{
    public function test_artisan_parameters_skip_unprefixed_null_options(): void
    {
        $this->assertSame(
            ['--queued' => true],
            RunMonthlyBillingJob::artisanParameters([
                'date' => null,
                'customer' => null,
                'force' => false,
                'help' => false,
            ]),
        );
    }

    public function test_artisan_parameters_keep_set_options_and_force_queued(): void
    {
        $this->assertSame(
            [
                '--queued' => true,
                '--date' => '2026-06-01',
                '--customer' => 42,
                '--force' => true,
                '--no-prorate' => true,
                '--coupon' => 'SAVE10',
                '--cycle' => 'monthly',
            ],
            RunMonthlyBillingJob::artisanParameters([
                '--date' => '2026-06-01',
                'customer' => 42,
                'force' => true,
                'no-prorate' => true,
                'coupon' => 'SAVE10',
                'cycle' => 'monthly',
            ]),
        );
    }

    public function test_handle_calls_generate_bills_with_prefixed_options(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('isp:generate-bills', ['--queued' => true, '--force' => true])
            ->andReturn(0);

        (new RunMonthlyBillingJob(['force' => true, 'date' => null]))->handle();
    }
}
