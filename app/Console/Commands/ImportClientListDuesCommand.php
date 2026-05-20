<?php

namespace App\Console\Commands;

use App\Services\Import\ClientListDueImporter;
use App\Services\Import\IspDigitalCurrentBillingSyncService;
use App\Services\Import\IspDigitalSessionClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportClientListDuesCommand extends Command
{
    protected $signature = 'isp:import-client-list-dues
                            {file? : CSV, XLSX, or PDF (Client List export)}
                            {--dry-run : Preview counts without saving}
                            {--from-isp-digital : Pull live billing grid (same as Client List PDF)}';

    protected $description = 'Adjust customer dues from Client List PDF/Excel/CSV or live ISP Digital billing grid';

    public function handle(): int
    {
        if ($this->option('from-isp-digital')) {
            return $this->syncFromIspDigital();
        }

        $path = $this->resolveFilePath($this->argument('file'));
        if ($path === null) {
            $this->error('No file found. Upload PDF/CSV/XLSX to storage/app/imports/ or pass a path.');
            $this->line('Example: php artisan isp:import-client-list-dues storage/app/imports/Client_List.pdf');
            $this->line('Or live sync: php artisan isp:import-client-list-dues --from-isp-digital');

            return self::FAILURE;
        }

        $this->info('Importing dues from: '.$path);

        try {
            $stats = app(ClientListDueImporter::class)->importFromPath(
                $path,
                (bool) $this->option('dry-run'),
            );

            $this->table(['Result', 'Count'], [
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
                ['Not found', $stats['not_found']],
                ['Cleared (zero due)', $stats['zeroed']],
            ]);

            if ($stats['errors'] !== []) {
                $this->warn('Sample issues:');
                foreach (array_slice($stats['errors'], 0, 15) as $err) {
                    $this->line('  · '.$err);
                }
            }

            if ($this->option('dry-run')) {
                $this->comment('Dry run — no changes saved.');
            } else {
                $this->info('Due amounts adjusted. Refresh admin billing & mobile app.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function syncFromIspDigital(): int
    {
        $baseUrl = (string) config('isp_digital.base_url');
        $password = (string) config('isp_digital.password');
        if ($password === '') {
            $this->error('Set ISP_DIGITAL_PASSWORD in .env');

            return self::FAILURE;
        }

        try {
            $client = new IspDigitalSessionClient(
                $baseUrl,
                (string) config('isp_digital.username'),
                $password,
            );
            $client->login();
            $result = app(IspDigitalCurrentBillingSyncService::class)->syncAll($client);
            $s = $result['summary'];

            $this->table(['Metric', 'BDT'], [
                ['Monthly bill', number_format($s['monthly_bill'] ?? 0, 2)],
                ['Collected', number_format($s['collected_bill'] ?? 0, 2)],
                ['Due', number_format($s['due'] ?? 0, 2)],
            ]);
            $this->table(['', 'Count'], [
                ['Customers synced', $result['customers']],
                ['Skipped', $result['skipped']],
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveFilePath(?string $file): ?string
    {
        if ($file !== null && $file !== '') {
            $path = $file;
            if (! str_starts_with($path, '/')) {
                $path = base_path($path);
            }

            return is_readable($path) ? $path : null;
        }

        $dir = storage_path('app/imports');
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir);
        }

        foreach (['pdf', 'xlsx', 'xls', 'csv'] as $ext) {
            $matches = glob($dir.'/Client_List*.'.$ext) ?: glob($dir.'/*.'.$ext);
            if ($matches !== [] && $matches !== false) {
                usort($matches, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

                return $matches[0];
            }
        }

        return null;
    }
}
