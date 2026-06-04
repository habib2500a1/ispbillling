<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ResellerResource;
use App\Models\ResellerCommission;
use App\Models\ResellerWalletRechargeRequest;
use App\Services\Resellers\ResellerWalletRechargeService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResellerPendingWalletRechargesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.reseller-pending-wallet-recharges';

    protected static ?string $navigationLabel = 'Pending top-ups';

    protected static ?string $title = 'Approve wallet top-ups';

    protected static ?string $slug = 'reseller-pending-wallet-recharges';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return ResellerResource::canViewAny();
    }

    public static function pendingCount(): int
    {
        return (int) ResellerWalletRechargeRequest::query()
            ->where('status', ResellerWalletRechargeRequest::STATUS_PENDING)
            ->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ResellerWalletRechargeRequest::query()
                    ->where('status', ResellerWalletRechargeRequest::STATUS_PENDING)
                    ->with(['reseller:id,name,code,wallet_balance,bonus_wallet_balance,is_active,wallet_frozen'])
                    ->latest('created_at'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('Request #')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('reseller.code')
                    ->label('Partner code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reseller.name')
                    ->label('Partner')
                    ->searchable()
                    ->url(fn (ResellerWalletRechargeRequest $record): ?string => $record->reseller_id
                        ? ResellerResource::getUrl('view', ['record' => $record->reseller_id])
                        : null),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Top-up amount')
                    ->money('BDT')
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('reseller.wallet_balance')
                    ->label('Wallet now')
                    ->money('BDT')
                    ->color(fn (ResellerWalletRechargeRequest $record): string => (float) ($record->reseller?->wallet_balance ?? 0) < 0
                        ? 'danger'
                        : 'gray'),
                Tables\Columns\TextColumn::make('pending_commission')
                    ->label('Commission due')
                    ->state(function (ResellerWalletRechargeRequest $record): float {
                        if ($record->reseller_id === null) {
                            return 0.0;
                        }

                        return (float) ResellerCommission::query()
                            ->where('reseller_id', $record->reseller_id)
                            ->where('status', ResellerCommission::STATUS_PENDING)
                            ->sum('commission_amount');
                    })
                    ->money('BDT')
                    ->color('warning')
                    ->description('Unpaid commission ledger'),
                Tables\Columns\TextColumn::make('wallet_after')
                    ->label('Wallet after approve')
                    ->state(function (ResellerWalletRechargeRequest $record): float {
                        return (float) ($record->reseller?->wallet_balance ?? 0) + (float) $record->amount;
                    })
                    ->money('BDT')
                    ->color('success'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge(),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Trx / reference')
                    ->limit(20)
                    ->tooltip(fn (ResellerWalletRechargeRequest $record): ?string => $record->reference),
                Tables\Columns\IconColumn::make('reseller.is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->poll('30s')
            ->striped()
            ->emptyStateHeading('No pending wallet top-ups')
            ->emptyStateDescription('Partner recharge requests will appear here for approval.')
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve & credit')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve wallet top-up')
                    ->modalDescription(function (ResellerWalletRechargeRequest $record): string {
                        $commissionDue = $record->reseller_id
                            ? (float) ResellerCommission::query()
                                ->where('reseller_id', $record->reseller_id)
                                ->where('status', ResellerCommission::STATUS_PENDING)
                                ->sum('commission_amount')
                            : 0.0;
                        $walletAfter = (float) ($record->reseller?->wallet_balance ?? 0) + (float) $record->amount;

                        return sprintf(
                            "Credit %s BDT to %s (%s)?\nWallet now: %s BDT → after approve: %s BDT.\nCommission due (settle separately): %s BDT.",
                            number_format((float) $record->amount, 2),
                            $record->reseller?->name ?? 'partner',
                            $record->request_number,
                            number_format((float) ($record->reseller?->wallet_balance ?? 0), 2),
                            number_format($walletAfter, 2),
                            number_format($commissionDue, 2),
                        );
                    })
                    ->action(function (ResellerWalletRechargeRequest $record): void {
                        try {
                            app(ResellerWalletRechargeService::class)->approve($record, auth()->user());
                            Notification::make()
                                ->title('Wallet credited')
                                ->body($record->request_number.' approved — partner can continue operations.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Could not approve')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for rejection')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ResellerWalletRechargeRequest $record, array $data): void {
                        app(ResellerWalletRechargeService::class)->reject($record, auth()->user(), $data['reason']);
                        Notification::make()->title('Top-up rejected')->success()->send();
                    }),
                Tables\Actions\Action::make('view_partner')
                    ->label('Open partner')
                    ->icon('heroicon-o-user')
                    ->url(fn (ResellerWalletRechargeRequest $record): string => ResellerResource::getUrl('view', ['record' => $record->reseller_id])),
            ]);
    }
}
