<?php

namespace App\Console\Commands;

use App\Http\Controllers\CustomersController;
use App\Http\Controllers\MikrotikController;
use App\Models\AutomaticProcess;
use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Production readiness smoke check — no secrets printed.
 */
class SmokeCheck extends Command
{
    protected $signature = 'cpagol:smoke-check';

    protected $description = 'Verify deploy readiness: DB, admin, billing hooks, routes, auto-processes';

    public function handle(): int
    {
        $ok = true;
        $check = function (string $label, bool $pass, string $hint = '') use (&$ok): void {
            if ($pass) {
                $this->info("[OK] {$label}");
            } else {
                $ok = false;
                $this->error("[FAIL] {$label}".($hint ? " — {$hint}" : ''));
            }
        };

        $check('APP_KEY set', filled(config('app.key')));
        $check('DB connected', rescue(fn () => Schema::hasTable('users') && Schema::hasTable('billing_infos'), false));
        $check(
            'Super Admin exists',
            User::query()->role('Super Admin')->exists(),
            'Run: php artisan db:seed --class=SuperAdminSeeder --force'
        );

        $check('billing_infos.billing_day column', Schema::hasColumn('billing_infos', 'billing_day'));
        $check('billing_infos.grace_period_days column', Schema::hasColumn('billing_infos', 'grace_period_days'));
        $check('billing_infos.auto_disable_date column', Schema::hasColumn('billing_infos', 'auto_disable_date'));

        $check('PaymentService class', class_exists(\App\Services\PaymentService::class));
        $check('Mikrotik enablePPPSecret', method_exists(MikrotikController::class, 'enablePPPSecret'));
        $check('Mikrotik disablePPPSecret', method_exists(MikrotikController::class, 'disablePPPSecret'));
        $check('CustomersController customerEnable', method_exists(CustomersController::class, 'customerEnable'));

        $requiredRoutes = [
            'login',
            'dashboard',
            'payment-collection',
            'ops-insights',
            'customers.index',
        ];
        foreach ($requiredRoutes as $name) {
            $check("route:{$name}", Route::has($name));
        }

        $requiredCommands = [
            'cpagol:generate-monthly-bills',
            'cpagol:disable-unpaid-users',
            'cpagol:payment-reminder-alerts',
            'cpagol:send-monthly-bill-sms',
            'cpagol:run-automatic-processes',
        ];
        foreach ($requiredCommands as $cmd) {
            $check("command:{$cmd}", array_key_exists($cmd, \Artisan::all()));
        }

        if (Schema::hasTable('automatic_processes')) {
            $enabled = AutomaticProcess::query()->where('enabled', true)->count();
            $check("automatic_processes enabled ({$enabled})", $enabled >= 4, 'Seed AutomaticProcessSeeder');
        }

        $due = BillingInfo::query()->where('due_amount', '>', 0)->count();
        $active = CustomersInfo::query()->where('status', 'active')->count();
        $this->line("Info: active_customers={$active}, billing_with_due={$due}");

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
