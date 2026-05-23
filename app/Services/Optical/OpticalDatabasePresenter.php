<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * ISP Digital–style optical database grid (all ONUs for tenant).
 */
final class OpticalDatabasePresenter
{
    public function __construct(
        private readonly SubscriberOpticalPowerPresenter $rows,
    ) {}

    public function paginate(int $tenantId, ?string $search = null, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $perPage = in_array($perPage, [10, 25, 50, 100, 200], true) ? $perPage : 25;
        $page = max(1, $page);

        $query = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->with([
                'customer.activePppSession',
                'customer.devices' => fn ($q) => $q->whereIn('type', ['router', 'onu'])->select([
                    'id', 'customer_id', 'type', 'mac_address', 'framed_ip_address',
                ]),
                'olt:id,tenant_id,display_name,serial_number',
                'oltPort:id,olt_id,card_no,pon_no,fiber_distance_m',
            ])
            ->orderByRaw('rx_power_dbm IS NULL ASC')
            ->orderByDesc('last_polled_at')
            ->orderBy('serial_number');

        if (filled($search)) {
            $needle = trim($search);
            $term = '%'.$needle.'%';
            $query->where(function (Builder $q) use ($term, $needle): void {
                $q->where('serial_number', 'like', $term)
                    ->orWhere('mac_address', 'like', $term)
                    ->orWhere('display_name', 'like', $term)
                    ->orWhere('notes', 'like', $term)
                    ->orWhere('onu_external_id', 'like', $term)
                    ->orWhereHas('customer', function (Builder $c) use ($term): void {
                        $c->where('name', 'like', $term)
                            ->orWhere('customer_code', 'like', $term)
                            ->orWhere('mikrotik_secret_name', 'like', $term)
                            ->orWhere('radius_username', 'like', $term)
                            ->orWhere('phone', 'like', $term);
                    })
                    ->orWhereHas('olt', function (Builder $o) use ($term): void {
                        $o->where('display_name', 'like', $term)
                            ->orWhere('serial_number', 'like', $term)
                            ->orWhere('management_ip', 'like', $term);
                    });

                if (preg_match('/^C?\s*(\d+)\s*\/\s*P?\s*(\d+)$/i', $needle, $ponMatch)) {
                    $q->orWhere(function (Builder $pon) use ($ponMatch): void {
                        $pon->where('card_no', (int) $ponMatch[1])
                            ->where('pon_no', (int) $ponMatch[2]);
                    });
                }

                if (ctype_digit($needle)) {
                    $q->orWhere('onu_index', (int) $needle)
                        ->orWhere('pon_no', (int) $needle)
                        ->orWhere('card_no', (int) $needle);
                }
            });
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $start = ($paginator->currentPage() - 1) * $paginator->perPage();

        $paginator->getCollection()->transform(function (Device $onu, int $offset) use ($start): array {
            return $this->rows->rowForOnu($onu, $start + $offset + 1);
        });

        return $paginator;
    }

    /**
     * @return array{total: int, with_rx: int, linked: int}
     */
    public function summary(int $tenantId): array
    {
        $base = Device::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', 'onu');

        return [
            'total' => (clone $base)->count(),
            'with_rx' => (clone $base)->whereNotNull('rx_power_dbm')->count(),
            'linked' => (clone $base)->whereNotNull('customer_id')->count(),
        ];
    }
}
