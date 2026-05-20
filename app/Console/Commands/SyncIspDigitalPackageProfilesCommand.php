<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Services\Import\IspDigitalSessionClient;
use App\Support\IspDigitalPackageSpeed;
use Illuminate\Console\Command;
use Throwable;

class SyncIspDigitalPackageProfilesCommand extends Command
{
    protected $signature = 'isp:sync-package-profiles-from-isp-digital
                            {--query=alloverclients : ISP Digital list filter}
                            {--dry-run : Show changes without saving}
                            {--url= : Override ISP_DIGITAL_URL}
                            {--user= : Override ISP_DIGITAL_USERNAME}
                            {--password= : Override ISP_DIGITAL_PASSWORD}';

    protected $description = 'Set packages.mikrotik_profile_name from ISP Digital PackageSpeed (display/profile)';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('isp_digital.base_url'));
        $username = (string) ($this->option('user') ?: config('isp_digital.username'));
        $password = (string) ($this->option('password') ?: config('isp_digital.password'));

        if ($password === '') {
            $this->error('Set ISP_DIGITAL_PASSWORD in .env or pass --password=');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $query = (string) $this->option('query');

        $this->info("Logging in to {$baseUrl} as {$username}…");

        try {
            $client = new IspDigitalSessionClient($baseUrl, $username, $password);
            $client->login();
        } catch (Throwable $e) {
            $this->error('Login failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $probe = $client->fetchCustomerPage(0, 1, $query);
        $total = (int) ($probe['iTotalDisplayRecords'] ?? 0);

        if ($total < 1) {
            $this->warn('No clients returned from ISP Digital.');

            return self::FAILURE;
        }

        $this->info("Scanning {$total} ISP Digital subscribers (query={$query})…");

        /** @var array<string, array{display: string, votes: array<string, int>}> $byDisplay */
        $byDisplay = [];
        $batch = 100;

        for ($offset = 0; $offset < $total; $offset += $batch) {
            $length = min($batch, $total - $offset);
            $page = $client->fetchCustomerPage($offset, $length, $query);

            foreach ($page['aaData'] as $row) {
                $parsed = IspDigitalPackageSpeed::parse($row);
                $display = $parsed['display_name'];
                $profile = $parsed['mikrotik_profile'];

                if ($display === '' || $profile === null) {
                    continue;
                }

                $key = strtolower($display);
                if (! isset($byDisplay[$key])) {
                    $byDisplay[$key] = ['display' => $display, 'votes' => []];
                }
                $byDisplay[$key]['votes'][$profile] = ($byDisplay[$key]['votes'][$profile] ?? 0) + 1;
            }
        }

        $updated = 0;
        $unchanged = 0;
        $linkedByProfile = 0;
        $missing = 0;

        foreach ($byDisplay as $entry) {
            $display = $entry['display'];
            arsort($entry['votes']);
            $profile = (string) array_key_first($entry['votes']);
            $count = $entry['votes'][$profile] ?? 0;
            $conflicts = count($entry['votes']);
            if ($conflicts > 1) {
                $alt = array_slice($entry['votes'], 1, 3, true);
                $parts = [];
                foreach ($alt as $name => $votes) {
                    $parts[] = "{$name} ({$votes})";
                }
                $this->warn("“{$display}” → {$profile} ({$count}); also: ".implode(', ', $parts));
            }

            $package = Package::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($display)])
                ->first();

            if ($package === null) {
                $package = Package::query()
                    ->where(function ($query) use ($profile): void {
                        $query->whereRaw('LOWER(mikrotik_profile_name) = ?', [strtolower($profile)])
                            ->orWhereRaw('LOWER(name) = ?', [strtolower($profile)]);
                    })
                    ->orderBy('id')
                    ->first();

                if ($package !== null) {
                    $linkedByProfile++;
                    $this->line("  link #{$package->id} “{$package->name}” ← ISP Digital “{$display}” / {$profile} ({$count} clients)");
                } else {
                    $missing++;
                    $this->line("  [missing] {$display} → {$profile} ({$count} clients)");

                    continue;
                }
            }

            if ($package->mikrotik_profile_name === $profile) {
                $unchanged++;

                continue;
            }

            $this->line(sprintf(
                '  %s #%d “%s”: %s → %s (%d clients)',
                $dryRun ? '[dry-run]' : 'update',
                $package->id,
                $package->name,
                $package->mikrotik_profile_name ?? '—',
                $profile,
                $count,
            ));

            if (! $dryRun) {
                $package->update(['mikrotik_profile_name' => $profile]);
            }

            $updated++;
        }

        $this->newLine();
        $this->table(['', 'Count'], [
            ['Unique package labels from ISP Digital', count($byDisplay)],
            ['Updated mikrotik_profile_name', $updated],
            ['Already correct', $unchanged],
            ['Matched existing router package by profile', $linkedByProfile],
            ['No matching local package', $missing],
            ['Mode', $dryRun ? 'dry-run' : 'applied'],
        ]);

        return self::SUCCESS;
    }
}
