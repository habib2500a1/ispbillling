<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Billing\BillingAccountListCounts;
use App\Support\CustomerAccountScopes;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use App\Support\TenantResolver;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ListVipCustomers extends ListFilteredCustomers
{
    /** @var array{total: int, with_due: int, total_due: float}|null */
    private ?array $memoizedVipStats = null;

    protected static ?string $navigationLabel = 'VIP clients';

    protected static ?string $title = 'VIP subscribers';

    public static function getNavigationLabel(): string
    {
        return 'VIP clients';
    }

    public function getSubheading(): ?string
    {
        return 'VIP lines stay on without auto disconnect — balance due still shows for collection.';
    }

    public function getDirectoryPageVariant(): ?string
    {
        return 'vip';
    }

    public function table(Table $table): Table
    {
        return CustomerResource::clientsDirectoryTable($table, 'vip');
    }

    /**
     * @return list<array{label: string, value: string, hint: string, tone: string, icon: string}>
     */
    public function getStatCards(): array
    {
        $stats = $this->getVipStats();

        return [
            [
                'label' => 'VIP clients',
                'value' => number_format($stats['total']),
                'hint' => 'No auto line off',
                'tone' => 'violet',
                'icon' => 'heroicon-o-star',
                'url' => CustomerResource::getUrl('vip'),
            ],
            [
                'label' => 'VIP with due',
                'value' => number_format($stats['with_due']),
                'hint' => 'Outstanding balance',
                'tone' => 'rose',
                'icon' => 'heroicon-o-exclamation-circle',
                'url' => CustomerResource::getUrl('due'),
            ],
            [
                'label' => 'VIP total due',
                'value' => 'BDT '.number_format($stats['total_due'], 2),
                'hint' => 'Collectible from VIP',
                'tone' => 'amber',
                'icon' => 'heroicon-o-banknotes',
                'url' => CustomerResource::getUrl('due'),
            ],
            [
                'label' => 'All due (tenant)',
                'value' => 'BDT '.number_format($this->getDirectoryStats()['total_due'] ?? 0, 2),
                'hint' => 'Every client segment',
                'tone' => 'sky',
                'icon' => 'heroicon-o-calculator',
                'url' => CustomerResource::getUrl('due'),
            ],
        ];
    }

    /**
     * @return array{total: int, with_due: int, total_due: float}
     */
    private function getVipStats(): array
    {
        if ($this->memoizedVipStats !== null) {
            return $this->memoizedVipStats;
        }

        $tenantId = TenantResolver::requiredTenantId();

        return $this->memoizedVipStats = Cache::remember(
            'clients_directory_vip_stats:'.$tenantId,
            120,
            function () use ($tenantId): array {
                $total = app(BillingAccountListCounts::class)->get('vip');

                $withDue = CustomerAccountScopes::applyWithBalanceDue(
                    Customer::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('subscriber_type', SubscriberType::VIP)
                        ->where('status', '!=', CustomerStatus::TERMINATED),
                    $tenantId,
                )->count();

                $totalDue = 0.0;
                $sumQuery = Customer::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('subscriber_type', SubscriberType::VIP)
                    ->where('status', '!=', CustomerStatus::TERMINATED);

                CustomerBalanceDue::augmentTableQuery($sumQuery);
                $sumQuery->orderBy('id')->chunkById(150, function ($chunk) use (&$totalDue): void {
                    foreach ($chunk as $customer) {
                        $totalDue += CustomerBalanceDue::displayAmount($customer);
                    }
                });

                return [
                    'total' => $total,
                    'with_due' => $withDue,
                    'total_due' => round($totalDue, 2),
                ];
            },
        );
    }

    protected function applyFilter(Builder $query): Builder
    {
        return $query->where('subscriber_type', SubscriberType::VIP)
            ->where('status', '!=', CustomerStatus::TERMINATED);
    }
}
