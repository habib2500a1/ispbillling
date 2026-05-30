<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Models\ResellerPackage;
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

    protected static ?string $navigationLabel = 'Reseller packages';

    protected static ?string $title = 'Reseller packages & selling price';

    protected static ?string $slug = 'reseller-package-prices';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return ResellerResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ResellerPackage::query()
                    ->with(['reseller:id,name,code', 'package:id,name,price_monthly,download_mbps'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('reseller.code')
                    ->label('Reseller code')
                    ->searchable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('reseller.name')
                    ->label('Reseller')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('package.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('package.mikrotik_profile_name')
                    ->label('Profile code')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('package.price_monthly')
                    ->label('Base price')
                    ->money('BDT'),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Selling price')
                    ->money('BDT')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reseller_id')
                    ->label('Reseller')
                    ->relationship('reseller', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('manage')
                    ->label('Edit on reseller')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (ResellerPackage $record): string => ResellerResource::getUrl('view', [
                        'record' => $record->reseller_id,
                    ]).'#relation-manager-packages'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('assign')
                    ->label('Assign packages')
                    ->icon('heroicon-o-plus')
                    ->url(ResellerResource::getUrl())
                    ->color('primary'),
            ])
            ->emptyStateHeading('No reseller packages yet')
            ->emptyStateDescription('Open a reseller → Packages & selling price tab to assign packages and set selling prices.');
    }
}
