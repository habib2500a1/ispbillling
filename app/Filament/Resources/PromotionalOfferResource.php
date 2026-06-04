<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionalOfferResource\Pages;
use App\Models\Package;
use App\Models\PromotionalOffer;
use App\Support\TenantResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionalOfferResource extends Resource
{
    protected static ?string $model = PromotionalOffer::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Offers & promotions';

    protected static ?string $slug = 'promotional-offers';

    protected static ?int $navigationSort = 9;

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->hasRole('super-admin') || $u->hasRole('isp-admin') || $u->hasRole('isp-manager'));
    }

    public static function form(Form $form): Form
    {
        $tenantId = TenantResolver::requiredTenantId();

        return $form->schema([
            Forms\Components\Section::make('Offer details')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(120),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                Forms\Components\Select::make('discount_type')
                    ->options([
                        PromotionalOffer::TYPE_PERCENT => 'Percentage off',
                        PromotionalOffer::TYPE_FIXED => 'Fixed BDT off',
                    ])
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('discount_value')
                    ->label('Discount value')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
            Forms\Components\Section::make('Eligibility')->schema([
                Forms\Components\Select::make('package_ids')
                    ->label('Packages (empty = all)')
                    ->multiple()
                    ->options(fn (): array => Package::query()
                        ->where('tenant_id', $tenantId)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                Forms\Components\DatePicker::make('valid_from'),
                Forms\Components\DatePicker::make('valid_to'),
                Forms\Components\TextInput::make('max_redemptions')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Leave empty for unlimited.'),
                Forms\Components\Textarea::make('terms')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('discount_type')->badge(),
                Tables\Columns\TextColumn::make('discount_value')->label('Value'),
                Tables\Columns\TextColumn::make('redemptions_count')->label('Used'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('valid_from')->date(),
                Tables\Columns\TextColumn::make('valid_to')->date(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotionalOffers::route('/'),
            'create' => Pages\CreatePromotionalOffer::route('/create'),
            'edit' => Pages\EditPromotionalOffer::route('/{record}/edit'),
        ];
    }
}
