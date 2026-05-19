<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Resources\PayrollRunResource;
use App\Services\Accounting\PayrollService;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayrollRun extends ViewRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markPaid')
                ->label('Mark as paid & post ledger')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(PayrollService::class)->markPaid($this->record);
                    Notification::make()->title('Payroll paid & posted')->success()->send();
                    $this->refreshFormData(['status', 'paid_at']);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make()->schema([
                TextEntry::make('period_month')
                    ->label('Period')
                    ->formatStateUsing(fn ($state, $record) => $record->periodLabel()),
                TextEntry::make('status')->badge(),
                TextEntry::make('total_gross')->money('BDT'),
                TextEntry::make('total_deductions')->money('BDT'),
                TextEntry::make('total_net')->money('BDT'),
            ])->columns(3),
            Section::make('Employees')->schema([
                RepeatableEntry::make('items')
                    ->schema([
                        TextEntry::make('employee.name'),
                        TextEntry::make('gross_salary')->money('BDT'),
                        TextEntry::make('deductions')->money('BDT'),
                        TextEntry::make('net_salary')->money('BDT'),
                    ])
                    ->columns(4),
            ]),
        ]);
    }
}
