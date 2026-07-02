<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class RunMonthlyBillingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /** @param  array<string, mixed>  $options */
    public function __construct(
        public array $options = [],
    ) {}

    public function handle(): void
    {
        $exit = Artisan::call('isp:generate-bills', self::artisanParameters($this->options));
        if ($exit !== 0) {
            throw new \RuntimeException("isp:generate-bills failed with exit code {$exit}");
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function artisanParameters(array $options): array
    {
        $allowed = [
            'date',
            'customer',
            'force',
            'no-prorate',
            'coupon',
            'cycle',
            'dry-run',
            'queued',
        ];

        $parameters = ['--queued' => true];

        foreach ($options as $key => $value) {
            $name = str_starts_with((string) $key, '--') ? substr((string) $key, 2) : (string) $key;

            if (! in_array($name, $allowed, true)) {
                continue;
            }

            if ($value === null || $value === false || $value === '') {
                continue;
            }

            $parameters['--'.$name] = $value === true ? true : $value;
        }

        return $parameters;
    }
}
