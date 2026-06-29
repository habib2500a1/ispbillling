<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Support\CustomerBalanceDue;
use App\Support\ResellerPortalPermission;

final class AiResellerAssistantService
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiAuditLogger $audit,
        private readonly AiOperationsOrchestrator $orchestrator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ask(Reseller $reseller, string $query): array
    {
        abort_unless($this->settings->resellerAiEnabled((int) $reseller->tenant_id), 503);

        $q = mb_strtolower(trim($query));
        $bn = preg_match('/[\x{0980}-\x{09FF}]/u', $query);

        if ($this->contains($q, ['due', 'বকেয়া', 'dues', 'বিল'])) {
            return $this->wrap($reseller, $query, $this->dueSummary($reseller, $bn));
        }

        if ($this->contains($q, ['commission', 'কমিশন'])) {
            return $this->wrap($reseller, $query, $this->commissionSummary($reseller, $bn));
        }

        if ($this->contains($q, ['customer', 'subscriber', 'কাস্টমার', 'গ্রাহক'])) {
            $count = Customer::withoutGlobalScopes()->where('reseller_id', $reseller->id)->count();

            return $this->wrap($reseller, $query, $bn
                ? "আপনার অধীনে মোট {$count} জন সাবস্ক্রাইবার আছে।"
                : "You have {$count} subscriber(s) under your account.");
        }

        if ($reseller->canPortal(ResellerPortalPermission::REPORTS_VIEW)
            && $this->contains($q, ['summary', 'overview', 'সারাংশ'])) {
            $payload = $this->orchestrator->dashboard((int) $reseller->tenant_id);

            return $this->wrap($reseller, $query, $bn
                ? 'অপারেশন সারাংশ প্রস্তুত। খোলা টিকেট: '.(int) data_get($payload, 'summary.open_tickets', 0)
                : 'Operations summary ready. Open tickets: '.(int) data_get($payload, 'summary.open_tickets', 0), [
                'dashboard' => $payload,
            ]);
        }

        return $this->wrap($reseller, $query, $bn
            ? 'আমি বকেয়া, কমিশন, গ্রাহক সংখ্যা ও সারাংশে সাহায্য করতে পারি।'
            : 'I can help with dues, commissions, subscriber counts, and summaries.');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function wrap(Reseller $reseller, string $query, string $reply, array $extra = []): array
    {
        $this->audit->log('reseller', $query, $reply, 'reseller.assistant', 'reseller', false, null, $reseller);

        return array_merge([
            'reply' => $reply,
            'advisory' => true,
        ], $extra);
    }

    private function dueSummary(Reseller $reseller, bool $bn): string
    {
        $customerIds = Customer::withoutGlobalScopes()->where('reseller_id', $reseller->id)->pluck('id');
        $due = Invoice::withoutGlobalScopes()
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
            ->get()
            ->sum(fn (Invoice $i) => $i->balanceDue());

        return $bn
            ? 'আপনার গ্রাহকদের মোট বকেয়া '.number_format((float) $due, 0).' BDT।'
            : 'Total due from your subscribers: '.number_format((float) $due, 0).' BDT.';
    }

    private function commissionSummary(Reseller $reseller, bool $bn): string
    {
        $pending = (float) ResellerCommission::withoutGlobalScopes()
            ->where('reseller_id', $reseller->id)
            ->where('status', 'pending')
            ->sum('commission_amount');

        return $bn
            ? 'পেন্ডিং কমিশন '.number_format($pending, 0).' BDT।'
            : 'Pending commission: '.number_format($pending, 0).' BDT.';
    }

    /**
     * @param  list<string>  $needles
     */
    private function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
