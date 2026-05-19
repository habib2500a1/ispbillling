<?php

namespace App\Filament\Resources\ResellerResource\Pages;

use App\Filament\Pages\ResellersHub;
use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Services\Resellers\ResellerBalanceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;

class ViewReseller extends ViewRecord
{
    protected static string $resource = ResellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('transfer_balance')
                ->label('Transfer balance')
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('from_reseller_id')
                        ->label('From (optional — HQ if empty)')
                        ->options(Reseller::query()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    Forms\Components\TextInput::make('amount')->numeric()->required()->minValue(0.01),
                    Forms\Components\Textarea::make('notes')->rows(2),
                ])
                ->action(function (array $data, ResellerBalanceService $balances): void {
                    /** @var Reseller $to */
                    $to = $this->record;
                    $from = isset($data['from_reseller_id']) ? Reseller::query()->find($data['from_reseller_id']) : null;
                    $balances->transfer($from, $to, (float) $data['amount'], $data['notes'] ?? null);
                    Notification::make()->title('Balance transferred')->success()->send();
                    $this->record->refresh();
                }),
            Actions\Action::make('credit_wallet')
                ->label('Credit wallet')
                ->icon('heroicon-o-plus-circle')
                ->form([
                    Forms\Components\TextInput::make('amount')->numeric()->required()->minValue(0.01),
                    Forms\Components\Textarea::make('notes')->rows(2),
                ])
                ->action(function (array $data, ResellerBalanceService $balances): void {
                    $balances->credit($this->record, (float) $data['amount'], notes: $data['notes'] ?? null);
                    Notification::make()->title('Wallet credited')->success()->send();
                }),
            Actions\EditAction::make(),
        ];
    }

    public function getTitle(): string
    {
        /** @var Reseller $record */
        $record = $this->record;

        return $record->name.' ('.$record->code.')';
    }

    public function getSubheading(): ?string
    {
        /** @var Reseller $record */
        $record = $this->record;
        $stats = $record->dashboardStats();

        return sprintf(
            '%d customers · %d sub-resellers · Wallet %s BDT · Pending commission %s BDT',
            $stats['customers'],
            $stats['sub_resellers'],
            number_format($stats['wallet'], 2),
            number_format($stats['pending_commission'], 2),
        );
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public function getBreadcrumbs(): array
    {
        return [
            ResellersHub::getUrl() => 'Resellers',
            ResellerResource::getUrl('index') => 'All partners',
            static::getUrl(['record' => $this->record]) => $this->record->name,
        ];
    }
}
