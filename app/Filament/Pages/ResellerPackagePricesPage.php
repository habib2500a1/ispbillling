<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PackageResource;
use App\Models\Package;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ResellerPackagePricesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string $view = 'filament.pages.reseller-package-prices';

    protected static ?string $navigationLabel = 'Package prices';

    protected static ?string $title = 'Package prices';

    protected static ?string $slug = 'reseller-package-prices';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return PackageResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Package::query()->withCount(['areaPrices', 'zonePrices']))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('download_mbps')
                    ->label('Speed')
                    ->formatStateUsing(fn (Package $r): string => $r->download_mbps.' Mbps'),
                Tables\Columns\TextColumn::make('price_monthly')
                    ->label('Base price')
                    ->money('BDT')
                    ->sortable(),
                Tables\Columns\TextColumn::make('area_prices_count')
                    ->label('Area overrides')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('zone_prices_count')
                    ->label('Zone overrides')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_prices')
                    ->label('Edit pricing')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Package $record): string => PackageResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('No packages')
            ->emptyStateDescription('Create internet packages under Network → Packages first.');
    }
}
