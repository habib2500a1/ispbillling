<?php

namespace App\Console\Commands;

use Database\Seeders\BangladeshGeoSeeder;
use Illuminate\Console\Command;

class SeedBangladeshGeoCommand extends Command
{
    protected $signature = 'isp:seed-bangladesh-geo';

    protected $description = 'Seed Bangladesh districts and common upazilas (global reference data)';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => BangladeshGeoSeeder::class, '--force' => true]);
        $this->info('Bangladesh geo reference data seeded.');

        return self::SUCCESS;
    }
}
