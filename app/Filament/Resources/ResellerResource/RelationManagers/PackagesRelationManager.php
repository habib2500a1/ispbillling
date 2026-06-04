<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\Package;
use App\Models\ResellerPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'resellerPackages';

    protected static ?string $title = 'Packages & reseller rate';

    protected static ?string $icon = 'heroicon-o-currency-dollar';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('package_id')
                    ->label('Package')
                    ->options(function (): array {
                        $reseller = $this->getOwnerRecord();
                        $assigned = ResellerPackage::query()
                            ->where('reseller_id', $reseller->id)
                            ->pluck('package_id');

                        $query = Package::query()
                            ->where('tenant_id', $reseller->tenant_id)
                            ->where('is_active', true);

                        if ($assigned->isNotEmpty()) {
                            $query->whereNotIn('id', $assigned);
                        }

                        return $query
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Package $package): array => [
                                $package->id => $package->adminSelectLabel(),
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (?ResellerPackage $record): bool => $record !== null)
                    ->dehydrated(fn (?ResellerPackage $record): bool => $record === null)
                    ->live(),
                Forms\Components\Placeholder::make('customer_bill_price')
                    ->label('Customer bill price')
                    ->content(function (Get $get): string {
                        $packageId = $get('package_id');
                        if (! $packageId) {
                            return 'Select a package — subscriber bills use the package list price (e.g. 500 BDT).';
                        }
                        $price = Package::query()->find($packageId)?->price_monthly;

                        return $price !== null
                            ? number_format((float) $price, 2).' BDT / month (auto on subscriber invoice)'
                            : '—';
                    }),
                Forms\Components\TextInput::make('wholesale_price')
                    ->label('Reseller rate (admin)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('BDT')
                    ->helperText('What this reseller pays you per active subscriber for this package (e.g. 200 BDT).'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('package.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('package.mikrotik_profile_name')
                    ->label('Profile code')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('package.download_mbps')
                    ->label('Speed')
                    ->suffix(' Mbps'),
                Tables\Columns\TextColumn::make('package.price_monthly')
                    ->label('Customer bill')
                    ->money('BDT')
                    ->description('Auto on invoice'),
                Tables\Columns\TextColumn::make('wholesale_price')
                    ->label('Reseller rate')
                    ->money('BDT')
                    ->placeholder(fn (ResellerPackage $record): string => $record->selling_price > 0
                        ? number_format((float) $record->selling_price, 2).' (legacy)'
                        : '—')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->defaultSort('package.name')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Assign package')
                    ->mutateFormDataUsing(function (array $data): array {
                        $reseller = $this->getOwnerRecord();
                        $data['tenant_id'] = $reseller->tenant_id;
                        $data['reseller_id'] = $reseller->id;
                        $data['selling_price'] = 0;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Remove'),
            ])
            ->emptyStateHeading('No packages assigned')
            ->emptyStateDescription('Assign packages and set the reseller rate (wholesale). Customer bills always use the package list price.');
    }
}
