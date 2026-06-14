<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LegacyPortalMirrorRun;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Reseller;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class LegacyPortalSyncStatusPage extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static string $view = 'filament.pages.legacy-portal-sync-status';

    protected static ?string $navigationLabel = 'Legacy portal sync';

    protected static ?string $title = 'Legacy portal sync status';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $slug = 'legacy-portal-sync-status';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canAccessModuleGroup('System');
    }

    /**
     * @return array<string, mixed>
     */
    public function latestRun(): array
    {
        $run = LegacyPortalMirrorRun::query()->latest('id')->first();
        if (! $run instanceof LegacyPortalMirrorRun) {
            return [
                'uuid' => 'No raw mirror run yet',
                'status' => 'not_started',
                'started_at' => null,
                'finished_at' => null,
                'summary' => [],
            ];
        }

        return [
            'uuid' => $run->run_uuid,
            'status' => $run->status,
            'started_at' => $run->started_at?->format('Y-m-d H:i:s'),
            'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
            'summary' => $run->summary ?? [],
        ];
    }

    /**
     * @return list<array{label: string, value: int|string}>
     */
    public function localCounts(): array
    {
        return [
            ['label' => 'Subscribers imported', 'value' => Customer::query()->fromLegacyPortal()->count()],
            ['label' => 'Details synced', 'value' => Customer::query()->fromLegacyPortal()->whereNotNull('meta->legacy_portal_details_synced_at')->count()],
            ['label' => 'ONU/network meta', 'value' => Customer::query()->fromLegacyPortal()->where(function ($q): void {
                $q->whereNotNull('meta->onu_mac')
                    ->orWhereNotNull('meta->legacy_portal_network')
                    ->orWhereNotNull('meta->mac_binding')
                    ->orWhereNotNull('meta->epon_port');
            })->count()],
            ['label' => 'Payments imported', 'value' => Payment::query()->where('meta->import_source', 'legacy_portal')->count()],
            ['label' => 'Invoices', 'value' => Invoice::query()->count()],
            ['label' => 'SMS logs imported', 'value' => NotificationLog::query()->where('meta->import_source', 'legacy_portal')->orWhereNotNull('meta->legacy_portal_sms_log_id')->count()],
            ['label' => 'Resellers imported', 'value' => Reseller::query()->where('meta->import_source', 'legacy_portal')->count()],
            ['label' => 'Employees/staff', 'value' => Employee::query()->count()],
        ];
    }

    /**
     * @return list<array{domain: string, records: int, status: string}>
     */
    public function mirrorCoverage(): array
    {
        $run = LegacyPortalMirrorRun::query()->latest('id')->first();
        $counts = $run instanceof LegacyPortalMirrorRun
            ? $run->records()
                ->selectRaw('domain, count(*) as aggregate')
                ->groupBy('domain')
                ->pluck('aggregate', 'domain')
                ->map(fn ($v): int => (int) $v)
                ->all()
            : [];

        return collect([
            'customers',
            'billing_grid',
            'service_invoices',
            'employees',
            'application_users',
            'mac_resellers',
            'payment_history',
            'sms_history',
            'customer_details_html',
            'tickets',
            'zones',
            'olt_onu',
        ])->map(fn (string $domain): array => [
            'domain' => $domain,
            'records' => $counts[$domain] ?? 0,
            'status' => ($counts[$domain] ?? 0) > 0 ? 'captured' : 'missing/not probed',
        ])->all();
    }

    /**
     * @return list<array{domain: string, keys: string}>
     */
    public function rawFieldSummary(): array
    {
        $run = LegacyPortalMirrorRun::query()->latest('id')->first();
        if (! $run instanceof LegacyPortalMirrorRun) {
            return [];
        }

        return $run->records()
            ->whereNotNull('payload_json')
            ->orderBy('domain')
            ->limit(30)
            ->get()
            ->map(function ($record): array {
                $payload = $record->payload_json ?? [];
                $rows = $payload['aaData'] ?? $payload['data'] ?? null;
                $first = is_array($rows) ? ($rows[0] ?? []) : $payload;
                $keys = is_array($first) ? array_keys($first) : [];

                return [
                    'domain' => (string) $record->domain,
                    'keys' => implode(', ', array_slice($keys, 0, 20)) ?: '(no tabular keys)',
                ];
            })
            ->unique(fn (array $row): string => $row['domain'].':'.$row['keys'])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function syncSettings(): array
    {
        return [
            ['label' => 'Base URL', 'value' => (string) config('legacy_portal.base_url')],
            ['label' => 'Daily sync', 'value' => (bool) config('legacy_portal.daily_sync_enabled', true) ? 'enabled' : 'disabled'],
            ['label' => 'Daily sync time', 'value' => (string) config('legacy_portal.daily_sync_at', '02:30')],
            ['label' => 'Collections interval', 'value' => (string) config('legacy_portal.collections_sync_every_minutes', 15).' minute(s)'],
            ['label' => 'Raw mirror schedule', 'value' => (bool) config('legacy_portal.raw_mirror_enabled', false) ? 'enabled' : 'disabled'],
            ['label' => 'Raw mirror time', 'value' => (string) config('legacy_portal.raw_mirror_at', '01:15')],
        ];
    }
}
