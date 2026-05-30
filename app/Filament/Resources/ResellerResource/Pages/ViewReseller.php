<?php

namespace App\Filament\Resources\ResellerResource\Pages;

use App\Filament\Pages\ResellersHub;
use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Services\Resellers\ResellerBalanceService;
use App\Services\Resellers\ResellerPortalAccessService;
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
            Actions\Action::make('reseller_portal_login')
                ->label('Portal login')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('success')
                ->url(fn (): string => route('staff.resellers.portal-login', ['reseller' => $this->record->getKey()]))
                ->openUrlInNewTab(),
            Actions\Action::make('reseller_portal_credentials')
                ->label('Portal ID & password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->modalHeading(fn (): string => 'Portal access — '.$this->record->name)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function (): \Illuminate\Contracts\View\View {
                    /** @var Reseller $record */
                    $record = $this->record;
                    $portal = app(ResellerPortalAccessService::class);
                    $portal->ensurePortalPassword($record);
                    $fresh = $record->fresh() ?? $record;

                    return view('filament.resources.reseller-resource.portal-access-modal', [
                        'login' => $portal->portalLoginId($fresh),
                        'passwordPlain' => $portal->portalPasswordPlain($fresh),
                        'token' => $portal->ensureAccessToken($fresh),
                        'link' => $portal->accessTokenUrl($fresh),
                    ]);
                }),
            Actions\Action::make('transfer_balance')
                ->label('Transfer balance')
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('from_reseller_id')
                        ->label('From (optional — HQ if empty)')
                        ->options(fn (): array => Reseller::query()
                            ->where('id', '!=', $this->record->getKey())
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->nullable()
                        ->helperText('Leave empty to credit from HQ. Pick a reseller to move balance between wallets.'),
                    Forms\Components\TextInput::make('amount')->numeric()->required()->minValue(0.01)->prefix('BDT'),
                    Forms\Components\Textarea::make('notes')->rows(2),
                ])
                ->action(function (array $data, ResellerBalanceService $balances): void {
                    /** @var Reseller $to */
                    $to = $this->record;
                    $from = isset($data['from_reseller_id']) ? Reseller::query()->find($data['from_reseller_id']) : null;

                    if ($from !== null && (int) $from->getKey() === (int) $to->getKey()) {
                        Notification::make()->title('Cannot transfer to the same reseller')->danger()->send();

                        return;
                    }

                    try {
                        $balances->transfer($from, $to, (float) $data['amount'], $data['notes'] ?? null);
                        Notification::make()->title('Balance transferred')->success()->send();
                        $this->record->refresh();
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        Notification::make()->title('Transfer failed')->body($e->validator->errors()->first())->danger()->send();
                    }
                }),
            Actions\Action::make('credit_wallet')
                ->label('Credit wallet')
                ->icon('heroicon-o-plus-circle')
                ->form([
                    Forms\Components\TextInput::make('amount')->numeric()->required()->minValue(0.01)->prefix('BDT'),
                    Forms\Components\Textarea::make('notes')->rows(2),
                ])
                ->action(function (array $data, ResellerBalanceService $balances): void {
                    $balances->credit($this->record, (float) $data['amount'], notes: $data['notes'] ?? null);
                    Notification::make()->title('Wallet credited')->success()->send();
                    $this->record->refresh();
                }),
            Actions\Action::make('debit_wallet')
                ->label('Deduct balance')
                ->icon('heroicon-o-minus-circle')
                ->color('danger')
                ->form([
                    Forms\Components\TextInput::make('amount')->numeric()->required()->minValue(0.01)->prefix('BDT'),
                    Forms\Components\Textarea::make('notes')->rows(2),
                ])
                ->action(function (array $data, ResellerBalanceService $balances): void {
                    try {
                        $balances->debit($this->record, (float) $data['amount'], $data['notes'] ?? null);
                        Notification::make()->title('Wallet debited')->success()->send();
                        $this->record->refresh();
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        Notification::make()->title('Debit failed')->body($e->validator->errors()->first())->danger()->send();
                    }
                }),
            Actions\Action::make('toggle_wallet_freeze')
                ->label(fn (): string => $this->record->wallet_frozen ? 'Unfreeze wallet' : 'Freeze wallet')
                ->icon('heroicon-o-lock-closed')
                ->color(fn (): string => $this->record->wallet_frozen ? 'success' : 'warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['wallet_frozen' => ! $this->record->wallet_frozen]);
                    Notification::make()
                        ->title($this->record->wallet_frozen ? 'Wallet frozen' : 'Wallet unfrozen')
                        ->success()
                        ->send();
                    $this->record->refresh();
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
