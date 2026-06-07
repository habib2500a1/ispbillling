<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\EnsureStorageWritable;
use App\Support\MobileAppLinks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class ProductionAuditCommand extends Command
{
    protected $signature = 'isp:production-audit {--json : Output JSON only} {--skip-tests : Do not run PHPUnit}';

    protected $description = 'Audit production security, webhooks, Redis, Horizon, queue, mobile APKs, and shop.';

    /**
     * @var list<array{check: string, status: string, detail: string}>
     */
    private array $results = [];

    public function handle(): int
    {
        $this->checkStorage();
        $this->checkAppSecurity();
        $this->checkRedis();
        $this->checkHorizon();
        $this->checkQueue();
        $this->checkWebhookSecrets();
        $this->checkFailedJobs();
        $this->checkMobileApks();
        $this->checkShop();

        if (! $this->option('skip-tests')) {
            $this->runTests();
        }

        if ($this->option('json')) {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT));

            return $this->exitCode();
        }

        $this->table(['Check', 'Status', 'Detail'], $this->results);
        $critical = collect($this->results)->where('status', 'FAIL')->count();
        $warn = collect($this->results)->where('status', 'WARN')->count();
        $this->newLine();
        $this->info("Audit complete: {$critical} critical, {$warn} warnings.");

        return $this->exitCode();
    }

    private function exitCode(): int
    {
        return collect($this->results)->contains(fn (array $r): bool => $r['status'] === 'FAIL')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function record(string $check, string $status, string $detail): void
    {
        $this->results[] = compact('check', 'status', 'detail');
    }

    private function checkStorage(): void
    {
        $issues = EnsureStorageWritable::findIssues();
        $this->record(
            'Storage writable',
            $issues === [] ? 'OK' : 'FAIL',
            $issues === [] ? 'All paths OK' : implode('; ', $issues),
        );
    }

    private function runTests(): void
    {
        $exit = Artisan::call('test', [], $this->output);
        $this->record('PHPUnit', $exit === 0 ? 'OK' : 'FAIL', $exit === 0 ? 'Passed' : 'Failed');
    }

    private function checkAppSecurity(): void
    {
        $key = (string) config('app.key', '');
        $this->record(
            'APP_KEY',
            $key !== '' && str_starts_with($key, 'base64:') ? 'OK' : 'FAIL',
            $key !== '' ? 'Set' : 'Missing — run php artisan key:generate',
        );

        $debug = (bool) config('app.debug', true);
        $env = (string) config('app.env', 'local');
        $this->record(
            'APP_DEBUG',
            ($env === 'production' && $debug) ? 'WARN' : 'OK',
            $env.' / debug='.($debug ? 'true' : 'false'),
        );
    }

    private function checkRedis(): void
    {
        try {
            $pong = Redis::connection()->ping();
            $this->record('Redis', ($pong === true || $pong === 'PONG') ? 'OK' : 'WARN', 'Ping successful');
        } catch (\Throwable $e) {
            $this->record('Redis', 'FAIL', $e->getMessage());
        }
    }

    private function checkHorizon(): void
    {
        try {
            $running = Artisan::call('horizon:status') === 0;
            $detail = $running ? 'Running' : (trim(Artisan::output()) ?: 'Not running');
            $this->record('Horizon', $running ? 'OK' : 'FAIL', $detail);
        } catch (\Throwable $e) {
            $this->record('Horizon', 'FAIL', $e->getMessage());
        }
    }

    private function checkQueue(): void
    {
        $connection = (string) config('queue.default', 'sync');
        $heavy = (bool) config('queue_ops.heavy_jobs_enabled', false);
        $this->record(
            'Queue driver',
            $connection === 'redis' ? 'OK' : 'WARN',
            "connection={$connection}, heavy_jobs=".($heavy ? 'on' : 'off'),
        );
    }

    private function checkWebhookSecrets(): void
    {
        $keys = [
            'PAYMENT_WEBHOOK_SECRET',
            'ISP_SUPPORT_WEBHOOK_SECRET',
            'NETFLOW_WEBHOOK_SECRET',
            'OPTICAL_WEBHOOK_SECRET',
            'CALL_CENTER_WEBHOOK_SECRET',
            'ROCKET_WEBHOOK_SECRET',
            'MFS_SMS_DEVICE_API_KEY',
            'WHATSAPP_WEBHOOK_VERIFY_TOKEN',
        ];

        $envPath = base_path('.env');
        $contents = File::exists($envPath) ? File::get($envPath) : '';
        $missing = [];

        foreach ($keys as $key) {
            if (! preg_match('/^'.preg_quote($key, '/').'=(.+)$/m', $contents, $m) || trim($m[1]) === '') {
                $missing[] = $key;
            }
        }

        $this->record(
            'Webhook secrets',
            $missing === [] ? 'OK' : 'FAIL',
            $missing === [] ? 'All 8 secrets set' : 'Missing: '.implode(', ', $missing),
        );
    }

    private function checkFailedJobs(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->record('Failed jobs', 'OK', 'Table not used');

            return;
        }

        $count = (int) DB::table('failed_jobs')->count();
        $this->record(
            'Failed jobs',
            $count === 0 ? 'OK' : ($count <= 5 ? 'WARN' : 'FAIL'),
            (string) $count,
        );
    }

    private function checkMobileApks(): void
    {
        $files = [
            'isp-radiant.apk' => public_path('downloads/isp-radiant.apk'),
            'isp-mfs-verify.apk' => public_path('downloads/isp-mfs-verify.apk'),
        ];

        $details = [];
        $ok = true;

        foreach ($files as $label => $path) {
            if (! is_file($path) || filesize($path) < 1000) {
                $ok = false;
                $details[] = "{$label}: missing";
            } else {
                $details[] = "{$label}: ".round(filesize($path) / 1024 / 1024, 1).' MB';
            }
        }

        $source = MobileAppLinks::mfsVerifySource();
        $details[] = 'source='.$source;

        $this->record('Mobile APKs', $ok ? 'OK' : 'WARN', implode('; ', $details));
    }

    private function checkShop(): void
    {
        if (! config('inventory.shop_enabled', true)) {
            $this->record('Public shop', 'WARN', 'INVENTORY_SHOP_ENABLED=false');

            return;
        }

        $tenantId = (int) config('inventory.default_tenant_id', 1);
        $count = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('show_on_shop', true)
            ->where('stock_qty', '>', 0)
            ->count();

        $this->record(
            'Shop products',
            $count > 0 ? 'OK' : 'WARN',
            "{$count} active storefront products",
        );
    }
}
