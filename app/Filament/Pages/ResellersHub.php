<?php

namespace App\Filament\Pages;

use App\Filament\Pages\ResellerPackagePricesPage;
use App\Filament\Pages\ResellerReportPage;
use App\Filament\Pages\ResellerWalletHubPage;
use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use Filament\Pages\Page;

class ResellersHub extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static string $view = 'filament.pages.resellers-hub';

    protected static ?string $navigationLabel = 'Reseller & franchise';

    protected static ?string $title = 'Reseller & franchise management';

    protected static ?string $navigationGroup = 'Resellers';

    protected static ?int $navigationSort = 1;

    /**
     * @return array<string, int|float>
     */
    public function getStats(): array
    {
        $total = Reseller::query()->count();
        $active = Reseller::query()->where('is_active', true)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => max(0, $total - $active),
            'franchises' => Reseller::query()->where('franchise_type', \App\Support\ResellerType::FRANCHISE)->count(),
            'sub_resellers' => Reseller::query()->where('franchise_type', \App\Support\ResellerType::SUB_RESELLER)->count(),
            'white_label' => Reseller::query()->where('white_label_enabled', true)->count(),
            'customers_total' => (int) \App\Models\Customer::query()->whereNotNull('reseller_id')->count(),
            'wallet_total' => (float) Reseller::query()->sum('wallet_balance'),
            'pending_commission' => (float) ResellerCommission::query()
                ->where('status', ResellerCommission::STATUS_PENDING)
                ->sum('commission_amount'),
        ];
    }

    /**
     * Top partners ranked by linked customer count, with a meter width
     * relative to the busiest partner for the leaderboard bar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopPartners(): array
    {
        $partners = Reseller::query()
            ->withCount('customers')
            ->orderByDesc('customers_count')
            ->orderByDesc('wallet_balance')
            ->limit(6)
            ->get();

        $max = (int) ($partners->max('customers_count') ?: 0);

        return $partners->map(fn (Reseller $reseller): array => [
            'name' => $reseller->name,
            'type' => $reseller->franchiseTypeLabel(),
            'customers' => (int) $reseller->customers_count,
            'wallet' => (float) $reseller->wallet_balance,
            'active' => (bool) $reseller->is_active,
            'url' => ResellerResource::getUrl('view', ['record' => $reseller]),
            'width' => $max > 0 ? (int) round(($reseller->customers_count / $max) * 100) : 0,
        ])->all();
    }

    /**
     * Partner counts grouped by franchise type, each with a share of total.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPartnerMix(): array
    {
        $rows = Reseller::query()
            ->selectRaw('franchise_type, COUNT(*) as aggregate')
            ->groupBy('franchise_type')
            ->pluck('aggregate', 'franchise_type');

        $total = (int) $rows->sum();
        $labels = \App\Support\ResellerType::labels();

        return collect($rows)
            ->map(fn (int $count, ?string $type): array => [
                'label' => $labels[$type] ?? ucfirst((string) ($type ?: 'Reseller')),
                'count' => $count,
                'share' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Commission settlement snapshot: pending / paid / cancelled amounts and
     * counts, plus the pending share of the settle-able total for a meter.
     *
     * @return array<string, int|float>
     */
    public function getSettlement(): array
    {
        $byStatus = ResellerCommission::query()
            ->selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(commission_amount), 0) as amt')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $row = fn (string $status, string $key): float => (float) ($byStatus[$status]->{$key} ?? 0);

        $pending = $row(ResellerCommission::STATUS_PENDING, 'amt');
        $paid = $row(ResellerCommission::STATUS_PAID, 'amt');
        $settleable = $pending + $paid;

        return [
            'pending_amount' => $pending,
            'pending_count' => (int) $row(ResellerCommission::STATUS_PENDING, 'cnt'),
            'paid_amount' => $paid,
            'paid_count' => (int) $row(ResellerCommission::STATUS_PAID, 'cnt'),
            'cancelled_amount' => $row(ResellerCommission::STATUS_CANCELLED, 'amt'),
            'cancelled_count' => (int) $row(ResellerCommission::STATUS_CANCELLED, 'cnt'),
            'pending_share' => $settleable > 0 ? (int) round(($pending / $settleable) * 100) : 0,
        ];
    }

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canResellers();
    }
}
