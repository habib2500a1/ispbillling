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

    protected static ?string $pollingInterval = '120s';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['snapshot' => $this->snapshot()];
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
