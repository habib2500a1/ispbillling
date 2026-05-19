<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResellerResource\Pages;
use App\Filament\Resources\ResellerResource\RelationManagers;
use App\Models\Reseller;
use App\Support\ResellerType;
use Filament\Forms;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
                Forms\Components\Section::make('Partner profile')
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent (sub-reseller of)')
                            ->options(fn (): array => Reseller::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->nullable(),
                        Forms\Components\Select::make('franchise_type')
                            ->label('Partner type')
                            ->options(ResellerType::labels())
                            ->default(ResellerType::RESELLER)
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->maxLength(64)
                            ->helperText('Leave blank for auto-generated code.'),
                        Forms\Components\TextInput::make('contact_person')->maxLength(255),
                        Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                        Forms\Components\Toggle::make('is_active')->default(true),
                        Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Commission & revenue share')
                    ->schema([
                        Forms\Components\Select::make('commission_type')
                            ->options(['percent' => 'Percentage of payment', 'fixed' => 'Fixed per payment'])
                            ->default('percent')
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('commission_value')
                            ->label('Commission value')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('revenue_share_percent')
                            ->label('Parent revenue share %')
                            ->helperText('Share of this partner\'s commission paid to parent franchise.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100),
                        Forms\Components\TextInput::make('wallet_balance')
                            ->label('Wallet balance')
                            ->numeric()
                            ->default(0)
                            ->disabledOn('create'),
                    ])
                    ->columns(2),
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
                Forms\Components\Section::make('Reseller portal')
                    ->schema([
                        Forms\Components\TextInput::make('portal_password')
                            ->label('Portal password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Leave blank to keep unchanged. Login at /reseller/login with code, email, or phone.')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make((string) $state) : null),
                    ])
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
                        Infolists\Components\TextEntry::make('franchise_type')->formatStateUsing(fn (string $s): string => ResellerType::labels()[$s] ?? $s)->badge(),
                        Infolists\Components\TextEntry::make('parent.name')->label('Parent')->placeholder('—'),
                        Infolists\Components\IconEntry::make('is_active')->boolean()->label('Active'),
                        Infolists\Components\TextEntry::make('wallet_balance')->money('BDT'),
                        Infolists\Components\TextEntry::make('commission_value')
                            ->label('Commission')
                            ->formatStateUsing(fn (Reseller $record): string => $record->commissionLabel()),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('White-label')
                    ->schema([
                        Infolists\Components\IconEntry::make('white_label_enabled')->boolean(),
                        Infolists\Components\TextEntry::make('brand_name')->placeholder('—'),
                        Infolists\Components\TextEntry::make('portal_subdomain')->placeholder('—'),
                    ])
                    ->columns(3)
                    ->visible(fn (Reseller $r): bool => $r->white_label_enabled),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->fontFamily('mono')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('franchise_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $s): string => ResellerType::labels()[$s] ?? $s)
                    ->badge(),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent')->placeholder('—'),
                Tables\Columns\TextColumn::make('customers_count')->counts('customers')->label('Customers'),
                Tables\Columns\TextColumn::make('children_count')->counts('children')->label('Sub-resellers'),
                Tables\Columns\TextColumn::make('commission_value')
                    ->label('Commission')
                    ->formatStateUsing(fn (Reseller $r): string => $r->commissionLabel()),
                Tables\Columns\TextColumn::make('wallet_balance')->money('BDT')->sortable(),
                Tables\Columns\IconColumn::make('white_label_enabled')->boolean()->label('WL'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('franchise_type')->options(ResellerType::labels()),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TernaryFilter::make('white_label_enabled')->label('White-label'),
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
