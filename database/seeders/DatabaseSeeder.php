<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(IspPermissionsSeeder::class);
        $this->call(IspRolesSeeder::class);

        $email = config('isp.admin_email');
        $password = config('isp.admin_password');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'ISP Administrator',
                'password' => Hash::make($password),
            ]
        );

        if (! $user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }

        $this->call(AutomaticProcessSeeder::class);
    }
}
