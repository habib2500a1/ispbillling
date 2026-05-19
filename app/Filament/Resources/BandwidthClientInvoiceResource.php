<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\BandwidthClientInvoiceResource\Pages;
use App\Models\BandwidthClient;
use App\Models\BandwidthClientInvoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BandwidthClientInvoiceResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = BandwidthClientInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Bandwidth invoices';

    protected static ?string $navigationGroup = 'BW Client';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('bandwidth_client_id')
                ->label('Client')
                ->options(fn () => BandwidthClient::query()->orderBy('name')->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->native(false),
            Forms\Components\TextInput::make('invoice_number')->maxLength(40),
            Forms\Components\TextInput::make('amount')->numeric()->required()->prefix('BDT'),
            Forms\Components\TextInput::make('amount_paid')->numeric()->default(0)->prefix('BDT'),
            Forms\Components\Select::make('status')
                ->options(['due' => 'Due', 'partial' => 'Partial', 'paid' => 'Paid', 'cancelled' => 'Cancelled'])
                ->native(false),
            Forms\Components\DatePicker::make('due_date')->native(false),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable()->fontFamily('mono'),
                Tables\Columns\TextColumn::make('client.name')->label('Client')->searchable(),
                Tables\Columns\TextColumn::make('period')
                    ->label('Period')
                    ->state(fn (BandwidthClientInvoice $record): string => $record->period_month && $record->period_year
                        ? date('M Y', mktime(0, 0, 0, (int) $record->period_month, 1, (int) $record->period_year))
                        : '—'),
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\TextColumn::make('amount_paid')->money('BDT'),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Due')
                    ->money('BDT')
                    ->state(fn (BandwidthClientInvoice $record): float => $record->balanceDue())
                    ->color(fn (BandwidthClientInvoice $record): string => $record->balanceDue() > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('due_date')->date('d M Y'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['due' => 'Due', 'partial' => 'Partial', 'paid' => 'Paid']),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBandwidthClientInvoices::route('/'),
            'edit' => Pages\EditBandwidthClientInvoice::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'billing';
    }
}
