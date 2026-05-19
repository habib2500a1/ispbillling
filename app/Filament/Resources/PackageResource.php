<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageResource\Pages;
use App\Filament\Resources\PackageResource\RelationManagers;
use App\Models\Package;
use App\Support\BillingCycleType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Section::make('MikroTik link')
                    ->description('PPP profile সিঙ্ক হলে এখানে সার্ভার ও প্রোফাইল নাম বসে; কাস্টমার অ্যাসাইনমেন্ট একই প্যাকেজ লিস্ট থেকে।')
                    ->schema([
                        Forms\Components\Select::make('mikrotik_server_id')
                            ->label('MikroTik server')
                            ->relationship('mikrotikServer', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\TextInput::make('mikrotik_profile_name')
                            ->label('RouterOS PPP profile name')
                            ->maxLength(128)
                            ->helperText('সিঙ্ক বাটন দিলে অটো ভরে; হাতে লিঙ্ক করতে পারেন।'),
                        Forms\Components\Placeholder::make('mikrotik_synced_display')
                            ->label('Last MikroTik sync')
                            ->content(fn (?Package $record): string => $record?->mikrotik_synced_at !== null
                                ? $record->mikrotik_synced_at->timezone(config('app.timezone'))->format('Y-m-d H:i')
                                : '—')
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Forms\Components\Section::make('BTRC / regulatory display')
                    ->description('ইনভয়েস বা রিপোর্টে দেখানোর জন্য লেবেল ও ব্যান্ডউইথ লাইন (ম্যানুয়াল বা MikroTik সিঙ্ক থেকে)।')
                    ->schema([
                        Forms\Components\TextInput::make('btrc_label')
                            ->label('BTRC package name / label')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('btrc_bandwidth')
                            ->label('BTRC bandwidth line')
                            ->maxLength(128)
                            ->helperText('যেমন: ৫০ Mbps symmetric বা ৪০↓ / ৪০↑ Mbps'),
                        Forms\Components\Textarea::make('btrc_notes')
                            ->label('BTRC notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Forms\Components\Select::make('type')
                    ->options([
                        'residential' => 'Residential',
                        'business' => 'Business',
                        'corporate' => 'Corporate',
                        'ftth' => 'FTTH',
                        'hotspot' => 'Hotspot',
                        'p2p' => 'P2P',
                    ])
                    ->required()
                    ->default('residential'),
                Forms\Components\Select::make('pricing_model')
                    ->options([
                        'speed' => 'Speed-based',
                        'volume' => 'Volume (GB)',
                        'time' => 'Time quota',
                        'hybrid' => 'Hybrid',
                    ])
                    ->required()
                    ->default('speed'),
                Forms\Components\TextInput::make('download_mbps')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('upload_mbps')
                    ->numeric(),
                Forms\Components\TextInput::make('included_data_gb')
                    ->label('Included data (GB / day)')
                    ->numeric()
                    ->helperText('Daily FUP quota. Overage billed on invoice when exceeded.'),
                Forms\Components\TextInput::make('overage_price_per_gb')
                    ->label('Overage price (BDT/GB)')
                    ->numeric()
                    ->helperText('Leave empty to use default from billing config.'),
                Forms\Components\TextInput::make('time_quota_hours')
                    ->label('Time quota (hours / month)')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('price_monthly')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('setup_fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('vat_percent')
                    ->label('VAT %')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('sd_percent')
                    ->label('Supplementary duty (SD) %')
                    ->numeric()
                    ->default(0)
                    ->helperText('Applied on amount after manual + coupon discounts (excludes VAT base).'),
                Forms\Components\TextInput::make('withholding_percent')
                    ->label('Withholding / AIT % (reporting)')
                    ->numeric()
                    ->default(0)
                    ->helperText('Stored on invoice for NBR-style reports; not added to customer payable total.'),
                Forms\Components\TextInput::make('billing_cycle_days')
                    ->required()
                    ->numeric()
                    ->default(30)
                    ->helperText('Fallback length when cycle type uses days.'),
                Forms\Components\Select::make('billing_cycle_type')
                    ->label('Billing cycle')
                    ->options(BillingCycleType::options())
                    ->required()
                    ->default(BillingCycleType::MONTHLY)
                    ->native(false)
                    ->helperText('Hourly/daily: price_monthly is scaled by billing_cycle_days (default 30).'),
                Forms\Components\DatePicker::make('promo_starts_at'),
                Forms\Components\DatePicker::make('promo_ends_at'),
                Forms\Components\TextInput::make('promo_price_monthly')
                    ->label('Promotional price (monthly)')
                    ->numeric(),
                Forms\Components\Repeater::make('slab_pricing')
                    ->label('Speed slab pricing')
                    ->schema([
                        Forms\Components\TextInput::make('upto_mbps')
                            ->label('Up to (Mbps)')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Price / month')
                            ->numeric()
                            ->required(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->columnSpanFull()
                    ->helperText('Optional tiered pricing; full automation can be wired later.'),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\Toggle::make('show_on_website')
                    ->label('Show on website')
                    ->default(false)
                    ->helperText('On = visible on landing page, signup, and customer portal. Off = hidden from customers (admin can still assign). Package upgrades require bill payment.'),
                Forms\Components\Toggle::make('is_ott')
                    ->label('OTT add-on (VAS)')
                    ->helperText('Mark as OTT subscription package for value-added service billing.'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('btrc_label')
                    ->label('BTRC name')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('btrc_bandwidth')
                    ->label('BTRC bandwidth')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('mikrotik_profile_name')
                    ->label('MT profile')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('mikrotikServer.name')
                    ->label('MikroTik')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('mikrotik_synced_at')
                    ->label('MT sync')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('pricing_model')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('download_mbps')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('upload_mbps')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('price_monthly')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('billing_cycle_type')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vat_percent')
                    ->label('VAT %')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sd_percent')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('show_on_website')
                    ->label('Public')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AddonsRelationManager::class,
            RelationManagers\AreaPricesRelationManager::class,
            RelationManagers\ZonePricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'edit' => Pages\EditPackage::route('/{record}/edit'),
        ];
    }
}
