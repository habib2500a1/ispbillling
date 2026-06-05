<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\IspPermissionsSeeder;
use Database\Seeders\IspRolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'isp:bootstrap-admin';

    protected $description = 'Ensure super-admin exists using ISP_ADMIN_EMAIL and ISP_ADMIN_PASSWORD from .env';

    public function handle(): int
    {
        $email = trim((string) config('isp.admin_email'));
        $password = (string) config('isp.admin_password');

        if ($email === '' || $password === '') {
            $this->warn('Set ISP_ADMIN_EMAIL and ISP_ADMIN_PASSWORD in Environment.');

            return self::FAILURE;
        }

        Artisan::call('db:seed', [
            '--class' => IspPermissionsSeeder::class,
            '--force' => true,
        ]);
        Artisan::call('db:seed', [
            '--class' => IspRolesSeeder::class,
            '--force' => true,
        ]);

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'ISP Administrator',
                'password' => Hash::make($password),
            ]
        );

        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }

        $this->info("Super-admin ready: {$email}");

        return self::SUCCESS;
    }
}
