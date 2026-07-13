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
                $existing->forceFill([
                    'name' => $row['name'],
                    'description' => $row['description'] ?? $existing->description,
                    'artisan_command' => $row['artisan_command'],
                    'command_options' => $row['command_options'],
                    'when_config_key' => $row['when_config_key'] ?? null,
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
                'description' => 'Creates monthly bills on the last day of each month (23:45).',
                'artisan_command' => 'cpagol:generate-monthly-bills',
                'command_options' => [],
                'execute_at' => '23:45',
                'interval' => 'daily',
                'sort_order' => 10,
            ],
            [
                'slug' => 'monthly-bill-sms',
                'name' => 'Monthly bill SMS',
                'description' => 'Sends bill SMS to all customers on the 1st of each month.',
                'artisan_command' => 'cpagol:send-monthly-bill-sms',
                'command_options' => [],
                'execute_at' => '10:00',
                'interval' => 'daily',
                'sort_order' => 20,
            ],
            [
                'slug' => 'payment-reminder-alerts',
                'name' => 'Payment reminder alerts',
                'description' => 'Due / overdue payment reminder SMS.',
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
                'description' => 'Sync PPP online/offline from routers every minute.',
                'artisan_command' => 'app:poll-ppp-online',
                'command_options' => [],
                'execute_at' => '00:00',
                'interval' => 'every_minute',
                'without_overlapping_minutes' => 1,
                'sort_order' => 50,
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
