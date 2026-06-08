<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformInvoiceResource\Pages;
use App\Models\PlatformInvoice;
use App\Models\Tenant;
use App\Services\Tenant\PlatformInvoiceBillingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformInvoiceResource extends Resource
{
    protected static ?string $model = PlatformInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-bangladeshi';

    protected static ?string $navigationLabel = 'Platform invoices';

    protected static ?string $modelLabel = 'platform invoice';

    protected static ?string $pluralModelLabel = 'Platform invoices';

    protected static ?string $navigationGroup = 'System';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('invoice_number')->disabled(),
            Forms\Components\TextInput::make('tenant.name')->disabled(),
            Forms\Components\TextInput::make('amount')->disabled(),
            Forms\Components\Textarea::make('notes')->rows(3),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Invoice')->schema([
                Infolists\Components\TextEntry::make('invoice_number'),
                Infolists\Components\TextEntry::make('tenant.name')->label('ISP tenant'),
                Infolists\Components\TextEntry::make('billing_period')->label('Period'),
                Infolists\Components\TextEntry::make('plan_name')->label('Package'),
                Infolists\Components\TextEntry::make('amount')->formatStateUsing(fn ($state): string => number_format((float) $state, 0).' BDT'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('issue_date')->date(),
                Infolists\Components\TextEntry::make('due_date')->date(),
                Infolists\Components\TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                Infolists\Components\TextEntry::make('payment_reference')->placeholder('—'),
                Infolists\Components\TextEntry::make('notes')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')->label('ISP tenant')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('billing_period')->label('Period')->sortable(),
                Tables\Columns\TextColumn::make('plan_name')->label('Package')->wrap(),
                Tables\Columns\TextColumn::make('customer_count')
                    ->label('Customers')
                    ->formatStateUsing(fn (PlatformInvoice $record): string => $record->max_customers
                        ? "{$record->customer_count} / {$record->max_customers}"
                        : (string) $record->customer_count),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0).' BDT'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PlatformInvoice::STATUS_PAID => 'success',
                        PlatformInvoice::STATUS_OVERDUE => 'danger',
                        PlatformInvoice::STATUS_VOID => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('issue_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->toggleable(),
            ])
            ->defaultSort('issue_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        PlatformInvoice::STATUS_ISSUED => 'Issued',
                        PlatformInvoice::STATUS_PAID => 'Paid',
                        PlatformInvoice::STATUS_OVERDUE => 'Overdue',
                        PlatformInvoice::STATUS_VOID => 'Void',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PlatformInvoice $record): bool => ! $record->isPaid() && $record->status !== PlatformInvoice::STATUS_VOID)
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment reference')
                            ->placeholder('bKash TrxID / bank ref'),
                    ])
                    ->action(function (PlatformInvoice $record, array $data): void {
                        app(PlatformInvoiceBillingService::class)->markPaid(
                            $record,
                            $data['payment_reference'] ?? null,
                        );

                        Notification::make()
                            ->title('Platform invoice marked paid')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generate_due')
                    ->label('Generate due today')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $stats = app(PlatformInvoiceBillingService::class)->generateDue(force: false);
                        Notification::make()
                            ->title('Platform billing run complete')
                            ->body("Created {$stats['created']} · skipped {$stats['skipped']}")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformInvoices::route('/'),
            'view' => Pages\ViewPlatformInvoice::route('/{record}'),
        ];
    }
}
