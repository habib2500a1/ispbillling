<?php

namespace App\Filament\Resources\OltResource\Pages;

use App\Filament\Resources\OltResource;
use App\Services\Network\BdcomEponOnuSyncService;
use App\Services\Network\HuaweiGponOnuSyncService;
use App\Services\Network\GponIntelligenceService;
use App\Services\Optical\OnuSignalCollectionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOlt extends EditRecord
{
    protected static string $resource = OltResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_huawei_gpon')
                ->label('Sync Huawei ONUs')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('success')
                ->visible(fn (): bool => app(HuaweiGponOnuSyncService::class)->supportsDriver($this->getRecord()))
                ->requiresConfirmation()
                ->action(function (): void {
                    $olt = $this->getRecord();
                    $result = app(HuaweiGponOnuSyncService::class)->syncOlt($olt->fresh());
                    $n = Notification::make()
                        ->title($result['success'] ? 'Huawei GPON synced' : 'Sync failed')
                        ->body($result['success']
                            ? "{$result['discovered']} ONUs · +{$result['created']} new · {$result['updated']} updated"
                            : ($result['error'] ?? ''));
                    $result['success'] ? $n->success() : $n->danger();
                    $n->send();
                    $this->dispatch('refresh');
                }),
            Actions\Action::make('sync_bdcom_epon')
                ->label('Sync BDCOM ONUs')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('info')
                ->visible(fn (): bool => app(BdcomEponOnuSyncService::class)->supportsDriver($this->getRecord()))
                ->requiresConfirmation()
                ->action(function (): void {
                    $olt = $this->getRecord();
                    $result = app(BdcomEponOnuSyncService::class)->syncOlt($olt->fresh(), false);
                    $n = Notification::make()
                        ->title($result['success'] ? 'BDCOM EPON synced' : 'Sync failed')
                        ->body($result['success']
                            ? "{$result['discovered']} ONUs · +{$result['created']} new · {$result['updated']} updated"
                            : ($result['error'] ?? ''));
                    $result['success'] ? $n->success() : $n->danger();
                    $n->send();
                    $this->dispatch('refresh');
                }),
            Actions\Action::make('sync_onu_dbm')
                ->label('Sync ONU dBm')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $olt = $this->getRecord();
                    try {
                        $sync = app(GponIntelligenceService::class)->syncAllOnuOpticalForOlt($olt);
                        $result = app(OnuSignalCollectionService::class)->collectForTenant((int) $olt->tenant_id);
                        Notification::make()
                            ->title('ONU dBm updated')
                            ->body(sprintf(
                                'Meta sync %d/%d · %d snapshots logged',
                                $sync['synced'],
                                $sync['total'],
                                $result['logged'],
                            ))
                            ->success()
                            ->send();
                        $this->dispatch('refresh');
                    } catch (\Throwable $e) {
                        Notification::make()->title('Sync failed')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! isset($data['olt_driver']) || $data['olt_driver'] === null || $data['olt_driver'] === '') {
            $data['olt_driver'] = 'generic_snmp';
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'olt';
        $data = $this->applyVendorFromOltDriver($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyVendorFromOltDriver(array $data): array
    {
        $driver = $data['olt_driver'] ?? null;
        if (! is_string($driver) || $driver === '') {
            return $data;
        }

        $vendor = config("olt_drivers.drivers.{$driver}.vendor");
        if (is_string($vendor) && $vendor !== '') {
            $data['vendor'] = $vendor;
        }

        return $data;
    }
}
