<?php

namespace App\Filament\Pages;

use App\Support\Rbac\StaffCapability;

use App\Models\BankAccount;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Accounting\CashbookService;
use App\Services\Collector\CollectorWalletService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AccountsWalletHubPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static string $view = 'filament.pages.accounts-wallet-hub';

    protected static ?string $navigationLabel = 'Wallets';

    protected static ?string $title = 'Wallets';

    protected static ?string $slug = 'accounts-wallet-hub';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canAccounting();
    }

    /**
     * @return array<string, float|int>
     */
    public function getWalletSummaryProperty(): array
    {
        $collectorTotal = 0.0;
        foreach (User::query()->pluck('id') as $userId) {
            $collectorTotal += (float) (app(CollectorWalletService::class)->wallet((int) $userId)['cash_in_hand'] ?? 0);
        }

        return [
            'cashbook' => app(CashbookService::class)->runningBalance(),
            'banks' => (float) BankAccount::query()->where('is_active', true)->sum('current_balance'),
            'collectors' => round($collectorTotal, 2),
            'resellers' => (float) Reseller::query()->sum('wallet_balance'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BankAccount::query()->where('is_active', true))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('bank_name')->label('Bank'),
                Tables\Columns\TextColumn::make('account_number')->label('Account')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('current_balance')->label('Balance')->money('BDT')->sortable(),
            ])
            ->heading('Bank wallets')
            ->emptyStateHeading('No bank accounts')
            ->emptyStateDescription('Add bank accounts under Accounts → Bank accounts.');
    }
}
