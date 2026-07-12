<?php

namespace App\Services\Ai;

use App\Models\CustomersInfo;
use App\Models\InventoryProduct;
use App\Models\NotificationLogs;
use App\Services\Dashboard\DashboardOpsService;
use App\Services\Olt\IspbillingOpticalBridge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Rule-based ops insights (AI digest lite) — no LLM required.
 * Surfaces actionable alerts from billing, tickets, optical, stock, HR, routers.
 */
final class OpsInsightsService
{
    /**
     * @return array{
     *   updated_at: string,
     *   digest: string,
     *   summary: array<string, int|float>,
     *   insights: list<array<string, mixed>>,
     *   counts: array{critical: int, high: int, medium: int, low: int}
     * }
     */
    public function payload(): array
    {
        $ops = app(DashboardOpsService::class)->snapshot();
        $insights = [];

        $this->pushBilling($insights, $ops);
        $this->pushTickets($insights, $ops);
        $this->pushCalls($insights, $ops);
        $this->pushInventory($insights, $ops);
        $this->pushHr($insights, $ops);
        $this->pushRouters($insights, $ops);
        $this->pushCustomers($insights);
        $this->pushOptical($insights);

        usort($insights, function (array $a, array $b): int {
            return $this->rank($b['severity'] ?? '') <=> $this->rank($a['severity'] ?? '');
        });

        $counts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];
        foreach ($insights as $row) {
            $sev = $row['severity'] ?? 'low';
            if (isset($counts[$sev])) {
                $counts[$sev]++;
            }
        }

        $summary = [
            'today_collection' => (float) ($ops['today_collection'] ?? 0),
            'overdue' => (int) ($ops['overdue_notices'] ?? 0),
            'due_soon' => (int) ($ops['due_soon'] ?? 0),
            'open_tickets' => (int) ($ops['open_tickets'] ?? 0),
            'sla_breached' => (int) ($ops['sla_breached'] ?? 0),
            'low_stock' => (int) ($ops['low_stock'] ?? 0),
            'insight_total' => count($insights),
        ];

