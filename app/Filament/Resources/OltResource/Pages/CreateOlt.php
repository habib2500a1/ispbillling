<?php

namespace App\Filament\Resources\OltResource\Pages;

use App\Filament\Resources\OltResource;
use App\Filament\Resources\OltResource\Concerns\NormalizesOltFormData;
use Filament\Resources\Pages\CreateRecord;

class CreateOlt extends CreateRecord
{
    use NormalizesOltFormData;

    protected static string $resource = OltResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->normalizeOltFormData($data);
        $data = $this->applyDefaultSerial($data);

        return $data;
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
