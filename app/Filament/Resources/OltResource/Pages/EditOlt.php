<?php

namespace App\Filament\Resources\OltResource\Pages;

use App\Filament\Resources\OltResource;
use App\Filament\Resources\OltResource\Concerns\NormalizesOltFormData;
use App\Services\Network\AveisGponOnuSyncService;
use App\Services\Network\OltSnmpMonitorService;
use App\Services\Olt\AveisOltDiagnosticsService;
use App\Jobs\RunOltPptpDiagnoseJob;
use App\Services\Olt\OltPptpTunnelService;
use App\Services\Olt\OltSnmpProbeService;
use Illuminate\Support\Facades\Cache;
use App\Support\OltManagementHelper;
use App\Services\Network\BdcomEponOnuSyncService;
use App\Services\Network\HuaweiGponOnuSyncService;
use App\Services\Network\OltOnuSyncCoordinator;
use App\Services\Network\VsolGponOnuSyncService;
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
            Actions\Action::make('test_vpn_now')
                ->label('Test VPN')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->visible(fn (): bool => filled($this->getRecord()->management_ip))
                ->action(function (): void {
                    RunOltPptpDiagnoseJob::dispatch((int) $this->getRecord()->id);
                    Notification::make()
                        ->title('VPN test started (background)')
                        ->body('পেজ খোলা রাখুন না — ৩০–৯০ সেক পর **VPN result (last)** চাপুন। Save করবেন না।')
                        ->info()
                        ->persistent()
                        ->send();
                }),
            Actions\Action::make('test_vpn_result')
                ->label('VPN result (last)')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->visible(fn (): bool => filled($this->getRecord()->management_ip))
                ->action(function (): void {
                    $cached = Cache::get(RunOltPptpDiagnoseJob::cacheKey((int) $this->getRecord()->id));
                    $this->notifyVpnTestResult($cached);
                }),
            Actions\Action::make('open_web_ui')
                ->label('Open Web UI')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->webUiUrl() !== null)
                ->url(fn (): string => (string) $this->getRecord()->webUiUrl())
                ->openUrlInNewTab(),
            Actions\Action::make('remove_vpn')
                ->label('Remove VPN')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => OltManagementHelper::vpnEnabled($this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading('Remove VPN from this OLT?')
                ->action(function (): void {
                    app(OltPptpTunnelService::class)->removeVpn($this->getRecord()->fresh());
                    Notification::make()->title('VPN removed')->success()->send();
                    $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                }),
            Actions\Action::make('diagnose_aveis')
                ->label('Test Aveis connection')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->visible(fn (): bool => OltManagementHelper::isAveisDriver($this->getRecord()->olt_driver))
                ->action(function (): void {
                    $diag = app(AveisOltDiagnosticsService::class)->diagnose($this->getRecord()->fresh());
                    $body = $diag['summary'];
                    if ($diag['sys_descr'] ?? null) {
                        $body .= "\n".\Illuminate\Support\Str::limit((string) $diag['sys_descr'], 80);
                    }
                    if ($diag['hints'] !== []) {
                        $body .= "\n\n".implode("\n", $diag['hints']);
                    }
                    $ok = ($diag['snmp_get_ok'] ?? false) && ($diag['snmp_walk_rows'] ?? 0) > 0;
                    $n = Notification::make()
                        ->title($ok ? 'Aveis SNMP ready' : 'Aveis connection issue')
                        ->body($body);
                    $ok ? $n->success() : $n->warning();
                    $n->send();
                }),
            Actions\Action::make('aveis_quick_setup')
                ->label('Aveis setup')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn (): bool => OltManagementHelper::isAveisDriver($this->getRecord()->olt_driver))
                ->requiresConfirmation()
                ->modalDescription('SNMP test → auto column detect → ONU sync → subscriber auto-link (MAC/PPP/description)। কয়েক মিনিট লাগতে পারে।')
                ->action(function (): void {
                    $olt = $this->getRecord()->fresh();
                    try {
                        $descr = app(OltSnmpProbeService::class)->fetchSysDescr($olt);
                        $poll = app(OltSnmpMonitorService::class)->pollOlt($olt);
                        $sync = app(AveisGponOnuSyncService::class)->syncOlt($olt->fresh(), true);
                        $n = Notification::make()
                            ->title($sync['success'] ? 'Aveis setup complete' : 'Aveis setup — no ONUs')
                            ->body($sync['success']
                                ? sprintf(
                                    'SNMP: %s · %d ONUs (%d new) · mode %s',
                                    \Illuminate\Support\Str::limit($descr, 40),
                                    $sync['discovered'] ?? 0,
                                    $sync['created'] ?? 0,
                                    $sync['sync_mode'] ?? 'snmp',
                                )
                                : ($sync['error'] ?? 'Unknown'));
                        $sync['success'] ? $n->success() : $n->warning();
                        $n->send();
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
                ->label('Sync Aveis ONUs (GPON/EPON)')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('warning')
                ->visible(fn (): bool => app(AveisGponOnuSyncService::class)->supportsDriver($this->getRecord()))
                ->requiresConfirmation()
                ->action(function (): void {
                    $result = app(AveisGponOnuSyncService::class)->syncOlt($this->getRecord()->fresh(), true);
                    $n = Notification::make()
                        ->title($result['success'] ? 'Aveis OLT synced' : 'Sync failed')
                        ->body($result['success']
                            ? "{$result['discovered']} ONUs · +{$result['created']} new · {$result['updated']} updated · linked ".($result['linked'] ?? 0)
                            : ($result['error'] ?? ''));
                    $result['success'] ? $n->success() : $n->danger();
                    $n->send();
                    $this->dispatch('refresh');
                }),
            Actions\Action::make('sync_vendor_gpon')
                ->label('Sync ONUs (SNMP)')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('gray')
                ->visible(function (): bool {
                    $olt = $this->getRecord();

                    return app(VsolGponOnuSyncService::class)->supportsDriver($olt);
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
                ->label('Sync BDCOM EPON/GPON ONUs')
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

        $data = $this->expandOltFormDataForFill($data);

        if (OltManagementHelper::isAveisDriver($data['olt_driver'] ?? null)) {
            $data['meta_extra'] = [];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeOltFormData($data, $this->getRecord());
    }

    protected function afterSave(): void
    {
        $olt = $this->getRecord()->fresh();
        $tunnel = app(OltPptpTunnelService::class);
        $state = $this->form->getState();

        $ovpn = trim((string) ($state['olt_openvpn_config'] ?? ''));
        if ($ovpn !== '') {
            $tunnel->storeOpenVpnConfig($olt, $ovpn);
        }
        $tunnel->syncPeerFromOlt($olt->fresh());
    }

    /**
     * @param  array<string, mixed>|null  $cached
     */
    private function notifyVpnTestResult(?array $cached): void
    {
        if (! is_array($cached)) {
            Notification::make()
                ->title('No VPN test yet')
                ->body('আগে **Test VPN** চাপুন, ৩০–৯০ সেক পর আবার এখানে।')
                ->warning()
                ->send();

            return;
        }
        if (($cached['status'] ?? '') === 'running') {
            Notification::make()->title('VPN test still running…')->warning()->send();

            return;
        }
        $body = ($cached['summary'] ?? '')."\n\n".implode("\n", $cached['lines'] ?? []);
        $ok = (bool) ($cached['success'] ?? false);
        $n = Notification::make()
            ->title($ok ? 'VPN / OLT OK' : 'VPN — কেন কাজ করছে না')
            ->body($body)
            ->persistent();
        $ok ? $n->success() : $n->danger();
        $n->send();
    }
}