        return [
            'updated_at' => now()->toIso8601String(),
            'digest' => $this->buildDigest($summary, $insights),
            'summary' => $summary,
            'insights' => $insights,
            'counts' => $counts,
        ];
    }

    /**
     * Persist current digest into notification_logs (in-app ops digest).
     */
    public function publishDigest(): NotificationLogs
    {
        $payload = $this->payload();
        $critical = (int) ($payload['counts']['critical'] ?? 0);
        $high = (int) ($payload['counts']['high'] ?? 0);

        return NotificationLogs::query()->create([
            'title' => 'Ops Digest — '.now()->format('d M Y H:i')." (C:{$critical}/H:{$high})",
            'message' => $payload['digest'],
            'status' => ($critical + $high) > 0 ? 'Action required' : 'OK',
            'type' => 'Ops Digest',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     * @param  array<string, mixed>  $ops
     */
    private function pushBilling(array &$insights, array $ops): void
    {
        $overdue = (int) ($ops['overdue_notices'] ?? 0);
        $dueSoon = (int) ($ops['due_soon'] ?? 0);

        if ($overdue > 0) {
            $insights[] = $this->insight(
                'billing',
                $overdue >= 20 ? 'critical' : 'high',
                __('Overdue billing notices'),
                __(':n subscribers past auto-disable date — collect or Net OFF.', ['n' => $overdue]),
                'billing-notices',
                __('Open billing notices')
            );
        }

        if ($dueSoon > 0) {
            $insights[] = $this->insight(
                'billing',
                'medium',
                __('Due soon'),
                __(':n active subscribers disable within 3 days — send SMS / call.', ['n' => $dueSoon]),
                'sms-notices',
                __('SMS notices')
            );
        }

        if ((float) ($ops['today_collection'] ?? 0) <= 0 && now()->hour >= 12) {
            $insights[] = $this->insight(
                'accounts',
                'medium',
                __('No collection logged today'),
                __('After noon with ৳0 collected — check collectors / payment page.'),
                'accounts-hub',
                __('Accounts Hub')
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     * @param  array<string, mixed>  $ops
     */
    private function pushTickets(array &$insights, array $ops): void
    {
        $breached = (int) ($ops['sla_breached'] ?? 0);
        $open = (int) ($ops['open_tickets'] ?? 0);

        if ($breached > 0) {
            $insights[] = $this->insight(
                'support',
                'critical',
                __('SLA breached tickets'),
                __(':n open tickets past SLA — prioritize NOC queue.', ['n' => $breached]),
                'noc-overview',
                __('NOC Overview')
            );
        } elseif ($open >= 10) {
            $insights[] = $this->insight(
                'support',
                'high',
                __('High open ticket load'),
                __(':n open/in-progress tickets.', ['n' => $open]),
                'admin-tickets',
                __('Support tickets')
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     * @param  array<string, mixed>  $ops
     */
    private function pushCalls(array &$insights, array $ops): void
    {
        $callbacks = (int) ($ops['callbacks'] ?? 0);
        if ($callbacks > 0) {
            $insights[] = $this->insight(
                'call',
                'medium',
                __('Pending callbacks'),
                __(':n callback outcomes in last 7 days.', ['n' => $callbacks]),
                'call-desk',
                __('Call Desk')
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     * @param  array<string, mixed>  $ops
     */
    private function pushInventory(array &$insights, array $ops): void
    {
        $low = (int) ($ops['low_stock'] ?? 0);
        if ($low > 0) {
            $insights[] = $this->insight(
                'inventory',
                $low >= 5 ? 'high' : 'medium',
                __('Low stock items'),
                __(':n products at or below reorder level — create PO.', ['n' => $low]),
                'inventory-purchases',
                __('Purchases')
            );
        }

        if (Schema::hasTable('inventory_products')) {
            $zero = InventoryProduct::query()
                ->where('is_active', true)
                ->where('stock_qty', '<=', 0)
                ->count();
            if ($zero > 0) {
                $insights[] = $this->insight(
                    'inventory',
                    'high',
                    __('Out of stock'),
                    __(':n active products with zero stock.', ['n' => $zero]),
                    'inventory-hub',
                    __('Inventory Hub')
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     * @param  array<string, mixed>  $ops
     */
    private function pushHr(array &$insights, array $ops): void
    {
        $pending = (int) ($ops['pending_leaves'] ?? 0);
        if ($pending > 0) {
            $insights[] = $this->insight(
                'hr',
                'low',
                __('Leave approvals pending'),
                __(':n leave requests waiting.', ['n' => $pending]),
                'hr-hub',
                __('HR Hub')
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     * @param  array<string, mixed>  $ops
     */
    private function pushRouters(array &$insights, array $ops): void
    {
        $total = (int) ($ops['routers'] ?? 0);
        $connected = (int) ($ops['routers_connected'] ?? 0);
        $offline = max(0, $total - $connected);

        if ($total > 0 && $offline > 0) {
            $insights[] = $this->insight(
                'network',
                $connected === 0 ? 'critical' : 'high',
                __('Routers offline'),
                __(':n of :t MikroTik routers not connected.', ['n' => $offline, 't' => $total]),
                'bandwidth-hub',
                __('Bandwidth Hub')
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     */
    private function pushCustomers(array &$insights): void
    {
        try {
            $disabled = CustomersInfo::query()
                ->whereNull('deleted_at')
                ->where('status', 'disable')
                ->count();
            if ($disabled >= 5) {
                $insights[] = $this->insight(
                    'customers',
                    'medium',
                    __('Temporarily disabled customers'),
                    __(':n customers in disable status — review reactivation / dues.', ['n' => $disabled]),
                    'customers.index',
                    __('Customers')
                );
            }
        } catch (\Throwable $e) {
            Log::debug('ops insights customers skipped: '.$e->getMessage());
        }
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     */
    private function pushOptical(array &$insights): void
    {
        try {
            $bridge = app(IspbillingOpticalBridge::class);
            if (! $bridge->enabled()) {
                return;
            }

            $row = DB::connection('ispbilling')->selectOne(
                <<<'SQL'
                SELECT
                    COUNT(*) FILTER (WHERE type = 'onu' AND rx_power_dbm IS NOT NULL AND rx_power_dbm <= -25 AND rx_power_dbm > -28) AS rx_weak,
                    COUNT(*) FILTER (WHERE type = 'onu' AND rx_power_dbm IS NOT NULL AND rx_power_dbm <= -28) AS rx_critical
                FROM devices
                SQL
            );

            $weak = (int) ($row->rx_weak ?? 0);
            $critical = (int) ($row->rx_critical ?? 0);
            $bad = $weak + $critical;

            if ($critical > 0) {
                $insights[] = $this->insight(
                    'optical',
                    'critical',
                    __('Critical ONU RX'),
                    __(':c critical and :w weak ONU signals.', ['c' => $critical, 'w' => $weak]),
                    'onu-management',
                    __('Optical / ONU')
                );
            } elseif ($bad > 0) {
                $insights[] = $this->insight(
                    'optical',
                    'high',
                    __('Weak ONU RX'),
                    __(':n ONUs with weak RX — check fiber / splitter.', ['n' => $bad]),
                    'onu-management',
                    __('Optical / ONU')
                );
            }
        } catch (\Throwable $e) {
            Log::debug('ops insights optical skipped: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function insight(
        string $domain,
        string $severity,
        string $title,
        string $message,
        string $route,
        string $linkLabel
    ): array {
        $url = null;
        try {
            $url = route($route);
        } catch (\Throwable) {
            $url = null;
        }

        return [
            'id' => $domain.'-'.md5($title.$message),
            'domain' => $domain,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'route' => $route,
            'url' => $url,
            'link_label' => $linkLabel,
        ];
    }

    /**
     * @param  array<string, int|float>  $summary
     * @param  list<array<string, mixed>>  $insights
     */
    private function buildDigest(array $summary, array $insights): string
    {
        $lines = [
            'Ops Digest — '.now()->format('d M Y H:i'),
            'Collection today: '.number_format((float) $summary['today_collection'], 2),
            'Overdue / due soon: '.(int) $summary['overdue'].' / '.(int) $summary['due_soon'],
            'Open tickets / SLA breached: '.(int) $summary['open_tickets'].' / '.(int) $summary['sla_breached'],
            'Low stock: '.(int) $summary['low_stock'],
            'Insights: '.(int) $summary['insight_total'],
        ];

        foreach (array_slice($insights, 0, 5) as $row) {
            $lines[] = '• ['.strtoupper((string) $row['severity']).'] '.$row['title'].' — '.$row['message'];
        }

        if ($insights === []) {
            $lines[] = '• No critical actions — systems look calm.';
        }

        return implode("\n", $lines);
    }

    private function rank(string $severity): int
    {
        return match ($severity) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}
