<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\SupportTicket;
use App\Services\Billing\PackageChangeQuoteService;
use App\Services\Billing\ScheduledPackageChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalPackageController extends Controller
{
    public function __construct(
        private readonly PackageChangeQuoteService $quotes,
        private readonly ScheduledPackageChangeService $scheduledChanges,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user('customer')->load(['package', 'pendingPackage']);

        $packages = Package::query()
            ->publicCatalog()
            ->orderBy('price_monthly')
            ->get();

        $quotesByPackage = [];
        foreach ($packages as $pkg) {
            if ((int) $pkg->id === (int) $customer->package_id) {
                continue;
            }
            $quotesByPackage[$pkg->id] = $this->quotes->quote($customer, $pkg);
        }

        return view('portal.packages.index', [
            'customer' => $customer,
            'packages' => $packages,
            'currentPackageId' => $customer->package_id,
            'quotesByPackage' => $quotesByPackage,
            'openBalance' => $customer->openInvoiceBalance(),
            'mustClearBalance' => (bool) config('billing.portal_package_change_requires_clear_balance', true),
        ]);
    }

    public function requestChange(Request $request): RedirectResponse
    {
        $customer = $request->user('customer')->load(['package', 'pendingPackage']);

        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                Rule::exists('packages', 'id')->where(fn ($q) => $q->where('is_active', true)->where('show_on_website', true)),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $package = Package::query()->findOrFail($validated['package_id']);

        if ((int) $package->id === (int) $customer->package_id) {
            return back()->withErrors(['package_id' => 'You are already on this package.']);
        }

        if (config('billing.portal_package_change_requires_clear_balance', true)) {
            $openBalance = $customer->openInvoiceBalance();
            if ($openBalance > 0.009) {
                return back()
                    ->withErrors([
                        'package_id' => 'Pay your outstanding bill ('.number_format($openBalance, 2).' BDT) before changing package.',
                    ])
                    ->withInput();
            }
        }

        $quote = $this->quotes->quote($customer, $package);

        $invoice = null;
        $scheduledDate = null;

        if ($quote['is_upgrade']
            && config('billing.portal_instant_upgrade', true)
            && $quote['net_due'] > 0) {
            $invoice = $this->quotes->createUpgradeInvoice($customer, $package);
        } elseif ($quote['is_upgrade'] && $quote['net_due'] <= 0) {
            $this->quotes->applyPackageChange($customer, $package);
        } elseif (! $quote['is_upgrade'] && config('billing.downgrade_next_cycle', true)) {
            $scheduledDate = $this->scheduledChanges->scheduleForNextCycle($customer, $package);
        }

        $ticket = new SupportTicket([
            'customer_id' => $customer->id,
            'channel' => 'portal',
            'department' => 'billing',
            'priority' => $quote['is_upgrade'] ? 'medium' : 'low',
            'issue_type' => 'billing',
            'subject' => 'Package change: '.$package->name,
            'description' => trim(implode("\n", array_filter([
                "Requested: {$package->name} ({$package->download_mbps} Mbps, ".number_format((float) $package->price_monthly, 2).' BDT/mo).',
                "Quote — credit: {$quote['credit_amount']} BDT, new charge: {$quote['new_charge']} BDT, net: {$quote['net_due']} BDT ({$quote['effective_label']}).",
                $scheduledDate ? 'Scheduled downgrade effective: '.$scheduledDate->format('d M Y') : null,
                $invoice ? 'Upgrade invoice: '.$invoice->invoice_number : null,
                $validated['note'] ?? '',
            ]))),
            'status' => 'open',
        ]);
        $ticket->save();

        if ($invoice instanceof Invoice) {
            return redirect()
                ->route('portal.invoices.show', $invoice)
                ->with('status', 'Upgrade invoice created. Pay '.number_format($quote['net_due'], 2).' BDT to activate '.$package->name.'. Ticket #'.$ticket->ticket_number);
        }

        if ($scheduledDate !== null) {
            $message = 'Downgrade to '.$package->name.' scheduled for '.$scheduledDate->format('d M Y').'. Ticket #'.$ticket->ticket_number;
        } elseif ($quote['net_due'] <= 0 && $quote['is_upgrade']) {
            $message = 'Package upgraded to '.$package->name.'. Ticket #'.$ticket->ticket_number;
        } else {
            $message = 'Package change request submitted. Ticket #'.$ticket->ticket_number.' — our team will contact you.';
        }

        return redirect()
            ->route('portal.packages.index')
            ->with('status', $message);
    }
}
