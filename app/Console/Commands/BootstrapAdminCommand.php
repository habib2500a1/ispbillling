<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\IspPermissionsSeeder;
use Database\Seeders\IspRolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'isp:bootstrap-admin {--reset-password : Overwrite password for existing super-admin from .env}';

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

        $user = User::query()->withoutGlobalScopes()->where('email', $email)->first();

        if ($user === null) {
            $user = User::query()->create([
                'email' => $email,
                'name' => 'ISP Administrator',
                'password' => $password,
                'is_active' => true,
            ]);
            $this->info("Super-admin created: {$email}");
        } else {
            $user->forceFill([
                'name' => $user->name ?: 'ISP Administrator',
                'is_active' => true,
            ]);

            if ($this->option('reset-password')) {
                $user->password = $password;
                $this->warn("Super-admin password reset from .env: {$email}");
            }

            $user->save();
            $this->info("Super-admin verified: {$email}");
        }

        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }

        return self::SUCCESS;
    }
}
