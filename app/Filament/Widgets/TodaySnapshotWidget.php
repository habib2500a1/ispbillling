<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

/**
 * "Today" snapshot strip shown at the very top of the dashboard.
 *
 * One glanceable row of the numbers an ISP operator checks first thing:
 *   - collected today, due customers, open tickets, expiring today/tomorrow.
 *
 * Every tile links to the relevant page so it doubles as navigation.
 * Results are cached (60s) so the strip is cheap even though it polls.
 */
class TodaySnapshotWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = -11; // above the quick-tools strip (-8) and KPI grid (-9)

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.today-snapshot';

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = '120s';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $snapshot = $this->snapshot();

        return [
            'snapshot' => $snapshot,
            'tilesHtml' => $this->tilesHtml($snapshot),
        ];
    }

    /** @param  array<string, mixed>  $snapshot */
    protected function tilesHtml(array $snapshot): string
    {
        $currency = fn ($v) => number_format((float) $v, 0).' ৳';
        $custIndex = \Illuminate\Support\Facades\Route::has('filament.admin.resources.customers.index')
            ? route('filament.admin.resources.customers.index') : null;
        $collectRoute = \Illuminate\Support\Facades\Route::has('filament.admin.pages.bill-collection-desk')
            ? route('filament.admin.pages.bill-collection-desk') : $custIndex;
        $ticketRoute = \Illuminate\Support\Facades\Route::has('filament.admin.pages.support-hub')
            ? route('filament.admin.pages.support-hub') : null;

        $tiles = [
            ['label' => 'Collected today', 'value' => $currency($snapshot['collected_today'] ?? 0), 'icon' => 'heroicon-m-banknotes', 'tone' => 'emerald', 'url' => $collectRoute],
            ['label' => 'Due customers', 'value' => number_format($snapshot['due_customers'] ?? 0), 'icon' => 'heroicon-m-exclamation-circle', 'tone' => ($snapshot['due_customers'] ?? 0) > 0 ? 'rose' : 'slate', 'url' => $collectRoute],
            ['label' => 'Open tickets', 'value' => number_format($snapshot['open_tickets'] ?? 0), 'icon' => 'heroicon-m-lifebuoy', 'tone' => ($snapshot['open_tickets'] ?? 0) > 0 ? 'amber' : 'slate', 'url' => $ticketRoute],
            ['label' => 'Expiring today', 'value' => number_format($snapshot['expiring_today'] ?? 0), 'icon' => 'heroicon-m-clock', 'tone' => ($snapshot['expiring_today'] ?? 0) > 0 ? 'rose' : 'slate', 'url' => $custIndex],
            ['label' => 'Expiring tomorrow', 'value' => number_format($snapshot['expiring_tomorrow'] ?? 0), 'icon' => 'heroicon-m-calendar-days', 'tone' => ($snapshot['expiring_tomorrow'] ?? 0) > 0 ? 'amber' : 'slate', 'url' => $custIndex],
        ];

        $html = '';
        foreach ($tiles as $tile) {
            $icon = svg(
                $tile['icon'],
                'isp-today-tile__icon-svg',
                [
                    'width' => '24',
                    'height' => '24',
                    'style' => 'width:24px!important;height:24px!important;min-width:24px!important;min-height:24px!important;max-width:24px!important;max-height:24px!important;display:block!important;flex:0 0 24px!important;box-sizing:border-box!important',
                ],
            )->toHtml();
            $iconBox = 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
                .'width:2.5rem!important;height:2.5rem!important;min-width:2.5rem!important;max-width:2.5rem!important;'
                .'min-height:2.5rem!important;max-height:2.5rem!important;flex:0 0 2.5rem!important;overflow:hidden!important;';
            $body = '<span class="isp-today-tile__icon" style="'.$iconBox.'" aria-hidden="true">'.$icon.'</span>'
                .'<span class="isp-today-tile__body">'
                .'<span class="isp-today-tile__value">'.e($tile['value']).'</span>'
                .'<span class="isp-today-tile__label">'.e($tile['label']).'</span>'
                .'</span>';
            $class = 'isp-today-tile isp-today-tile--'.e($tile['tone']);
            if ($tile['url']) {
                $html .= '<a href="'.e($tile['url']).'" class="'.$class.'">'.$body.'</a>';
            } else {
                $html .= '<div class="'.$class.'">'.$body.'</div>';
            }
        }

        return $html;
    }

    /** @return array<string, mixed> */
    protected function snapshot(): array
    {
        $tenantId = TenantResolver::requiredTenantId();

        return Cache::remember(
            'dashboard:today-snapshot:'.$tenantId,
            now()->addSeconds(60),
            fn (): array => $this->build($tenantId),
        );
    }

    /** @return array<string, mixed> */
    private function build(int $tenantId): array
    {
        $today = today();
        $tomorrow = today()->addDay();

        $collectedToday = (float) Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('payment_type', PaymentType::PAYMENT)
            ->whereDate('paid_at', $today)
            ->sum('amount');

        $dueCustomers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::ACTIVE)
            ->whereHas('invoices', fn ($q) => $q
                ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
                ->whereRaw('(total - amount_paid) > 0.009'))
            ->count();

        $openTickets = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress', 'waiting'])
            ->count();

        $expiringToday = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('service_expires_at', $today)
            ->count();

        $expiringTomorrow = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('service_expires_at', $tomorrow)
            ->count();

        return [
            'collected_today' => $collectedToday,
            'due_customers' => $dueCustomers,
            'open_tickets' => $openTickets,
            'expiring_today' => $expiringToday,
            'expiring_tomorrow' => $expiringTomorrow,
        ];
    }
}
