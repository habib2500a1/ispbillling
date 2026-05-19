<?php

namespace App\Services\Hotspot;

use App\Models\HotspotVoucher;
use Illuminate\Support\Str;

class HotspotVoucherGenerator
{
    /**
     * @return list<HotspotVoucher>
     */
    public function generateBatch(
        int $count,
        int $durationHours,
        ?int $dataLimitMb,
        float $price,
        ?string $batchName = null,
        ?int $packageId = null,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        $batchName ??= 'BATCH-'.now()->format('Ymd-His');
        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $created[] = HotspotVoucher::query()->create([
                'code' => $this->uniqueCode(),
                'batch_name' => $batchName,
                'duration_hours' => $durationHours,
                'data_limit_mb' => $dataLimitMb,
                'price' => $price,
                'status' => HotspotVoucher::STATUS_AVAILABLE,
                'package_id' => $packageId,
                'expires_at' => $expiresAt,
            ]);
        }

        return $created;
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
        } while (HotspotVoucher::query()->where('code', $code)->exists());

        return $code;
    }
}
