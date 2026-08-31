<?php

namespace App\Services;

use App\Events\PackagePurchased;
use App\Jobs\SendPaymentCollectionSmsJob;
use App\Jobs\SyncCustomerRouterStatusJob;
use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\PaymentSummary;
use App\Models\PPPSecrets;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Process a successful online payment, update billing records,
     * record collection summary, and activate the user on MikroTik.
     */
    public function processSuccessPayment(CustomersInfo $customer, float $amount, string $gateway, string $trxId): bool
    {
        DB::beginTransaction();
        try {
            $billing = $customer->billing;
            if (! $billing) {
                throw new \Exception("Billing information not found for customer {$customer->customer_unique_id}");
            }

            // Fetch reseller of the voucher if it was voucher recharge, or customer's reseller
            $reseller = null;
            if ($gateway === 'voucher') {
                $voucher = \App\Models\Voucher::where('code', $trxId)->first();
                if ($voucher) {
                    $reseller = $voucher->reseller;
                }
            }
            if (! $reseller && $customer->reseller_id) {
                $reseller = $customer->reseller;
            }

            $useProrated = $reseller ? (bool)$reseller->getSetting('use_prorated_validity', true) : true;
            $allowPartialActivation = $reseller ? (bool)$reseller->getSetting('allow_partial_activation', false) : false;

            $isProratedApplied = false;
            $rent = (float) $billing->monthly_rent ?: 1.00;
            $currentDue = (float) $billing->due_amount;

            if ($useProrated && $amount < $rent && $amount > 0) {
                // Prorated daily validity calculation
                $proratedDays = (int) round(($amount / $rent) * 30);
                if ($proratedDays < 1) {
                    $proratedDays = 1;
                }

                $baseDate = today();
                if ($billing->auto_disable_date) {
                    $currentExpire = Carbon::parse($billing->auto_disable_date)->startOfDay();
                    if ($currentExpire->gt(today())) {
                        $baseDate = $currentExpire;
                    }
                }

                $expireDate = $baseDate->addDays($proratedDays)->format('Y-m-d');
                $newDue = 0.00;
                $advancePaid = 0.00;
                $isProratedApplied = true;
            } else {
                // Standard monthly billing calculations
                $newDue = max(0.00, $currentDue - $amount);
                $advancePaid = max(0.00, $amount - $currentDue);
                $expireDate = $billing->auto_disable_date;

                if ($newDue > 0) {
                    $extra_month = floor($newDue / $rent);
                    if ($extra_month < $billing->auto_disable_month) {
                        $expireDate = Carbon::parse($billing->auto_disable_date)
                            ->month(now()->month)->year(now()->year)
                            ->subMonths($extra_month)
                            ->format('Y-m-d');
                    } else {
                        $expireDate = Carbon::parse($billing->auto_disable_date)
                            ->month(now()->month)->year(now()->year)
                            ->subMonths($billing->auto_disable_month)
                            ->addMonths(1)
                            ->format('Y-m-d');
                    }
                } elseif ($advancePaid > 0) {
                    $extra_month = 1 + floor($advancePaid / $rent);
                    $expireDate = Carbon::parse($billing->auto_disable_date)
                        ->month(now()->month)->year(now()->year)
                        ->addMonths($extra_month)
                        ->format('Y-m-d');
                } else {
                    $expireDate = Carbon::parse($billing->auto_disable_date)
                        ->month(now()->month)->year(now()->year)
                        ->addMonths(1)
                        ->format('Y-m-d');
                }
            }

            // 2. Create Collection Record
            CollectionSummary::create([
                'customer_collection_unique_id' => $customer->customer_unique_id,
                'collection_date' => Carbon::now(),
                'collection_amount' => $amount,
                'collected_by' => 'Online Payment ('.strtoupper($gateway).')',
                'payment_type' => 'online',
                'payment_method' => $gateway,
                'transaction_id' => $trxId,
                'payment_status' => 'paid',
                'invoice_no' => CollectionSummary::nextInvoiceNo(),
                'bill_month' => Carbon::now()->format('F Y'),
            ]);

            // 3. Update BillingInfo
            $billing->update([
                'paid_amount' => $billing->paid_amount + $amount,
                'paid_date' => Carbon::now(),
                'auto_disable_date' => $expireDate,
                'extra_date' => null,
                'due_amount' => $newDue,
            ]);

            // 4. Create Monthly Payment Summary if not exists for the current month
            $summaryExists = PaymentSummary::where('customer_payment_unique_id', $customer->customer_unique_id)
                ->where('summary_date', Carbon::now()->firstOfMonth()->format('Y-m-d'))
                ->exists();

            if (! $summaryExists) {
                PaymentSummary::create([
                    'customer_payment_unique_id' => $customer->customer_unique_id,
                    'summary_date' => Carbon::now()->firstOfMonth()->format('Y-m-d'),
                    'monthly_rent' => $billing->monthly_rent,
                    'additional_charge' => $billing->additional_charge,
                    'vat' => $billing->vat,
                    'previous_due' => $billing->previous_due,
                    'advance' => $billing->advance,
                    'discount' => $billing->discount,
                ]);
            }

            // 5. Update customer status based on due and partial activation setting
            if ($newDue == 0 || $allowPartialActivation || $isProratedApplied) {
                $customer->status = 'active';
                $customer->disable_count = 0;
            } else {
                $customer->status = 'inactive';
            }
            $customer->save();

            // 6. Extend auto_disable_date if today is beyond it
            if (! $isProratedApplied && $billing->auto_disable_date) {
                $autoDisableDate = Carbon::parse($billing->auto_disable_date)->startOfDay();
                $autoDisableMonth = $billing->auto_disable_month;
                $disableDate = $autoDisableDate->copy()->addMonths($autoDisableMonth);

                if ($disableDate->lte(today())) {
                    while ($disableDate->lte(today())) {
                        $disableDate->addMonth();
                    }

                    $billing->auto_disable_date = $disableDate->copy()->subMonths($autoDisableMonth)->toDateString();
                    $billing->save();
                }
            }

            // Router activation runs after HTTP response (avoids Cloudflare timeout).
            $customerUniqueId = $customer->customer_unique_id;
            $routerStatus = $customer->status === 'active' ? 'active' : 'disable';
            $shouldSyncRouter = (bool) $customer->pppUser;

            DB::commit();

            $smsData = [
                'recipient' => $customer->mobile,
                'customer_id' => $customer->customer_unique_id,
                'customer_name' => $customer->customer_name,
                'collection_amount' => $amount,
                'ip_or_user_name' => $customer->pppUser->username ?? '',
                'due_amount' => $newDue,
                'company_name' => siteUrlSettings('site_name') ?: config('app.name'),
            ];

            $customerForEvent = $customer->fresh(['billing', 'pppUser', 'official']);

            if ($shouldSyncRouter) {
                SyncCustomerRouterStatusJob::dispatch($customerUniqueId, $routerStatus)->afterResponse();
            }

            dispatch(function () use ($customerForEvent, $amount, $smsData) {
                event(new PackagePurchased($customerForEvent, $amount));
                SendPaymentCollectionSmsJob::dispatchSync($smsData);
            })->afterResponse();

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PaymentService processing failed: '.$e->getMessage());
            throw $e;
        }
    }
}
