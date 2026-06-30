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
            ['tool' => 'billing.due_customers', 'patterns' => ['due customer', 'unpaid invoice', 'show due', 'overdue', 'বকেয়া', 'বকেয়া', 'দেনা', 'বিল বকেয়া'], 'chips' => ['Show due customers', 'বকেয়া কাস্টমার']],
            ['tool' => 'billing.today_collection', 'patterns' => ['today collection', 'collected today', 'today revenue', 'আজকের কালেকশন', 'আজ সংগ্রহ'], 'chips' => ["Show today's collection", 'আজকের কালেকশন']],
            ['tool' => 'billing.monthly_revenue', 'patterns' => ['monthly revenue', 'revenue trend', 'revenue forecast', 'month revenue', 'মাসিক আয়', 'রেভিনিউ'], 'chips' => ['Show monthly revenue']],
            ['tool' => 'billing.revenue_by_zone', 'patterns' => ['revenue by zone', 'revenue zone', 'zone revenue', 'জোন আয়', 'জোন রেভিনিউ'], 'chips' => ['Show revenue by zone']],
            ['tool' => 'billing.top_packages', 'patterns' => ['top package', 'package revenue', 'best package', 'প্যাকেজ'], 'chips' => ['Show top packages']],
            ['tool' => 'network.offline_onus', 'patterns' => ['offline onu', 'offline onus', 'onu offline', 'অফলাইন onu', 'অফলাইন ওনু'], 'chips' => ['Show offline ONUs', 'অফলাইন ONU']],
            ['tool' => 'network.offline_routers', 'patterns' => ['offline router', 'router offline', 'mikrotik offline', 'অফলাইন রাউটার'], 'chips' => ['Show offline routers']],
            ['tool' => 'network.weak_signals', 'patterns' => ['weak signal', 'low rx', 'signal quality', 'critical onu', 'দুর্বল সিগন্যাল', 'সিগন্যাল'], 'chips' => ['Show weak signals']],
            ['tool' => 'network.olt_capacity', 'patterns' => ['overloaded olt', 'pon utilization', 'olt capacity', 'pon capacity', 'olt'], 'chips' => ['Show overloaded OLTs']],
            ['tool' => 'network.rca', 'patterns' => ['root cause', 'rca', 'probable cause', 'fiber break', 'রুট কজ', 'কারণ'], 'chips' => ['Show root cause analysis']],
            ['tool' => 'support.open_tickets', 'patterns' => ['open ticket', 'support ticket', 'show ticket', 'টিকেট', 'অভিযোগ'], 'chips' => ['Show open tickets', 'খোলা টিকেট']],
            ['tool' => 'support.complaint_trends', 'patterns' => ['complaint', 'ticket trend', 'resolution time', 'escalated ticket', 'কমপ্লেইন'], 'chips' => ['Show complaint trends']],
            ['tool' => 'support.ticket_triage', 'patterns' => ['triage ticket', 'classify ticket', 'টিকেট ট্রায়াজ', 'টিকেট বিশ্লেষণ'], 'chips' => ['Triage latest ticket']],
            ['tool' => 'gis.complaint_density', 'patterns' => ['complaint area', 'highest complaint', 'complaint zone', 'complaint density', 'এলাকায় অভিযোগ'], 'chips' => ['Show highest complaint areas']],
            ['tool' => 'inventory.low_stock', 'patterns' => ['low stock', 'inventory stock', 'stock level', 'স্টক'], 'chips' => ['Show low stock inventory']],
            ['tool' => 'inventory.warranty_expiring', 'patterns' => ['expiring warranty', 'warranty expir', 'ওয়ারেন্টি'], 'chips' => ['Show expiring warranties']],
            ['tool' => 'hr.technician_performance', 'patterns' => ['technician performance', 'tech performance', 'resolved ticket', 'টেকনিশিয়ান'], 'chips' => ['Show technician performance']],
            ['tool' => 'hr.attendance', 'patterns' => ['attendance', 'present today', 'absent today', 'উপস্থিতি', 'হাজিরা'], 'chips' => ['Show attendance summary']],
            ['tool' => 'bi.recommendations', 'patterns' => ['recommend', 'suggest', 'insight', 'what should', 'priority', 'পরামর্শ', 'সাজেশন'], 'chips' => ['Show AI recommendations']],
            ['tool' => 'bi.churn', 'patterns' => ['churn', 'at risk subscriber', 'expiring service', 'চার্ন', 'ঝুঁকি'], 'chips' => ['Show churn risk']],
            ['tool' => 'bi.churn_scored', 'patterns' => ['churn score', 'risk score', 'churn list', 'ঝুঁকিপূর্ণ গ্রাহক'], 'chips' => ['Show scored churn list']],
            ['tool' => 'bi.summary', 'patterns' => ['operational summary', 'executive summary', 'overview', 'status', 'সারাংশ', 'ওভারভিউ'], 'chips' => ['Operational summary', 'অপারেশন সারাংশ']],
            ['tool' => 'actions.propose_suspend_defaulters', 'patterns' => ['suspend defaulter', 'suspend chronic', 'সাসপেন্ড', 'বকেয়াদার বন্ধ'], 'chips' => ['Propose suspend chronic defaulters']],
        ];
    }

    public function resolve(string $query): ?string
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return null;
        }

        foreach ($this->definitions() as $def) {
            foreach ($def['patterns'] as $pattern) {
                if (str_contains($q, mb_strtolower($pattern))) {
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
        $q = mb_strtolower(trim($query));
        $filters = [];

        if (preg_match('/area\s+([a-z0-9\s\-]+)/i', $query, $m)) {
            $filters['area'] = trim($m[1]);
        }
        if (preg_match('/zone\s+([a-z0-9\s\-]+)/i', $query, $m)) {
            $filters['zone'] = trim($m[1]);
        }
        if (preg_match('/(?:জোন|এলাকা)\s+([^\?\.\!]+)/u', $query, $m)) {
            $filters['zone'] = trim($m[1]);
        }
        if (str_contains($q, 'only today') || str_contains($q, 'today only') || str_contains($q, 'শুধু আজ')) {
            $filters['today_only'] = true;
        }
        if (str_contains($q, 'critical') || str_contains($q, 'urgent') || str_contains($q, 'জরুরি')) {
            $filters['severity'] = 'critical';
        }
        if (str_contains($q, 'clear filter') || str_contains($q, 'reset filter') || str_contains($q, 'ফিল্টার মুছ')) {
            $filters['_clear'] = true;
        }

        return $filters;
    }
}
