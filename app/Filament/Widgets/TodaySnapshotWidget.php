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
 */
class TodaySnapshotWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = -11;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.today-snapshot';

    protected static bool $isLazy = false;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'tiles' => $this->tiles($this->snapshot()),
        ];
    }

    /** @param  array<string, mixed>  $snapshot
     * @return list<array{label: string, value: string, icon: string, tone: string, url: ?string}>
     */
    protected function tiles(array $snapshot): array
    {
        $currency = fn ($v) => number_format((float) $v, 0).' ৳';
        $custIndex = \Illuminate\Support\Facades\Route::has('filament.admin.resources.customers.index')
            ? route('filament.admin.resources.customers.index') : null;
        $collectRoute = \Illuminate\Support\Facades\Route::has('filament.admin.pages.bill-collection-desk')
            ? route('filament.admin.pages.bill-collection-desk') : $custIndex;
        $ticketRoute = \Illuminate\Support\Facades\Route::has('filament.admin.pages.support-hub')
            ? route('filament.admin.pages.support-hub') : null;

        return [
            ['label' => 'Collected today', 'value' => $currency($snapshot['collected_today'] ?? 0), 'icon' => 'heroicon-m-banknotes', 'tone' => 'emerald', 'url' => $collectRoute],
            ['label' => 'Due customers', 'value' => number_format($snapshot['due_customers'] ?? 0), 'icon' => 'heroicon-m-exclamation-circle', 'tone' => ($snapshot['due_customers'] ?? 0) > 0 ? 'rose' : 'slate', 'url' => $collectRoute],
            ['label' => 'Open tickets', 'value' => number_format($snapshot['open_tickets'] ?? 0), 'icon' => 'heroicon-m-lifebuoy', 'tone' => ($snapshot['open_tickets'] ?? 0) > 0 ? 'amber' : 'slate', 'url' => $ticketRoute],
            ['label' => 'Expiring today', 'value' => number_format($snapshot['expiring_today'] ?? 0), 'icon' => 'heroicon-m-clock', 'tone' => ($snapshot['expiring_today'] ?? 0) > 0 ? 'rose' : 'slate', 'url' => $custIndex],
            ['label' => 'Expiring tomorrow', 'value' => number_format($snapshot['expiring_tomorrow'] ?? 0), 'icon' => 'heroicon-m-calendar-days', 'tone' => ($snapshot['expiring_tomorrow'] ?? 0) > 0 ? 'amber' : 'slate', 'url' => $custIndex],
        ];
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
