<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HotspotVoucherResource\Pages;
use App\Models\HotspotVoucher;
use App\Models\Package;
use App\Services\Hotspot\HotspotVoucherGenerator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HotspotVoucherResource extends Resource
{
    protected static ?string $model = HotspotVoucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Network';

    protected static ?string $navigationLabel = 'Hotspot vouchers';

    protected static ?int $navigationSort = 12;

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->hasRole('super-admin') || $u->hasRole('isp-admin'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(32),
            Forms\Components\TextInput::make('batch_name')->maxLength(64),
            Forms\Components\TextInput::make('duration_hours')->numeric()->required()->default(24),
            Forms\Components\TextInput::make('data_limit_mb')->numeric()->label('Data limit (MB)'),
            Forms\Components\TextInput::make('price')->numeric()->default(0),
            Forms\Components\Select::make('status')->options([
                HotspotVoucher::STATUS_AVAILABLE => 'Available',
                HotspotVoucher::STATUS_USED => 'Used',
                HotspotVoucher::STATUS_EXPIRED => 'Expired',
                HotspotVoucher::STATUS_REVOKED => 'Revoked',
            ])->required(),
            Forms\Components\Select::make('package_id')->relationship('package', 'name')->searchable(),
            Forms\Components\DateTimePicker::make('expires_at'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('batch_name')->toggleable(),
                Tables\Columns\TextColumn::make('duration_hours')->label('Hours'),
                Tables\Columns\TextColumn::make('price')->money('BDT'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    HotspotVoucher::STATUS_AVAILABLE => 'success',
                    HotspotVoucher::STATUS_USED => 'gray',
                    HotspotVoucher::STATUS_EXPIRED => 'warning',
                    default => 'danger',
                }),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    HotspotVoucher::STATUS_AVAILABLE => 'Available',
                    HotspotVoucher::STATUS_USED => 'Used',
                ]),
                Tables\Filters\Filter::make('batch_name')
                    ->form([Forms\Components\TextInput::make('batch')->label('Batch')])
                    ->query(fn ($query, array $data) => $query->when(filled($data['batch'] ?? null), fn ($q) => $q->where('batch_name', $data['batch']))),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generate_batch')
                    ->label('Generate batch')
                    ->icon('heroicon-o-plus-circle')
                    ->form([
                        Forms\Components\TextInput::make('count')->numeric()->required()->minValue(1)->maxValue(500)->default(10),
                        Forms\Components\TextInput::make('duration_hours')->numeric()->required()->default(24),
                        Forms\Components\TextInput::make('data_limit_mb')->numeric(),
                        Forms\Components\TextInput::make('price')->numeric()->default(0),
                        Forms\Components\TextInput::make('batch_name'),
                        Forms\Components\Select::make('package_id')->options(fn () => Package::query()->pluck('name', 'id')),
                        Forms\Components\DateTimePicker::make('expires_at'),
                    ])
                    ->action(function (array $data, HotspotVoucherGenerator $generator): void {
                        $generator->generateBatch(
                            (int) $data['count'],
                            (int) $data['duration_hours'],
                            filled($data['data_limit_mb'] ?? null) ? (int) $data['data_limit_mb'] : null,
                            (float) ($data['price'] ?? 0),
                            $data['batch_name'] ?? null,
                            filled($data['package_id'] ?? null) ? (int) $data['package_id'] : null,
                            isset($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null,
                        );
                        Notification::make()->title('Vouchers generated')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotspotVouchers::route('/'),
            'create' => Pages\CreateHotspotVoucher::route('/create'),
            'edit' => Pages\EditHotspotVoucher::route('/{record}/edit'),
        ];
    }
}
