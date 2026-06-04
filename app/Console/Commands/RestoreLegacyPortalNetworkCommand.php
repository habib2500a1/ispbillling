<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Billing\CustomerLineGraceService;
use App\Services\Import\LegacyPortalOverdueEvaluator;
use App\Support\BillingDefaults;
use App\Support\CustomerNetworkSync;
use App\Support\CustomerStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class RestoreLegacyPortalNetworkCommand extends Command
{
    protected $signature = 'isp:restore-legacy-portal-network
                            {--dry-run : List changes only}
                            {--customer= : Only this customer_code}
                            {--grace-overdue : Line grace until month-end when open invoice (stays ON)}
                            {--no-snapshot : Also restore active subscribers missing legacy_portal_raw snapshot}';

    protected $description = 'Turn lines back ON for subscribers who were active in legacy portal but were suspended by import/billing mistake';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $codeFilter = trim((string) $this->option('customer'));
        $graceOverdue = (bool) $this->option('grace-overdue');
        $includeNoSnapshot = (bool) $this->option('no-snapshot');

        $query = Customer::query()
            ->fromLegacyPortal()
            ->whereNotIn('status', [CustomerStatus::TERMINATED, 'left']);

        if ($codeFilter !== '') {
            $query->where('customer_code', $codeFilter);
        }

        $candidates = $query->orderBy('customer_code')->get()->filter(
            fn (Customer $c): bool => $this->shouldRestore($c, $includeNoSnapshot),
        );

        if ($candidates->isEmpty()) {
            $this->warn('No matching subscribers.');

            return self::SUCCESS;
        }

        $this->info('Restoring network for '.$candidates->count().' subscriber(s) (active in legacy portal snapshot)…');

        $restored = 0;
        $graced = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($candidates->count());
        $bar->start();

        foreach ($candidates as $customer) {
            try {
                $result = $this->restoreOne($customer, $dryRun, $graceOverdue);
                if ($result['restored']) {
                    $restored++;
                }
                if ($result['graced']) {
                    $graced++;
                }
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("  {$customer->customer_code}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', 'Count'], [
            ['Candidates (legacy portal active)', $candidates->count()],
            ['Restored ON', $restored],
            ['Line grace (overdue)', $graced],
            ['Failed', $failed],
            ['Still suspended (DB)', Customer::query()->fromLegacyPortal()->where('network_access_state', 'suspended')->count()],
        ]);

        if ($dryRun) {
            $this->warn('Dry run — no MikroTik push.');
        } else {
            $this->info('Run: php artisan isp:fix-stale-network — to catch any remaining disabled PPP secrets.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{restored: bool, graced: bool}
     */
    private function restoreOne(Customer $customer, bool $dryRun, bool $graceOverdue): array
    {
        $raw = is_array($customer->meta['legacy_portal_raw'] ?? null) ? $customer->meta['legacy_portal_raw'] : [];
        $patch = [
            'status' => CustomerStatus::ACTIVE,
            'network_access_state' => 'active',
        ];

        $expires = $this->resolveExpiresFromRaw($raw);
        if ($expires !== null) {
            $patch['service_expires_at'] = $expires->toDateString();
        }

        $openDue = $customer->openInvoiceBalance();
        $graced = false;

        if ($graceOverdue && $openDue > 0.01 && ! $dryRun) {
            $raw = is_array($customer->meta['legacy_portal_raw'] ?? null) ? $customer->meta['legacy_portal_raw'] : [];
            if ($raw !== []) {
                app(LegacyPortalOverdueEvaluator::class)->syncLineGraceFromBillingRow($customer, $raw);
            } else {
                $meta = is_array($customer->meta) ? $customer->meta : [];
                $billingDay = max(1, min(31, (int) ($customer->billing_day ?? 1)));
                $until = now()->day(min($billingDay, (int) now()->daysInMonth()))->startOfDay();
                if ($until->lt(now()->startOfDay())) {
                    $until = now()->endOfMonth()->startOfDay();
                }
                $meta['line_grace_until'] = $until->toDateString();
                $meta['line_grace_restored_at'] = now()->toIso8601String();
                $patch['meta'] = $meta;
            }
            $graced = CustomerLineGraceService::hasActiveLineGrace($customer->fresh() ?? $customer);
        }

        if ($dryRun) {
            return ['restored' => true, 'graced' => $graced];
        }

        $customer->forceFill($patch)->saveQuietly();
        $customer = $customer->fresh() ?? $customer;

        CustomerNetworkSync::pushMikrotikEnableNow($customer);

        return ['restored' => true, 'graced' => $graced];
    }

    private function shouldRestore(Customer $customer, bool $includeNoSnapshot): bool
    {
        if ($this->wasExplicitlyOffInLegacyPortal($customer)) {
            return false;
        }

        if ($this->wasActiveInLegacyPortal($customer)) {
            return true;
        }

        if (! $includeNoSnapshot) {
            return false;
        }

        return CustomerStatus::normalize((string) $customer->status) === CustomerStatus::ACTIVE
            && ($customer->network_access_state ?? 'active') === 'suspended';
    }

    private function wasExplicitlyOffInLegacyPortal(Customer $customer): bool
    {
        $raw = is_array($customer->meta['legacy_portal_raw'] ?? null) ? $customer->meta['legacy_portal_raw'] : [];
        if ($raw === []) {
            return false;
        }

        if (filter_var($raw['Disabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $short = strtolower((string) ($raw['ShortStatus'] ?? ''));
        $status = strtolower((string) ($raw['Status'] ?? ''));

        return in_array($short, ['inactive', 'suspended', 'off', 'costfree'], true)
            || str_contains($status, 'inactive')
            || str_contains($status, 'suspend');
    }

    private function wasActiveInLegacyPortal(Customer $customer): bool
    {
        $raw = is_array($customer->meta['legacy_portal_raw'] ?? null) ? $customer->meta['legacy_portal_raw'] : [];
        if ($raw === []) {
            return false;
        }

        if (filter_var($raw['Disabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $status = strtolower((string) ($raw['Status'] ?? ''));
        $short = strtolower((string) ($raw['ShortStatus'] ?? ''));

        if (str_contains($status, 'suspend') || str_contains($status, 'inactive')
            || in_array($short, ['suspended', 'off', 'expired', 'inactive'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function resolveExpiresFromRaw(array $raw): ?Carbon
    {
        $effective = trim((string) ($raw['EffectiveTo'] ?? ''));
        if ($effective !== '') {
            try {
                return Carbon::parse($effective);
            } catch (Throwable) {
                // fall through
            }
        }

        $expireDay = (int) preg_replace('/\D+/', '', (string) ($raw['BillingLastDate'] ?? ''));
        if ($expireDay >= 1 && $expireDay <= 31) {
            return Carbon::parse(BillingDefaults::dateFromExpireDay($expireDay));
        }

        return null;
    }
}
