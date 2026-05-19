<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\BandwidthClientPaymentResource\Pages;
use App\Models\BandwidthClient;
use App\Models\BandwidthClientInvoice;
use App\Models\BandwidthClientPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BandwidthClientPaymentResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = BandwidthClientPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Bandwidth payments';

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
                ->live()
                ->native(false),
            Forms\Components\Select::make('bandwidth_client_invoice_id')
                ->label('Apply to invoice')
                ->options(function (Get $get): array {
                    $clientId = $get('bandwidth_client_id');
                    if (! $clientId) {
                        return [];
                    }

                    return BandwidthClientInvoice::query()
                        ->where('bandwidth_client_id', $clientId)
                        ->whereIn('status', ['due', 'partial'])
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (BandwidthClientInvoice $inv): array => [
                            $inv->id => ($inv->invoice_number ?? '#'.$inv->id).' · due '.number_format($inv->balanceDue(), 2).' BDT',
                        ])
                        ->all();
                })
                ->searchable()
                ->native(false),
            Forms\Components\TextInput::make('amount')->numeric()->required()->prefix('BDT'),
            Forms\Components\DateTimePicker::make('paid_at')->default(now())->required()->native(false),
            Forms\Components\Select::make('method')
                ->options([
                    'cash' => 'Cash',
                    'bank' => 'Bank transfer',
                    'bkash' => 'bKash',
                    'nagad' => 'Nagad',
                    'cheque' => 'Cheque',
                ])
                ->default('cash')
                ->native(false),
            Forms\Components\TextInput::make('reference')->maxLength(120),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('client.name')->label('Client')->searchable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')->label('Invoice')->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')->money('BDT')->sortable(),
                Tables\Columns\TextColumn::make('method')->badge(),
                Tables\Columns\TextColumn::make('reference')->placeholder('—'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBandwidthClientPayments::route('/'),
            'create' => Pages\CreateBandwidthClientPayment::route('/create'),
            'edit' => Pages\EditBandwidthClientPayment::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'billing';
    }
}
