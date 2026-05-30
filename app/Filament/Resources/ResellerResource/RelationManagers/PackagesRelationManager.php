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

    protected static ?string $title = 'Packages & selling price';

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
                    ->live()
                    ->afterStateUpdated(function (Get $get, Forms\Set $set): void {
                        $packageId = $get('package_id');
                        if (! $packageId) {
                            return;
                        }
                        $base = Package::query()->find($packageId)?->price_monthly;
                        if ($base !== null && blank($get('selling_price'))) {
                            $set('selling_price', $base);
                        }
                    }),
                Forms\Components\TextInput::make('selling_price')
                    ->label('Selling price (BDT)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('BDT')
                    ->helperText('Price this reseller charges subscribers for this package.'),
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
                    ->label('Base price')
                    ->money('BDT'),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Reseller selling price')
                    ->money('BDT')
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

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Remove'),
            ])
            ->emptyStateHeading('No packages assigned')
            ->emptyStateDescription('Assign packages and set each reseller’s selling price. Until you assign any package, this reseller sees all active packages.');
    }
}
