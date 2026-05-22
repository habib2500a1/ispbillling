<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ChecksDashboardWidgetAccess;
use App\Filament\Resources\PendingGatewayPaymentResource;
use App\Models\PendingGatewayPayment;
use App\Support\TenantResolver;
use Filament\Widgets\Widget;

class PendingMfsVerifyAlertWidget extends Widget
{
    use ChecksDashboardWidgetAccess;

    protected static string $view = 'filament.widgets.pending-mfs-verify-alert';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        if (! parent::canView()) {
            return false;
        }

        if (! PendingGatewayPaymentResource::canViewAny()) {
            return false;
        }

        $tenantId = TenantResolver::requiredTenantId();

        return (int) PendingGatewayPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->count() > 0;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $url = PendingGatewayPaymentResource::getUrl();
        $count = (int) PendingGatewayPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->count();

        $items = PendingGatewayPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'count' => $count,
            'url' => $url,
            'items' => $items->map(fn (PendingGatewayPayment $row): array => [
                'gateway' => strtoupper((string) $row->gateway),
                'trx' => (string) $row->transaction_id,
                'amount' => number_format((float) $row->amount, 2),
                'customer' => (string) ($row->customer?->name ?? '—'),
            ])->all(),
        ];
    }
}
