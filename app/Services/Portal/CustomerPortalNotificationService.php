<?php

namespace App\Services\Portal;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Outage;
use App\Models\Payment;
use App\Models\SignalAlert;
use Carbon\Carbon;

final class CustomerPortalNotificationService
{
    /**
     * @return list<array{id: string, type: string, title: string, message: string, severity: string, at: string}>
     */
    public function feed(Customer $customer, int $limit = 30): array
    {
        $items = [];

        foreach ($this->dueInvoiceAlerts($customer) as $row) {
            $items[] = $row;
        }

        foreach ($this->outageAlerts($customer) as $row) {
            $items[] = $row;
        }

        foreach ($this->opticalAlerts($customer) as $row) {
            $items[] = $row;
        }

        foreach ($this->paymentAlerts($customer) as $row) {
            $items[] = $row;
        }

        if ($customer->service_expires_at !== null && $customer->service_expires_at->isBetween(now(), now()->addDays(7))) {
            $items[] = [
                'id' => 'expiry-'.$customer->id,
                'type' => 'service_expiry',
                'title' => 'Service expiry reminder',
                'message' => 'Your package expires on '.$customer->service_expires_at->format('d M Y').'.',
                'severity' => 'warning',
                'at' => now()->toIso8601String(),
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        return array_slice($items, 0, $limit);
    }

    public function unreadCount(Customer $customer): int
    {
        $count = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial'])
            ->whereColumn('total', '>', 'amount_paid')
            ->count();

        $count += Outage::query()
            ->currentlyActive()
            ->forCustomerArea($customer->area_id)
            ->where('tenant_id', $customer->tenant_id)
            ->count();

        $deviceIds = $customer->devices()->where('type', 'onu')->pluck('id');
        if ($deviceIds->isNotEmpty()) {
            $count += SignalAlert::query()
                ->whereIn('device_id', $deviceIds)
                ->where('status', 'open')
                ->count();
        }

        $count += Payment::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['completed', 'success', 'paid'])
            ->where('created_at', '>=', now()->subDays(14))
            ->count();

        if ($customer->service_expires_at !== null && $customer->service_expires_at->isBetween(now(), now()->addDays(7))) {
            $count++;
        }

        return $count;
    }

    /**
     * @return array{total:int,action_required:int,payments:int,outages:int,service_expiry:int,latest_at:?string}
     */
    public function summary(Customer $customer): array
    {
        $items = $this->feed($customer, 50);

        return [
            'total' => count($items),
            'action_required' => count(array_filter(
                $items,
                fn (array $item): bool => in_array($item['severity'], ['danger', 'warning'], true)
            )),
            'payments' => count(array_filter(
                $items,
                fn (array $item): bool => $item['type'] === 'payment_success'
            )),
            'outages' => count(array_filter(
                $items,
                fn (array $item): bool => $item['type'] === 'maintenance'
            )),
            'service_expiry' => count(array_filter(
                $items,
                fn (array $item): bool => $item['type'] === 'service_expiry'
            )),
            'latest_at' => $items[0]['at'] ?? null,
        ];
    }

    /**
     * @return list<array{id: string, type: string, title: string, message: string, severity: string, at: string}>
     */
    private function dueInvoiceAlerts(Customer $customer): array
    {
        $rows = [];
        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        foreach ($invoices as $inv) {
            $due = round((float) $inv->total - (float) $inv->amount_paid, 2);
            if ($due <= 0) {
                continue;
            }

            $overdue = $inv->due_date && Carbon::parse($inv->due_date)->isPast();
            $rows[] = [
                'id' => 'inv-'.$inv->id,
                'type' => $overdue ? 'bill_overdue' : 'bill_due',
                'title' => $overdue ? 'Bill overdue' : 'Bill due',
                'message' => sprintf('Invoice %s — %s BDT due%s.', $inv->invoice_number, number_format($due, 2), $overdue ? ' (overdue)' : ''),
                'severity' => $overdue ? 'danger' : 'warning',
                'at' => ($inv->due_date ?? $inv->issue_date)?->toIso8601String() ?? now()->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: string, type: string, title: string, message: string, severity: string, at: string}>
     */
    private function outageAlerts(Customer $customer): array
    {
        return Outage::query()
            ->currentlyActive()
            ->forCustomerArea($customer->area_id)
            ->where('tenant_id', $customer->tenant_id)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get()
            ->map(fn (Outage $o): array => [
                'id' => 'outage-'.$o->id,
                'type' => 'maintenance',
                'title' => $o->title,
                'message' => (string) ($o->description ?: 'Area maintenance or outage in progress.'),
                'severity' => 'warning',
                'at' => $o->started_at?->toIso8601String() ?? now()->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: string, type: string, title: string, message: string, severity: string, at: string}>
     */
    private function opticalAlerts(Customer $customer): array
    {
        $deviceIds = $customer->devices()->where('type', 'onu')->pluck('id');
        if ($deviceIds->isEmpty()) {
            return [];
        }

        return SignalAlert::query()
            ->whereIn('device_id', $deviceIds)
            ->where('status', 'open')
            ->orderByDesc('detected_at')
            ->limit(5)
            ->get()
            ->map(fn (SignalAlert $a): array => [
                'id' => 'optical-'.$a->id,
                'type' => 'optical',
                'title' => $a->title,
                'message' => (string) ($a->message ?: 'Optical signal issue detected on your line.'),
                'severity' => $a->severity === 'critical' ? 'danger' : 'warning',
                'at' => $a->detected_at?->toIso8601String() ?? now()->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: string, type: string, title: string, message: string, severity: string, at: string}>
     */
    private function paymentAlerts(Customer $customer): array
    {
        return Payment::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['completed', 'success', 'paid'])
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(fn (Payment $p): array => [
                'id' => 'pay-'.$p->id,
                'type' => 'payment_success',
                'title' => 'Payment received',
                'message' => sprintf('Thank you — %s BDT received.', number_format((float) $p->amount, 2)),
                'severity' => 'success',
                'at' => $p->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ])
            ->all();
    }
}
