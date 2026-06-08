<?php

namespace App\Services\Ai;

/**
 * Natural-language phrase → read-only tool routing (no LLM required).
 */
final class AiIntentCatalog
{
    /**
     * @return list<array{tool: string, patterns: list<string>, chips?: list<string>}>
     */
    public function definitions(): array
    {
        return [
            ['tool' => 'billing.due_customers', 'patterns' => ['due customer', 'unpaid invoice', 'show due', 'overdue'], 'chips' => ['Show due customers']],
            ['tool' => 'billing.today_collection', 'patterns' => ['today collection', 'collected today', 'today revenue'], 'chips' => ["Show today's collection"]],
            ['tool' => 'billing.monthly_revenue', 'patterns' => ['monthly revenue', 'revenue trend', 'revenue forecast', 'month revenue'], 'chips' => ['Show monthly revenue']],
            ['tool' => 'billing.revenue_by_zone', 'patterns' => ['revenue by zone', 'revenue zone', 'zone revenue'], 'chips' => ['Show revenue by zone']],
            ['tool' => 'billing.top_packages', 'patterns' => ['top package', 'package revenue', 'best package'], 'chips' => ['Show top packages']],
            ['tool' => 'network.offline_onus', 'patterns' => ['offline onu', 'offline onus', 'onu offline'], 'chips' => ['Show offline ONUs']],
            ['tool' => 'network.offline_routers', 'patterns' => ['offline router', 'router offline', 'mikrotik offline'], 'chips' => ['Show offline routers']],
            ['tool' => 'network.weak_signals', 'patterns' => ['weak signal', 'low rx', 'signal quality', 'critical onu'], 'chips' => ['Show weak signals']],
            ['tool' => 'network.olt_capacity', 'patterns' => ['overloaded olt', 'pon utilization', 'olt capacity', 'pon capacity'], 'chips' => ['Show overloaded OLTs']],
            ['tool' => 'network.rca', 'patterns' => ['root cause', 'rca', 'probable cause', 'fiber break'], 'chips' => ['Show root cause analysis']],
            ['tool' => 'support.open_tickets', 'patterns' => ['open ticket', 'support ticket', 'show ticket'], 'chips' => ['Show open tickets']],
            ['tool' => 'support.complaint_trends', 'patterns' => ['complaint', 'ticket trend', 'resolution time', 'escalated ticket'], 'chips' => ['Show complaint trends']],
            ['tool' => 'gis.complaint_density', 'patterns' => ['complaint area', 'highest complaint', 'complaint zone', 'complaint density'], 'chips' => ['Show highest complaint areas']],
            ['tool' => 'inventory.low_stock', 'patterns' => ['low stock', 'inventory stock', 'stock level'], 'chips' => ['Show low stock inventory']],
            ['tool' => 'inventory.warranty_expiring', 'patterns' => ['expiring warranty', 'warranty expir'], 'chips' => ['Show expiring warranties']],
            ['tool' => 'hr.technician_performance', 'patterns' => ['technician performance', 'tech performance', 'resolved ticket'], 'chips' => ['Show technician performance']],
            ['tool' => 'hr.attendance', 'patterns' => ['attendance', 'present today', 'absent today'], 'chips' => ['Show attendance summary']],
            ['tool' => 'bi.recommendations', 'patterns' => ['recommend', 'suggest', 'insight', 'what should', 'priority'], 'chips' => ['Show AI recommendations']],
            ['tool' => 'bi.churn', 'patterns' => ['churn', 'at risk subscriber', 'expiring service'], 'chips' => ['Show churn risk']],
            ['tool' => 'bi.summary', 'patterns' => ['operational summary', 'executive summary', 'overview', 'status'], 'chips' => ['Operational summary']],
        ];
    }

    public function resolve(string $query): ?string
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return null;
        }

        foreach ($this->definitions() as $def) {
            foreach ($def['patterns'] as $pattern) {
                if (str_contains($q, $pattern)) {
                    return $def['tool'];
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function quickChips(): array
    {
        $chips = [];
        foreach ($this->definitions() as $def) {
            foreach ($def['chips'] ?? [] as $chip) {
                $chips[] = $chip;
            }
        }

        return array_values(array_unique($chips));
    }

    /**
     * Parse follow-up filters from natural language.
     *
     * @return array<string, mixed>
     */
    public function parseFollowUpFilters(string $query): array
    {
        $q = strtolower(trim($query));
        $filters = [];

        if (preg_match('/area\s+([a-z0-9\s\-]+)/i', $query, $m)) {
            $filters['area'] = trim($m[1]);
        }
        if (preg_match('/zone\s+([a-z0-9\s\-]+)/i', $query, $m)) {
            $filters['zone'] = trim($m[1]);
        }
        if (str_contains($q, 'only today') || str_contains($q, 'today only')) {
            $filters['today_only'] = true;
        }
        if (str_contains($q, 'critical') || str_contains($q, 'urgent')) {
            $filters['severity'] = 'critical';
        }
        if (str_contains($q, 'clear filter') || str_contains($q, 'reset filter')) {
            $filters['_clear'] = true;
        }

        return $filters;
    }
}
