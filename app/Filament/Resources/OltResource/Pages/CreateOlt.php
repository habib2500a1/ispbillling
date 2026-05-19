<?php

namespace App\Filament\Resources\OltResource\Pages;

use App\Filament\Resources\OltResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOlt extends CreateRecord
{
    protected static string $resource = OltResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
