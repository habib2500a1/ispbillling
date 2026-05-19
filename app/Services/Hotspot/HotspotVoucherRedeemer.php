<?php

namespace App\Services\Hotspot;

use App\Models\HotspotVoucher;
use Illuminate\Support\Facades\DB;

final class HotspotVoucherRedeemer
{
    /**
     * @return array{ok: bool, message: string, voucher?: array<string, mixed>}
     */
    public function redeem(string $code, ?string $clientMac = null): array
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return ['ok' => false, 'message' => 'Voucher code is required.'];
        }

        /** @var HotspotVoucher|null $voucher */
        $voucher = HotspotVoucher::query()->where('code', $code)->first();

        if ($voucher === null) {
            return ['ok' => false, 'message' => 'Invalid voucher code.'];
        }

        if (! $voucher->isRedeemable()) {
            return ['ok' => false, 'message' => 'This voucher is no longer valid.'];
        }

        DB::transaction(function () use ($voucher, $clientMac): void {
            $voucher->update([
                'status' => HotspotVoucher::STATUS_USED,
                'used_at' => now(),
                'notes' => trim(($voucher->notes ?? '')."\nRedeemed".($clientMac ? " MAC: {$clientMac}" : '')),
            ]);
        });

        return [
            'ok' => true,
            'message' => 'Voucher activated. You may connect to Wi‑Fi now.',
            'voucher' => [
                'code' => $voucher->code,
                'duration_hours' => $voucher->duration_hours,
                'data_limit_mb' => $voucher->data_limit_mb,
                'expires_at' => $voucher->expires_at?->toIso8601String(),
            ],
        ];
    }
}
