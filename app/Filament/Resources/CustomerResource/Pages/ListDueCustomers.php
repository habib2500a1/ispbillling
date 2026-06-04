<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Support\CustomerAccountScopes;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListDueCustomers extends ListFilteredCustomers
{
    protected static ?string $navigationLabel = 'Due clients';

    protected static ?string $title = 'Clients with due balance';

    public static function getNavigationLabel(): string
    {
        return 'Due clients';
    }

    public function getSubheading(): ?string
    {
        return 'Clients with outstanding balance — collect payment, extend line, or open profile.';
    }

    public function getDirectoryPageVariant(): ?string
    {
        return 'due';
    }

    public function table(Table $table): Table
    {
        return CustomerResource::clientsDirectoryTable($table, 'due');
    }

    /**
     * @return list<array{label: string, value: string, hint: string, tone: string, icon: string}>
     */
    public function getStatCards(): array
    {
        $stats = $this->getDirectoryStats();
        $dueCount = max(0, (int) ($stats['due_clients'] ?? 0));
        $totalDue = max(0, (float) ($stats['total_due'] ?? 0));
        $avgDue = $dueCount > 0 ? $totalDue / $dueCount : 0.0;

        return [
            [
                'label' => 'Due clients',
                'value' => number_format($dueCount),
                'hint' => 'Outstanding balance',
                'tone' => 'rose',
                'icon' => 'heroicon-o-exclamation-circle',
            ],
            [
                'label' => 'Total due',
                'value' => 'BDT '.number_format($totalDue, 2),
                'hint' => 'Collectible now',
                'tone' => 'rose',
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => 'Average due',
                'value' => 'BDT '.number_format($avgDue, 2),
                'hint' => 'Per due client',
                'tone' => 'amber',
                'icon' => 'heroicon-o-calculator',
            ],
            [
                'label' => 'Active clients',
                'value' => number_format((int) ($stats['active'] ?? 0)),
                'hint' => 'In good standing',
                'tone' => 'emerald',
                'icon' => 'heroicon-o-check-circle',
            ],
        ];
    }

    protected function applyFilter(Builder $query): Builder
    {
        return CustomerAccountScopes::applyWithBalanceDue($query);
    }
}
