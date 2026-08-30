<?php

namespace Database\Seeders;

use App\Models\AutomaticProcess;
use App\Services\Automation\AutomaticProcessScheduler;
use Illuminate\Database\Seeder;

class AutomaticProcessSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncDefaults(fullRestore: true);
    }

    /**
     * @return array{created: int, updated: int}
     */
    public function syncOnDeploy(): array
    {
        return $this->syncDefaults(fullRestore: false);
    }

    /**
     * @return array{created: int, updated: int}
     */
    private function syncDefaults(bool $fullRestore): array
    {
        $scheduler = app(AutomaticProcessScheduler::class);
        $stats = ['created' => 0, 'updated' => 0];

        foreach ($this->defaultRows() as $row) {
            $enabled = (bool) ($row['enabled'] ?? true);
            unset($row['enabled']);

            $existing = AutomaticProcess::query()->where('slug', $row['slug'])->first();

            if ($existing === null) {
                $process = AutomaticProcess::query()->create(
                    array_merge($row, ['enabled' => $enabled]),
                );
                $process->forceFill([
                    'next_run_at' => $scheduler->computeNextRunAt($process),
                ])->save();
                $stats['created']++;

                continue;
            }

            if ($fullRestore) {
                $existing->forceFill(array_merge($row, ['enabled' => $enabled]))->save();
            } else {
                // Keep owner schedule, name, and enabled as edited on Automatic Processes.
                $existing->forceFill([
                    'artisan_command' => $row['artisan_command'],
                    'command_options' => $row['command_options'] ?? $existing->command_options,
                    'when_config_key' => array_key_exists('when_config_key', $row)
                        ? $row['when_config_key']
                        : $existing->when_config_key,
                    'without_overlapping_minutes' => $row['without_overlapping_minutes'] ?? $existing->without_overlapping_minutes,
                    'sort_order' => $row['sort_order'],
                ])->save();
            }
            $stats['updated']++;

            if ($existing->fresh()->next_run_at === null) {
                $existing->forceFill([
                    'next_run_at' => $scheduler->computeNextRunAt($existing),
                ])->save();
            }
        }

        return $stats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultRows(): array
    {
        return [
            [
                'slug' => 'generate-monthly-bills',
                'name' => 'Generate monthly bills',
                'description' => 'Creates monthly bills daily for customers whose billing_day matches today (full sweep on last day / --force).',
                'artisan_command' => 'cpagol:generate-monthly-bills',
                'command_options' => [],
                'execute_at' => '23:45',
                'interval' => 'daily',
                'sort_order' => 10,
            ],
            [
                'slug' => 'monthly-bill-sms',
                'name' => 'Monthly bill SMS',
                'description' => 'Sends bill SMS on the day set under Billing rules (default: 1st).',
                'artisan_command' => 'cpagol:send-monthly-bill-sms',
                'command_options' => [],
                'execute_at' => '10:00',
                'interval' => 'daily',
                'sort_order' => 20,
            ],
            [
                'slug' => 'payment-reminder-alerts',
                'name' => 'Payment reminder alerts',
                'description' => 'Due reminder SMS N days before expire (N is under Billing rules).',
                'artisan_command' => 'cpagol:payment-reminder-alerts',
                'command_options' => [],
                'execute_at' => '08:00',
                'interval' => 'daily',
                'sort_order' => 30,
            ],
            [
                'slug' => 'disable-unpaid-users',
                'name' => 'Disable unpaid customers',
                'description' => 'Auto-disable overdue customers on MikroTik.',
                'artisan_command' => 'cpagol:disable-unpaid-users',
                'command_options' => [],
                'execute_at' => '08:30',
                'interval' => 'daily',
                'sort_order' => 40,
            ],
            [
                'slug' => 'poll-ppp-online',
                'name' => 'Poll PPP online status',
                'description' => 'Sync PPP online/offline and snapshot session / daily / monthly GB usage.',
                'artisan_command' => 'app:poll-ppp-online',
                'command_options' => [],
                'execute_at' => '00:00',
                'interval' => 'every_minute',
                'without_overlapping_minutes' => 1,
                'sort_order' => 50,
            ],
            [
                'slug' => 'reset-traffic-month',
                'name' => 'Reset monthly traffic cache',
                'description' => 'Clears last month GB totals so the new month starts at 0 (profile + portal).',
                'artisan_command' => 'app:reset-traffic-month',
                'command_options' => [],
                'execute_at' => '00:05',
                'interval' => 'daily',
                'without_overlapping_minutes' => 10,
                'sort_order' => 52,
            ],
            [
                'slug' => 'poll-router-logs',
                'name' => 'Poll router logs',
                'description' => 'Fetch MikroTik logs every minute.',
                'artisan_command' => 'app:poll-router-logs',
                'command_options' => [],
                'execute_at' => '00:00',
                'interval' => 'every_minute',
                'without_overlapping_minutes' => 1,
                'sort_order' => 60,
            ],
            [
                'slug' => 'olt-health-poll',
                'name' => 'OLT health poll',
                'description' => 'Poll OLT optical health every 15 minutes.',
                'artisan_command' => 'olt:poll-health',
                'command_options' => [],
                'execute_at' => '00:00',
                'interval' => 'every_fifteen_minutes',
                'without_overlapping_minutes' => 10,
                'sort_order' => 70,
            ],
            [
                'slug' => 'saas-sync-domains',
                'name' => 'Publish ISP domains to Caddy',
                'description' => 'Keeps sold ISP custom domains on the shared HTTPS proxy.',
                'artisan_command' => 'saas:sync-domains',
                'command_options' => [],
                'execute_at' => '00:00',
                'interval' => 'every_minute',
                'without_overlapping_minutes' => 1,
                'sort_order' => 76,
            ],
            [
                'slug' => 'saas-lock-overdue',
                'name' => 'Lock unpaid SaaS operators',
                'description' => 'Lock ISP admins whose platform bill is past due.',
                'artisan_command' => 'saas:lock-overdue',
                'command_options' => [],
                'execute_at' => '00:15',
                'interval' => 'daily',
                'sort_order' => 75,
            ],
            [
                'slug' => 'prune-router-logs',
                'name' => 'Prune old router logs',
                'description' => 'Delete router logs older than retention setting.',
                'artisan_command' => 'cpagol:prune-router-logs',
                'command_options' => [],
                'execute_at' => '04:00',
                'interval' => 'daily',
                'sort_order' => 80,
            ],
        ];
    }
}
