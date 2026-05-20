<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\SupportTicket;
use App\Services\Billing\PackageChangeQuoteService;
use App\Services\Billing\ScheduledPackageChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function index(Request $request, PackageChangeQuoteService $quotes): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user()->load(['package', 'pendingPackage']);

        $packages = Package::query()
            ->publicCatalog()
            ->orderBy('price_monthly')
            ->get()
            ->map(function (Package $pkg) use ($customer, $quotes) {
                $row = [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'download_mbps' => $pkg->download_mbps,
                    'upload_mbps' => $pkg->upload_mbps,
                    'price_monthly' => (float) $pkg->price_monthly,
                    'is_current' => (int) $pkg->id === (int) $customer->package_id,
                ];
                if (! $row['is_current']) {
                    $row['quote'] = $quotes->quote($customer, $pkg);
                }

                return $row;
            });

        return response()->json([
            'current_package_id' => $customer->package_id,
            'pending_package' => $customer->pendingPackage ? [
                'id' => $customer->pendingPackage->id,
                'name' => $customer->pendingPackage->name,
            ] : null,
            'data' => $packages,
        ]);
    }

    public function requestChange(
        Request $request,
        PackageChangeQuoteService $quotes,
        ScheduledPackageChangeService $scheduledChanges,
    ): JsonResponse {
        /** @var Customer $customer */
        $customer = $request->user()->load(['package', 'pendingPackage']);

        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                Rule::exists('packages', 'id')->where(fn ($q) => $q->where('is_active', true)->where('show_on_website', true)),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $package = Package::query()->findOrFail((int) $validated['package_id']);

        if ((int) $package->id === (int) $customer->package_id) {
            return response()->json(['message' => 'You are already on this package.'], 422);
        }

        if (config('billing.portal_package_change_requires_clear_balance', true)) {
            $openBalance = $customer->openInvoiceBalance();
            if ($openBalance > 0.009) {
                return response()->json([
                    'message' => 'Pay outstanding bill ('.number_format($openBalance, 2).' BDT) before changing package.',
                ], 422);
            }
        }

        $quote = $quotes->quote($customer, $package);
        $invoice = null;
        $scheduledDate = null;

        if ($quote['is_upgrade']
            && config('billing.portal_instant_upgrade', true)
            && $quote['net_due'] > 0) {
            $invoice = $quotes->createUpgradeInvoice($customer, $package);
        } elseif ($quote['is_upgrade'] && $quote['net_due'] <= 0) {
            $quotes->applyPackageChange($customer, $package);
        } elseif (! $quote['is_upgrade'] && config('billing.downgrade_next_cycle', true)) {
            $scheduledDate = $scheduledChanges->scheduleForNextCycle($customer, $package);
        }

        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'channel' => 'app',
            'department' => 'billing',
            'priority' => $quote['is_upgrade'] ? 'medium' : 'low',
            'issue_type' => 'billing',
            'subject' => 'Package change: '.$package->name,
            'description' => trim(implode("\n", array_filter([
                "Requested via mobile app: {$package->name}",
                $validated['note'] ?? '',
            ]))),
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Package change request submitted.',
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ],
            'invoice_id' => $invoice?->id,
            'scheduled_date' => $scheduledDate?->toDateString(),
        ], 201);
    }
}
