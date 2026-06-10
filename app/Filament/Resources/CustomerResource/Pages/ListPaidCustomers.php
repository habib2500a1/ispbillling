<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Support\CustomerAccountScopes;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListPaidCustomers extends ListFilteredCustomers
{
    protected static ?string $navigationLabel = 'Paid clients';

    protected static ?string $title = 'Paid clients';

    public static function getNavigationLabel(): string
    {
        return 'Paid clients';
    }

    public function getSubheading(): ?string
    {
        return 'Clients with no outstanding bill — current due is zero.';
    }

    public function getDirectoryPageVariant(): ?string
    {
        return 'paid';
    }

    public function table(Table $table): Table
    {
        return CustomerResource::clientsDirectoryTable($table, 'paid');
    }

    /**
     * @return list<array{label: string, value: string, hint: string, tone: string, icon: string, url?: string}>
     */
    public function getStatCards(): array
    {
        $stats = $this->getDirectoryStats();
        $paidCount = max(0, (int) ($stats['paid_clients'] ?? 0));
        $dueCount = max(0, (int) ($stats['due_clients'] ?? 0));

        return [
            [
                'label' => 'Paid clients',
                'value' => number_format($paidCount),
                'hint' => 'No current due',
                'tone' => 'emerald',
                'icon' => 'heroicon-o-check-badge',
                'url' => CustomerResource::getUrl('paid'),
            ],
            [
                'label' => 'Current due',
                'value' => number_format($dueCount),
                'hint' => 'Need collection',
                'tone' => 'rose',
                'icon' => 'heroicon-o-exclamation-circle',
                'url' => CustomerResource::getUrl('due'),
            ],
            [
                'label' => 'Total due',
                'value' => 'BDT '.number_format((float) ($stats['total_due'] ?? 0), 2),
                'hint' => 'Outstanding balance',
                'tone' => 'amber',
                'icon' => 'heroicon-o-banknotes',
                'url' => \App\Filament\Pages\BillCollectionDesk::getUrl(),
            ],
            [
                'label' => 'Active clients',
                'value' => number_format((int) ($stats['active'] ?? 0)),
                'hint' => 'In good standing',
                'tone' => 'sky',
                'icon' => 'heroicon-o-user-group',
                'url' => CustomerResource::getUrl('active'),
            ],
        ];
    }

    protected function applyFilter(Builder $query): Builder
    {
        return CustomerAccountScopes::applyPaidUp($query);
    }
}
