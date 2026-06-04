<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\Reseller;
use App\Services\Resellers\ResellerDueLedgerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Admin receivable ledger';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('entry_type')->badge(),
                Tables\Columns\TextColumn::make('direction')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\TextColumn::make('admin_receivable_after')->money('BDT')->label('Due after'),
                Tables\Columns\TextColumn::make('customer.customer_code')->label('Customer'),
                Tables\Columns\TextColumn::make('notes')->limit(40),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('recordSettlement')
                    ->label('Record settlement')
                    ->icon('heroicon-o-banknotes')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->prefix('BDT')
                            ->helperText('Amount reseller paid HQ — reduces admin receivable due.'),
                        Forms\Components\Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (array $data): void {
                        /** @var Reseller $reseller */
                        $reseller = $this->getOwnerRecord();
                        $amount = (float) $data['amount'];
                        if ($amount <= 0) {
                            Notification::make()->title('Invalid amount')->danger()->send();

                            return;
                        }

                        app(ResellerDueLedgerService::class)->recordSettlement(
                            $reseller,
                            $amount,
                            auth()->user(),
                            $data['notes'] ?? null,
                        );

                        Notification::make()->title('Settlement recorded')->success()->send();
                    }),
                Tables\Actions\Action::make('creditNote')
                    ->label('Credit note')
                    ->icon('heroicon-o-minus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('amount')->numeric()->required()->prefix('BDT'),
                        Forms\Components\Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (array $data): void {
                        app(ResellerDueLedgerService::class)->recordCreditNote(
                            $this->getOwnerRecord(),
                            (float) $data['amount'],
                            auth()->user(),
                            $data['notes'] ?? null,
                        );
                        Notification::make()->title('Credit note recorded')->success()->send();
                    }),
                Tables\Actions\Action::make('debitNote')
                    ->label('Debit note')
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('amount')->numeric()->required()->prefix('BDT'),
                        Forms\Components\Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (array $data): void {
                        app(ResellerDueLedgerService::class)->recordDebitNote(
                            $this->getOwnerRecord(),
                            (float) $data['amount'],
                            auth()->user(),
                            $data['notes'] ?? null,
                        );
                        Notification::make()->title('Debit note recorded')->success()->send();
                    }),
            ]);
    }
}
