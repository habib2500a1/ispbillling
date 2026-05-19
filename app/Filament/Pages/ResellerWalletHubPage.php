<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Services\Resellers\ResellerBalanceService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ResellerWalletHubPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static string $view = 'filament.pages.reseller-wallet-hub';

    protected static ?string $navigationLabel = 'Wallet';

    protected static ?string $title = 'Reseller wallets';

    protected static ?string $slug = 'reseller-wallet-hub';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return ResellerResource::canViewAny();
    }

    /**
     * @return array{total: float, partners: int, negative: int}
     */
    public function getWalletStatsProperty(): array
    {
        $base = Reseller::query();

        return [
            'total' => round((float) (clone $base)->sum('wallet_balance'), 2),
            'partners' => (int) (clone $base)->count(),
            'negative' => (int) (clone $base)->where('wallet_balance', '<', 0)->count(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Reseller::query()->orderByDesc('wallet_balance'))
            ->columns([
                Tables\Columns\TextColumn::make('code')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('franchise_type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('wallet_balance')
                    ->label('Balance')
                    ->money('BDT')
                    ->sortable()
                    ->color(fn (Reseller $r): string => (float) $r->wallet_balance < 0 ? 'danger' : 'success')
                    ->weight('bold'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('credit')
                    ->label('Top up')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        TextInput::make('amount')->numeric()->required()->minValue(0.01)->prefix('BDT'),
                        Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (Reseller $record, array $data, ResellerBalanceService $balances): void {
                        $balances->credit($record, (float) $data['amount'], notes: $data['notes'] ?? null);
                        Notification::make()->title('Wallet credited')->success()->send();
                    }),
                Tables\Actions\ViewAction::make()
                    ->url(fn (Reseller $record): string => ResellerResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('wallet_balance', 'desc');
    }
}
