<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Resources\PayrollRunResource;
use App\Services\Accounting\PayrollService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPayrollRuns extends ListRecords
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate this month')
                ->icon('heroicon-o-sparkles')
                ->action(function (): void {
                    $run = app(PayrollService::class)->generateDraft(
                        (int) now()->month,
                        (int) now()->year,
                    );
                    Notification::make()
                        ->title('Payroll draft ready')
                        ->body($run->periodLabel().' — net '.number_format((float) $run->total_net, 2).' BDT')
                        ->success()
                        ->send();
                }),
        ];
    }
}
