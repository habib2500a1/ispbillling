<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PendingGatewayPaymentResource\Pages;
use App\Models\PendingGatewayPayment;
use App\Services\Payments\GatewayPaymentVerificationService;
use App\Services\Payments\PipraPayCheckoutService;
use App\Http\Controllers\PipraPayPaymentController;
use App\Support\PaymentGateway;
use Illuminate\Http\Request;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PendingGatewayPaymentResource extends Resource
{
    protected static ?string $model = PendingGatewayPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Pending gateway payments';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canPayments();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('gateway')->badge(),
                Tables\Columns\TextColumn::make('transaction_id')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Subscriber'),
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (?string $state): string => match ($state) {
                    PendingGatewayPayment::STATUS_PENDING => 'warning',
                    PendingGatewayPayment::STATUS_APPROVED, PendingGatewayPayment::STATUS_AUTO_APPROVED => 'success',
                    PendingGatewayPayment::STATUS_REJECTED => 'danger',
                    default => 'gray',
                }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        PendingGatewayPayment::STATUS_PENDING => 'Pending',
                        PendingGatewayPayment::STATUS_APPROVED => 'Approved',
                        PendingGatewayPayment::STATUS_AUTO_APPROVED => 'Auto approved',
                        PendingGatewayPayment::STATUS_REJECTED => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('gateway')->options([
                    'rocket' => 'Rocket',
                    PaymentGateway::PIPRAPAY => 'PipraPay',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('syncPipraPay')
                    ->label('Sync from PipraPay')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (PendingGatewayPayment $record): bool => $record->gateway === PaymentGateway::PIPRAPAY
                        && $record->status === PendingGatewayPayment::STATUS_PENDING
                        && PipraPayCheckoutService::isEnabled())
                    ->action(function (PendingGatewayPayment $record): void {
                        $service = PipraPayCheckoutService::fromConfig();
                        $ppId = (string) ($record->meta['pp_id'] ?? $record->transaction_id);

                        try {
                            $verified = $service->verifyPayment($ppId);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('PipraPay verify failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $service->isPaymentSuccessful($verified)) {
                            $status = (string) ($verified['status'] ?? $verified['payment_status'] ?? 'pending');
                            Notification::make()
                                ->title('Not approved on PipraPay yet')
                                ->body('Current status: '.$status.'. Approve in PipraPay admin, then sync again.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $request = Request::create('/piprapay/success', 'GET', [
                            'pp_id' => $ppId,
                            'order_id' => $record->checkout_order_id,
                        ]);
                        app(PipraPayPaymentController::class)->success($request);
                        Notification::make()->title('Payment recorded from PipraPay')->success()->send();
                    }),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PendingGatewayPayment $record): bool => $record->status === PendingGatewayPayment::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (PendingGatewayPayment $record): void {
                        try {
                            app(GatewayPaymentVerificationService::class)->approve($record, auth()->id());
                            Notification::make()->title('Payment approved')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Approval failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PendingGatewayPayment $record): bool => $record->status === PendingGatewayPayment::STATUS_PENDING)
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')->label('Reason')->maxLength(500),
                    ])
                    ->action(function (PendingGatewayPayment $record, array $data): void {
                        app(GatewayPaymentVerificationService::class)->reject($record, $data['reason'] ?? null, auth()->id());
                        Notification::make()->title('Payment rejected')->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPendingGatewayPayments::route('/'),
        ];
    }
}
