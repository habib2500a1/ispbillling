<?php

namespace App\Filament\Resources\OltResource\Pages;

use App\Filament\Resources\OltResource;
use App\Filament\Resources\OltResource\Concerns\NormalizesOltFormData;
use App\Services\Network\AveisGponOnuSyncService;
use App\Services\Network\OltSnmpMonitorService;
use App\Services\Olt\OltSnmpProbeService;
use App\Support\OltManagementHelper;
use App\Services\Network\BdcomEponOnuSyncService;
use App\Services\Network\HuaweiGponOnuSyncService;
use App\Services\Network\OltOnuSyncCoordinator;
use App\Services\Network\GponIntelligenceService;
use App\Services\Optical\OnuSignalCollectionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOlt extends EditRecord
{
    use NormalizesOltFormData;

    protected static string $resource = OltResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_web_ui')
                ->label('Open Web UI')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->webUiUrl() !== null)
                ->url(fn (): string => (string) $this->getRecord()->webUiUrl())
                ->openUrlInNewTab(),
            Actions\Action::make('aveis_quick_setup')
                ->label('Aveis setup')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn (): bool => OltManagementHelper::isAveisDriver($this->getRecord()->olt_driver))
                ->requiresConfirmation()
                ->modalDescription('SNMP test → ONU inventory sync (193+ ONUs)। কয়েক মিনিট লাগতে পারে।')
                ->action(function (): void {
                    $olt = $this->getRecord()->fresh();
                    try {
                        $descr = app(OltSnmpProbeService::class)->fetchSysDescr($olt);
                        $poll = app(OltSnmpMonitorService::class)->pollOlt($olt);
                        $sync = app(AveisGponOnuSyncService::class)->syncOlt($olt->fresh(), true);
                        Notification::make()
                            ->title('Aveis setup complete')
                            ->body(sprintf(
                                'SNMP: %s · %d ONUs synced (%d new)',
                                \Illuminate\Support\Str::limit($descr, 40),
                                $sync['discovered'] ?? 0,
                                $sync['created'] ?? 0,
                            ))
                            ->success()
                            ->send();
                        $this->dispatch('refresh');
                    } catch (\Throwable $e) {
                        Notification::make()->title('Setup failed')->body($e->getMessage())->danger()->send();
                    }
                }),
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
            Actions\Action::make('sync_aveis_gpon')
                ->label('Sync Aveis ONUs')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('warning')
                ->visible(fn (): bool => app(AveisGponOnuSyncService::class)->supportsDriver($this->getRecord()))
                ->requiresConfirmation()
                ->action(function (): void {
                    $result = app(AveisGponOnuSyncService::class)->syncOlt($this->getRecord()->fresh(), true);
                    $n = Notification::make()
                        ->title($result['success'] ? 'Aveis OLT synced' : 'Sync failed')
                        ->body($result['success']
                            ? "{$result['discovered']} ONUs · +{$result['created']} new · {$result['updated']} updated"
                            : ($result['error'] ?? ''));
                    $result['success'] ? $n->success() : $n->danger();
                    $n->send();
                    $this->dispatch('refresh');
                }),
            Actions\Action::make('sync_vendor_gpon')
                ->label('Sync VSOL / Ecom ONUs')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('gray')
                ->visible(function (): bool {
                    $olt = $this->getRecord();
                    $coord = app(OltOnuSyncCoordinator::class);

                    return $coord->supportsOlt($olt)
                        && ! app(AveisGponOnuSyncService::class)->supportsDriver($olt)
                        && ! app(BdcomEponOnuSyncService::class)->supportsDriver($olt)
                        && ! app(HuaweiGponOnuSyncService::class)->supportsDriver($olt);
                })
                ->requiresConfirmation()
                ->action(function (): void {
                    $result = app(OltOnuSyncCoordinator::class)->syncOlt($this->getRecord()->fresh(), true);
                    $n = Notification::make()
                        ->title($result['success'] ? 'OLT ONU sync OK' : 'Sync failed')
                        ->body($result['success']
                            ? "{$result['discovered']} ONUs discovered"
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
            $data['olt_driver'] = 'aveis_epon';
        }

        return $this->expandOltFormDataForFill($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeOltFormData($data);
    }
}
