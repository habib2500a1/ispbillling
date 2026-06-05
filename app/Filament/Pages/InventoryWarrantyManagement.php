<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InventoryReportPage;
use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryWarrantyManagement extends Page implements HasTable
{
    use InteractsWithTable;
    use InventoryReportPage;

    protected static ?string $slug = 'inventory-warranty';

    protected static string $view = 'filament.pages.inventory-table-report';

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public function getReportTitle(): string
    {
        return 'Warranty management';
    }

    public function getReportSubtitle(): string
    {
        return 'CPE / device warranty dates, vendor, and claim status.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Device::query()->nonOlts())
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->label('Device')->searchable(),
                Tables\Columns\TextColumn::make('serial_number')->searchable(),
                Tables\Columns\TextColumn::make('warranty_vendor')->label('Vendor')->toggleable(),
                Tables\Columns\TextColumn::make('warranty_started_at')->date()->label('Start'),
                Tables\Columns\TextColumn::make('warranty_expires_at')->date()->label('Expires')
                    ->color(fn (Device $record): ?string => $record->warrantyIsExpired()
                        ? 'danger'
                        : ($record->warrantyExpiresWithinDays(30) ? 'warning' : null)),
                Tables\Columns\TextColumn::make('effective_warranty')
                    ->label('Status')
                    ->state(fn (Device $record): string => $record->effectiveWarrantyStatus())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Device::WARRANTY_ACTIVE => 'success',
                        Device::WARRANTY_EXPIRED => 'danger',
                        Device::WARRANTY_CLAIMED => 'info',
                        Device::WARRANTY_VOID => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('warranty_filter')
                    ->label('Warranty')
                    ->options([
                        'tracked' => 'Has warranty dates',
                        'expiring' => 'Expiring in 30 days',
                        'expired' => 'Expired',
                        'none' => 'No warranty set',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'tracked' => $query->whereNotNull('warranty_expires_at'),
                            'expiring' => $query->whereNotNull('warranty_expires_at')
                                ->where('warranty_expires_at', '>=', now()->toDateString())
                                ->where('warranty_expires_at', '<=', now()->addDays(30)->toDateString()),
                            'expired' => $query->whereNotNull('warranty_expires_at')
                                ->where('warranty_expires_at', '<', now()->toDateString()),
                            'none' => $query->whereNull('warranty_expires_at')->whereNull('warranty_vendor'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_warranty')
                    ->label('Warranty')
                    ->icon('heroicon-o-shield-check')
                    ->form([
                        Forms\Components\TextInput::make('warranty_vendor')->maxLength(128),
                        Forms\Components\DatePicker::make('warranty_started_at'),
                        Forms\Components\DatePicker::make('warranty_expires_at'),
                        Forms\Components\Select::make('warranty_status')
                            ->options([
                                Device::WARRANTY_ACTIVE => 'Active',
                                Device::WARRANTY_EXPIRED => 'Expired',
                                Device::WARRANTY_VOID => 'Void',
                                Device::WARRANTY_CLAIMED => 'Claimed',
                            ])
                            ->nullable(),
                        Forms\Components\DatePicker::make('warranty_claimed_at'),
                        Forms\Components\Textarea::make('warranty_notes')->rows(2)->columnSpanFull(),
                    ])
                    ->fillForm(fn (Device $record): array => $record->only([
                        'warranty_vendor',
                        'warranty_started_at',
                        'warranty_expires_at',
                        'warranty_status',
                        'warranty_claimed_at',
                        'warranty_notes',
                    ]))
                    ->action(function (Device $record, array $data): void {
                        $record->update($data);
                    }),
                Tables\Actions\Action::make('open_device')
                    ->label('Device')
                    ->url(fn (Device $record): string => DeviceResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('warranty_expires_at');
    }
}
