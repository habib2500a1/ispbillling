<?php

namespace App\Filament\Resources\OltResource\Pages;

use App\Filament\Resources\OltResource;
use App\Filament\Resources\OltResource\Concerns\NormalizesOltFormData;
use App\Services\Olt\OltPptpTunnelService;
use Filament\Resources\Pages\CreateRecord;

class CreateOlt extends CreateRecord
{
    use NormalizesOltFormData;

    protected static string $resource = OltResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->normalizeOltFormData($data, null);
        $data = $this->applyDefaultSerial($data);

        return $data;
    }

    protected function afterCreate(): void
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyDefaultSerial(array $data): array
    {
        if (filled($data['serial_number'] ?? null)) {
            return $data;
        }

        $ip = trim((string) ($data['management_ip'] ?? ''));
        if ($ip !== '') {
            $data['serial_number'] = 'OLT-'.str_replace('.', '-', $ip);
        }

        return $data;
    }

}
