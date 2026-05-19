<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResellerResource\Pages;
use App\Filament\Resources\ResellerResource\RelationManagers;
use App\Models\Reseller;
use App\Models\User;
use App\Support\ResellerType;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ResellerResource extends Resource
{
    protected static ?string $model = Reseller::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Resellers';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Reseller account')
                    ->description('Core identity and hierarchy for this partner.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->maxLength(64)
                            ->helperText('Leave blank to auto-generate.'),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_id_prefix')
                            ->label('Client ID prefix')
                            ->maxLength(32)
                            ->placeholder('e.g. ABC')
                            ->helperText('Prefix for subscriber IDs created under this reseller.'),
                        Forms\Components\Select::make('franchise_type')
                            ->label('Partner type')
                            ->options(ResellerType::labels())
                            ->default(ResellerType::RESELLER)
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('is_active')
                            ->label('Status')
                            ->options(['1' => 'Active', '0' => 'Inactive'])
                            ->default('1')
                            ->required()
                            ->dehydrateStateUsing(fn (string $state): bool => $state === '1')
                            ->formatStateUsing(fn (?bool $state): string => ($state ?? true) ? '1' : '0'),
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent reseller')
                            ->options(fn (): array => Reseller::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->placeholder('Top-level reseller')
                            ->helperText('Select only if creating a sub-reseller.')
                            ->nullable(),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Contact & address')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                        Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
                        Forms\Components\TextInput::make('contact_person')->label('Contact person')->maxLength(255),
                        Forms\Components\TextInput::make('address')->maxLength(255)->columnSpanFull(),
                        Forms\Components\TextInput::make('city')->maxLength(64),
                        Forms\Components\TextInput::make('district')->maxLength(64),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Commission & wallet')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\Select::make('commission_type')
                            ->options(['percent' => 'Percentage', 'fixed' => 'Fixed amount'])
                            ->default('percent')
                            ->required()
                            ->native(false)
                            ->live(),
                        Forms\Components\TextInput::make('commission_value')
                            ->label(fn (Get $get): string => $get('commission_type') === 'fixed' ? 'Fixed commission (BDT)' : 'Default commission %')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->suffix(fn (Get $get): ?string => $get('commission_type') === 'fixed' ? 'BDT' : '%'),
                        Forms\Components\TextInput::make('revenue_share_percent')
                            ->label('Parent revenue share %')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Get $get, ?Reseller $record): bool => filled($get('parent_id')) || $record?->parent_id !== null),
                        Forms\Components\TextInput::make('opening_balance')
                            ->label('Opening balance')
                            ->numeric()
                            ->default(0)
                            ->prefix('BDT')
                            ->visibleOn('create')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('wallet_balance')
                            ->label('Wallet balance')
                            ->numeric()
                            ->disabled()
                            ->visibleOn('edit'),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Portal login')
                    ->description('Credentials for /reseller/login partner portal.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Forms\Components\TextInput::make('portal_login')
                            ->label('Reseller user ID')
                            ->maxLength(64)
                            ->helperText('Login username. Defaults to partner code if empty.'),
                        Forms\Components\TextInput::make('portal_password')
                            ->label('Login password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make((string) $state) : null)
                            ->helperText('Leave blank when editing to keep the current password.'),
                        Forms\Components\Select::make('primary_user_id')
                            ->label('Primary user')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->placeholder('Select staff user')
                            ->helperText('Optional HQ staff owner for this partner account.'),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Documents')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\TextInput::make('trade_license')->label('Trade license no.')->maxLength(64),
                        Forms\Components\TextInput::make('nid_number')->label('NID / representative ID')->maxLength(32),
                        Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(),
                Forms\Components\Section::make('White-label branding')
                    ->schema([
                        Forms\Components\Toggle::make('white_label_enabled')
                            ->label('Enable white-label')
                            ->live(),
                        Forms\Components\TextInput::make('brand_name')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => (bool) $get('white_label_enabled')),
                        Forms\Components\TextInput::make('portal_subdomain')
                            ->label('Portal subdomain')
                            ->placeholder('partner1')
                            ->visible(fn (Get $get): bool => (bool) $get('white_label_enabled')),
                        Forms\Components\ColorPicker::make('brand_primary_color')
                            ->label('Brand color')
                            ->visible(fn (Get $get): bool => (bool) $get('white_label_enabled')),
                        Forms\Components\FileUpload::make('brand_logo_path')
                            ->label('Logo')
                            ->image()
                            ->directory('reseller-brands')
                            ->visible(fn (Get $get): bool => (bool) $get('white_label_enabled')),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('code')->fontFamily('mono'),
                        Infolists\Components\TextEntry::make('client_id_prefix')->label('Client prefix')->placeholder('—'),
                        Infolists\Components\TextEntry::make('franchise_type')->formatStateUsing(fn (string $s): string => ResellerType::labels()[$s] ?? $s)->badge(),
                        Infolists\Components\TextEntry::make('parent.name')->label('Parent')->placeholder('—'),
                        Infolists\Components\IconEntry::make('is_active')->boolean()->label('Active'),
                        Infolists\Components\TextEntry::make('wallet_balance')->money('BDT'),
                        Infolists\Components\TextEntry::make('commission_value')
                            ->label('Commission')
                            ->formatStateUsing(fn (Reseller $record): string => $record->commissionLabel()),
                        Infolists\Components\TextEntry::make('portal_login')->label('Portal login')->formatStateUsing(fn (Reseller $r): string => $r->portalLoginId()),
                        Infolists\Components\TextEntry::make('primaryUser.name')->label('Primary user')->placeholder('—'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Contact')
                    ->schema([
                        Infolists\Components\TextEntry::make('email')->placeholder('—'),
                        Infolists\Components\TextEntry::make('phone')->placeholder('—'),
                        Infolists\Components\TextEntry::make('address')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->fontFamily('mono')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('client_id_prefix')->label('Prefix')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('franchise_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $s): string => ResellerType::labels()[$s] ?? $s)
                    ->badge(),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent')->placeholder('—'),
                Tables\Columns\TextColumn::make('customers_count')->counts('customers')->label('Customers'),
                Tables\Columns\TextColumn::make('commission_value')
                    ->label('Commission')
                    ->formatStateUsing(fn (Reseller $r): string => $r->commissionLabel()),
                Tables\Columns\TextColumn::make('wallet_balance')->money('BDT')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('franchise_type')->options(ResellerType::labels()),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            RelationManagers\ChildrenRelationManager::class,
            RelationManagers\TerritoriesRelationManager::class,
            RelationManagers\CustomersRelationManager::class,
            RelationManagers\CommissionsRelationManager::class,
            RelationManagers\BalanceTransfersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResellers::route('/'),
            'create' => Pages\CreateReseller::route('/create'),
            'view' => Pages\ViewReseller::route('/{record}'),
            'edit' => Pages\EditReseller::route('/{record}/edit'),
        ];
    }
}
