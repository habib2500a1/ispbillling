<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 8;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Coupon / promo';

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->hasRole('super-admin') || $u->hasRole('isp-admin'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Promo code')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(64)
                            ->unique(
                                table: 'coupons',
                                column: 'code',
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule): Unique {
                                    $tid = (int) (auth()->user()?->tenant_id ?? 1);

                                    return $rule->where('tenant_id', $tid);
                                },
                            )
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper(trim($state)) : $state)
                            ->placeholder('SUMMER2026'),
                        Forms\Components\Select::make('discount_type')
                            ->options([
                                Coupon::TYPE_PERCENT => 'Percentage off subtotal',
                                Coupon::TYPE_FIXED_AMOUNT => 'Fixed BDT off',
                                Coupon::TYPE_FIRST_MONTH_PERCENT => 'First invoice % off',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('value')
                            ->label('Value (% or BDT)')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Limits')
                    ->schema([
                        Forms\Components\TextInput::make('max_uses')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Leave empty for unlimited.'),
                        Forms\Components\TextInput::make('min_invoice_amount')
                            ->label('Minimum invoice (BDT)')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\DatePicker::make('valid_from'),
                        Forms\Components\DatePicker::make('valid_to'),
                        Forms\Components\Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('discount_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Coupon::TYPE_PERCENT => '% off',
                        Coupon::TYPE_FIXED_AMOUNT => 'Fixed',
                        Coupon::TYPE_FIRST_MONTH_PERCENT => '1st month %',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(fn (Coupon $record): string => in_array($record->discount_type, [Coupon::TYPE_PERCENT, Coupon::TYPE_FIRST_MONTH_PERCENT], true)
                        ? $record->value.'%'
                        : number_format((float) $record->value, 2).' BDT'),
                Tables\Columns\TextColumn::make('uses_count')
                    ->label('Used')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('max_uses')
                    ->placeholder('∞')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('valid_to')
                    ->date()
                    ->placeholder('No end')
                    ->color(fn (Coupon $record): ?string => $record->valid_to && $record->valid_to->isPast() ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
