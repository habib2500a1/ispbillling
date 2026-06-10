<?php

namespace App\Services\Geo;

use App\Models\District;
use App\Models\Upazila;

final class BangladeshGeoResolver
{
    /**
     * @return array{district_id: ?int, upazila_id: ?int, district: ?string, thana: ?string}
     */
    public function resolve(?string $districtName, ?string $upazilaName): array
    {
        $districtName = $this->clean($districtName);
        $upazilaName = $this->clean($upazilaName);

        $district = $districtName !== ''
            ? District::query()->whereRaw('LOWER(name) = ?', [strtolower($districtName)])->first()
            : null;

        $upazila = null;
        if ($upazilaName !== '') {
            $query = Upazila::query()->whereRaw('LOWER(name) = ?', [strtolower($upazilaName)]);
            if ($district !== null) {
                $query->where('district_id', $district->id);
            }
            $upazila = $query->first();
        }

        return [
            'district_id' => $district?->id,
            'upazila_id' => $upazila?->id,
            'district' => $district?->name ?? ($districtName !== '' ? $districtName : null),
            'thana' => $upazila?->name ?? ($upazilaName !== '' ? $upazilaName : null),
        ];
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
    }
}
