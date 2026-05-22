<?php

namespace App\Filament\Resources\StaffExpenseResource\Pages;

use App\Filament\Resources\StaffExpenseResource;
use App\Models\StaffExpense;
use App\Services\Expenses\StaffExpenseService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewStaffExpense extends ViewRecord
{
    protected static string $resource = StaffExpenseResource::class;

    protected function getHeaderActions(): array
    {
        $canApprove = StaffExpenseService::userCanApprove(auth()->user());

        return [
            Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === StaffExpense::STATUS_PENDING && $canApprove)
                ->action(function (): void {
                    app(StaffExpenseService::class)->approve($this->record);
                    Notification::make()->title('Approved')->success()->send();
                    $this->refreshFormData(['status']);
                }),
            Actions\Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === StaffExpense::STATUS_PENDING && $canApprove)
                ->form([
                    Forms\Components\Textarea::make('reason')->required()->maxLength(500),
                ])
                ->action(function (array $data): void {
                    app(StaffExpenseService::class)->reject($this->record, $data['reason']);
                    Notification::make()->title('Rejected')->warning()->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Expense')
                ->schema([
                    Infolists\Components\TextEntry::make('expense_number'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('expense_source')->label('Type')->formatStateUsing(fn (StaffExpense $r) => $r->sourceLabel()),
                    Infolists\Components\TextEntry::make('category.name')->label('Category'),
                    Infolists\Components\TextEntry::make('vendor.name')->placeholder('—'),
                    Infolists\Components\TextEntry::make('amount')->money('BDT'),
                    Infolists\Components\TextEntry::make('payment_method'),
                    Infolists\Components\TextEntry::make('expense_date')->date(),
                    Infolists\Components\TextEntry::make('description')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('submitter.name')->label('Submitted by'),
                    Infolists\Components\TextEntry::make('approver.name')->label('Processed by')->placeholder('—'),
                    Infolists\Components\TextEntry::make('rejection_reason')->visible(fn (StaffExpense $r) => $r->status === StaffExpense::STATUS_REJECTED),
                ])
                ->columns(2),
        ]);
    }
}
