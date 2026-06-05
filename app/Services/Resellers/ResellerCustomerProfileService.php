<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Reseller;
use App\Services\Billing\PackageChangeQuoteService;
use App\Services\Billing\ScheduledPackageChangeService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class ResellerCustomerProfileService
{
    public function __construct(
        private readonly PackageChangeQuoteService $packageQuotes,
        private readonly ScheduledPackageChangeService $scheduledPackages,
        private readonly ResellerPackageCatalogService $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function profileSnapshot(Customer $customer): array
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];

        return [
            'tags' => [
                'vip' => (bool) ($meta['tag_vip'] ?? false),
                'late_payer' => (bool) ($meta['tag_late_payer'] ?? false),
                'gaming' => (bool) ($meta['tag_gaming'] ?? false),
                'corporate' => (bool) ($meta['tag_corporate'] ?? false),
            ],
            'notify' => [
                'sms' => filter_var($meta['notify_sms'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'whatsapp' => filter_var($meta['notify_whatsapp'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'email' => filter_var($meta['notify_email'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
            'payment_plan' => [
                'enabled' => (bool) ($meta['payment_plan_enabled'] ?? false),
                'installment_bdt' => max(0, (float) ($meta['payment_plan_installment_bdt'] ?? 0)),
                'next_due_date' => $meta['payment_plan_next_due_date'] ?? null,
                'note' => trim((string) ($meta['payment_plan_note'] ?? '')),
            ],
            'network' => [
                'mac_binding' => trim((string) ($meta['mac_binding'] ?? '')),
                'onu_mac' => trim((string) ($meta['onu_mac'] ?? '')),
                'epon_port' => trim((string) ($meta['epon_port'] ?? '')),
                'vlan' => trim((string) ($meta['vlan'] ?? '')),
                'static_ip' => trim((string) ($meta['static_ip'] ?? '')),
            ],
            'location' => [
                'gps_lat' => $meta['gps_lat'] ?? null,
                'gps_lng' => $meta['gps_lng'] ?? null,
                'installation_date' => $meta['installation_date'] ?? null,
                'installation_photo_url' => filled($meta['installation_photo_path'] ?? null)
                    ? asset('storage/'.ltrim((string) $meta['installation_photo_path'], '/'))
                    : null,
            ],
            'pending_package_id' => $customer->pending_package_id,
            'pending_package_effective_date' => $customer->pending_package_effective_date?->format('d M Y'),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function applyProfileMeta(Customer $customer, array $validated, bool $merge = true): void
    {
        $meta = $merge && is_array($customer->meta) ? $customer->meta : [];

        foreach (['tag_vip', 'tag_late_payer', 'tag_gaming', 'tag_corporate'] as $tag) {
            if (array_key_exists($tag, $validated)) {
                if ($validated[$tag]) {
                    $meta[$tag] = true;
                } else {
                    unset($meta[$tag]);
                }
            }
        }

        foreach (['notify_sms', 'notify_whatsapp', 'notify_email'] as $notify) {
            if (array_key_exists($notify, $validated)) {
                $meta[$notify] = (bool) $validated[$notify];
            }
        }

        if (array_key_exists('payment_plan_enabled', $validated)) {
            if ($validated['payment_plan_enabled']) {
                $meta['payment_plan_enabled'] = true;
            } else {
                unset(
                    $meta['payment_plan_enabled'],
                    $meta['payment_plan_installment_bdt'],
                    $meta['payment_plan_next_due_date'],
                    $meta['payment_plan_note'],
                );
            }
        }

        foreach (['payment_plan_installment_bdt', 'payment_plan_note'] as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            if ($key === 'payment_plan_note') {
                if (filled($validated[$key])) {
                    $meta[$key] = trim((string) $validated[$key]);
                } else {
                    unset($meta[$key]);
                }
                continue;
            }
            $num = round((float) $validated[$key], 2);
            if ($num > 0) {
                $meta[$key] = $num;
            } else {
                unset($meta[$key]);
            }
        }

        if (array_key_exists('payment_plan_next_due_date', $validated)) {
            if (filled($validated['payment_plan_next_due_date'])) {
                $meta['payment_plan_next_due_date'] = (string) $validated['payment_plan_next_due_date'];
            } else {
                unset($meta['payment_plan_next_due_date']);
            }
        }

        foreach (['mac_binding', 'onu_mac', 'epon_port', 'vlan', 'static_ip'] as $netKey) {
            if (! array_key_exists($netKey, $validated)) {
                continue;
            }
            if (filled($validated[$netKey])) {
                $meta[$netKey] = trim((string) $validated[$netKey]);
            } else {
                unset($meta[$netKey]);
            }
        }

        foreach (['gps_lat', 'gps_lng'] as $coord) {
            if (! array_key_exists($coord, $validated)) {
                continue;
            }
            if ($validated[$coord] === null || $validated[$coord] === '') {
                unset($meta[$coord]);
                continue;
            }
            $meta[$coord] = round((float) $validated[$coord], 6);
        }

        if (array_key_exists('installation_date', $validated)) {
            if (filled($validated['installation_date'])) {
                $meta['installation_date'] = (string) $validated['installation_date'];
            } else {
                unset($meta['installation_date']);
            }
        }

        $customer->meta = $meta;
    }

    /**
     * @return array<string, mixed>
     */
    public function packageQuote(Reseller $reseller, Customer $customer, int $packageId): array
    {
        if (! $this->catalog->resellerMaySellPackage($reseller, $packageId)) {
            throw ValidationException::withMessages(['package_id' => 'Package not available on your account.']);
        }

        $package = Package::withoutGlobalScopes()
            ->where('tenant_id', $reseller->tenant_id)
            ->where('id', $packageId)
            ->firstOrFail();

        $quote = $this->packageQuotes->quote($customer->loadMissing('package'), $package);
        $wholesale = $this->catalog->wholesalePriceFor($reseller, $package);

        return array_merge($quote, [
            'package_id' => $packageId,
            'wholesale_monthly' => $wholesale,
            'estimated_margin_monthly' => $wholesale !== null
                ? max(0, round($this->catalog->customerBillPriceFor($package, $customer) - $wholesale, 2))
                : null,
        ]);
    }

    /**
     * @return array{message: string, invoice: ?\App\Models\Invoice, scheduled: bool}
     */
    public function applyPackageChange(Reseller $reseller, Customer $customer, int $packageId, bool $confirmUpgradeInvoice = true): array
    {
        if ((int) $customer->package_id === $packageId) {
            return ['message' => 'Subscriber is already on this package.', 'invoice' => null, 'scheduled' => false];
        }

        $quote = $this->packageQuote($reseller, $customer, $packageId);
        $package = Package::withoutGlobalScopes()->findOrFail($packageId);

        if ($quote['is_upgrade'] && $quote['net_due'] > 0 && $confirmUpgradeInvoice) {
            $invoice = $this->packageQuotes->createUpgradeInvoice($customer, $package);

            return [
                'message' => 'Upgrade invoice '.$invoice?->invoice_number.' created ('.number_format($quote['net_due'], 0).' BDT due). Package updates when paid.',
                'invoice' => $invoice,
                'scheduled' => false,
            ];
        }

        if ($quote['is_upgrade']) {
            $this->packageQuotes->applyPackageChange($customer, $package);
            $this->scheduledPackages->clearSchedule($customer->fresh());

            return [
                'message' => 'Package changed to '.$package->name.' immediately.',
                'invoice' => null,
                'scheduled' => false,
            ];
        }

        $effective = $this->scheduledPackages->scheduleForNextCycle($customer, $package);

        return [
            'message' => 'Downgrade to '.$package->name.' scheduled for '.$effective->format('d M Y').'.',
            'invoice' => null,
            'scheduled' => true,
        ];
    }

    /**
     * Monthly margin rows from paid/open invoices (last N months).
     *
     * @return list<array{month: string, label: string, retail: float, wholesale: float, margin: float, invoices: int}>
     */
    public function marginHistory(Reseller $reseller, Customer $customer, int $months = 12): array
    {
        $since = now()->subMonths($months - 1)->startOfMonth();
        $splitter = app(ResellerInvoiceSplitCalculator::class);

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('issue_date', '>=', $since->toDateString())
            ->whereNotIn('status', ['void', 'cancelled'])
            ->orderByDesc('issue_date')
            ->get();

        $byMonth = [];
        foreach ($invoices as $invoice) {
            $key = Carbon::parse($invoice->issue_date)->format('Y-m');
            if (! isset($byMonth[$key])) {
                $byMonth[$key] = ['retail' => 0.0, 'wholesale' => 0.0, 'margin' => 0.0, 'invoices' => 0];
            }
            $split = $splitter->splitForInvoice($invoice);
            $byMonth[$key]['retail'] += $split['retail'];
            $byMonth[$key]['wholesale'] += $split['wholesale'];
            $byMonth[$key]['margin'] += $split['margin'];
            $byMonth[$key]['invoices']++;
        }

        $rows = [];
        for ($i = 0; $i < $months; $i++) {
            $m = now()->subMonths($months - 1 - $i)->startOfMonth();
            $key = $m->format('Y-m');
            $bucket = $byMonth[$key] ?? ['retail' => 0.0, 'wholesale' => 0.0, 'margin' => 0.0, 'invoices' => 0];
            $rows[] = [
                'month' => $key,
                'label' => $m->format('M Y'),
                'retail' => round($bucket['retail'], 2),
                'wholesale' => round($bucket['wholesale'], 2),
                'margin' => round($bucket['margin'], 2),
                'invoices' => $bucket['invoices'],
            ];
        }

        return array_reverse($rows);
    }

    /**
     * @return array<int, string>
     */
    public static function tagFilterOptions(): array
    {
        return [
            'vip' => 'VIP',
            'late_payer' => 'Late payer',
            'gaming' => 'Gaming',
            'corporate' => 'Corporate',
        ];
    }

    public static function applyTagFilter($query, ?string $tag): void
    {
        if (! filled($tag)) {
            return;
        }

        match ($tag) {
            'vip' => $query->where('meta->tag_vip', true),
            'late_payer' => $query->where('meta->tag_late_payer', true),
            'gaming' => $query->where('meta->tag_gaming', true),
            'corporate' => $query->where('meta->tag_corporate', true),
            default => null,
        };
    }
}
