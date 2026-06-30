<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SupportTicket;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use Carbon\Carbon;

final class AiChurnScoringService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function scoredCustomers(int $tenantId, int $limit = 25): array
    {
        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::ACTIVE)
            ->with(['package:id,name'])
            ->limit(200)
            ->get();

        $rows = [];
        foreach ($customers as $customer) {
            $score = $this->scoreCustomer($customer);
            if ($score['score'] >= 40) {
                $rows[] = $score;
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function scoreCustomer(Customer $customer): array
    {
        $score = 0;
        $reasons = [];

        $due = (float) $customer->openInvoiceBalance();
        if ($due > 0) {
            $score += min(35, (int) round($due / 100));
            $reasons[] = 'Open balance '.number_format($due, 0).' BDT';
        }

        if ($customer->service_expires_at !== null) {
            $days = now()->startOfDay()->diffInDays(Carbon::parse($customer->service_expires_at)->startOfDay(), false);
            if ($days <= 7) {
                $score += 30;
                $reasons[] = 'Service expires in '.$days.' day(s)';
            }
        }

        $openTickets = SupportTicket::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();
        if ($openTickets > 0) {
            $score += min(20, $openTickets * 8);
            $reasons[] = $openTickets.' open ticket(s)';
        }

        $overdueInvoices = Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
            ->where('due_date', '<', now()->toDateString())
            ->count();
        if ($overdueInvoices > 0) {
            $score += 15;
            $reasons[] = $overdueInvoices.' overdue invoice(s)';
        }

        if ($customer->ppp_last_seen_at !== null && $customer->ppp_last_seen_at->lt(now()->subDays(14))) {
            $score += 10;
            $reasons[] = 'No PPP session for 14+ days';
        }

        $score = min(100, $score);

        return [
            'customer_id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'name' => $customer->name,
            'package' => $customer->package?->name,
            'score' => $score,
            'risk' => $score >= 70 ? 'high' : ($score >= 50 ? 'medium' : 'low'),
            'reasons' => $reasons,
        ];
    }
}
