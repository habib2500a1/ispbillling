<?php

namespace App\Console\Commands;

use App\Http\Controllers\PipraPayPaymentController;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\PipraPayCheckoutService;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class ReconcilePipraPayCommand extends Command
{
    protected $signature = 'piprapay:reconcile
        {reference : PipraPay bp_id (pp_id) OR gateway trx reference}
        {--customer= : Customer ID (required for manual trx reference)}
        {--amount= : Amount in BDT (required for manual trx reference)}
        {--invoice= : Invoice ID (optional, for invoice payment)}
        {--wallet : Wallet top-up (default if no invoice)}
        {--verify-only : Only verify with PipraPay API, do not record}';

    protected $description = 'Verify and record a missed PipraPay payment (by bp_id or manual trx reference)';

    public function handle(): int
    {
        if (! PipraPayCheckoutService::isEnabled()) {
            $this->error('PipraPay is disabled.');

            return self::FAILURE;
        }

        $reference = trim($this->argument('reference'));
        $service = PipraPayCheckoutService::fromConfig();

        try {
            $verified = $service->verifyPayment($reference);
        } catch (\Throwable $e) {
            $verified = null;
            $this->warn('PipraPay verify API: '.$e->getMessage());
        }

        if ($verified !== null && $service->isPaymentSuccessful($verified)) {
            $this->info('PipraPay reports payment successful.');
            $this->line(json_encode($verified, JSON_PRETTY_PRINT));

            if ($this->option('verify-only')) {
                return self::SUCCESS;
            }

            $request = Request::create('/piprapay/success', 'GET', [
                'pp_id' => $reference,
                'order_id' => $service->orderIdFromVerify($verified, $reference),
            ]);

            $response = app(PipraPayPaymentController::class)->success($request);
            $this->info('Recorded via success flow. Redirect: '.$response->getTargetUrl());

            return self::SUCCESS;
        }

        if ($this->option('verify-only')) {
            $this->error('Payment not verified via PipraPay API.');

            return self::FAILURE;
        }

        return $this->recordManual($reference);
    }

    private function recordManual(string $reference): int
    {
        $customerId = (int) $this->option('customer');
        $amount = (float) $this->option('amount');
        $invoiceId = $this->option('invoice') ? (int) $this->option('invoice') : null;

        if ($customerId <= 0 || $amount <= 0) {
            $this->error('For gateway trx references (e.g. bKash ID), pass --customer=ID and --amount=BDT.');
            $this->line('Find the PipraPay bp_id (pp_id) in your PipraPay merchant panel and run without --customer/--amount.');

            return self::FAILURE;
        }

        $customer = Customer::query()->withoutGlobalScopes()->find($customerId);
        if ($customer === null) {
            $this->error('Customer not found.');

            return self::FAILURE;
        }

        $exists = Payment::query()
            ->withoutGlobalScopes()
            ->where('gateway', PaymentGateway::PIPRAPAY)
            ->where(function ($q) use ($reference): void {
                $q->where('gateway_transaction_id', $reference)
                    ->orWhere('reference', $reference)
                    ->orWhere('meta->mfs_trx_id', $reference);
            })
            ->where('status', 'completed')
            ->exists();

        if ($exists) {
            $this->warn('Payment with this reference already exists.');

            return self::SUCCESS;
        }

        $paymentType = $invoiceId
            ? PaymentType::PAYMENT
            : ($this->option('wallet') || ! $invoiceId ? PaymentType::WALLET_DEPOSIT : PaymentType::PAYMENT);

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'amount' => round($amount, 2),
            'method' => PaymentGateway::PIPRAPAY,
            'gateway' => PaymentGateway::PIPRAPAY,
            'gateway_transaction_id' => $reference,
            'reference' => $reference,
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => $paymentType,
            'meta' => [
                'mfs_trx_id' => $reference,
                'source' => 'piprapay_manual_reconcile',
                'reconciled_at' => now()->toIso8601String(),
                'reconciled_by' => 'artisan',
            ],
        ]);

        PaymentProcessor::processCompletedPayment($payment->fresh(['customer', 'invoice']));

        $customer->refresh();
        $this->info("Payment #{$payment->id} recorded. Receipt: {$payment->receipt_number}");
        if ($paymentType === PaymentType::WALLET_DEPOSIT) {
            $this->info('Wallet balance: '.number_format((float) $customer->account_balance, 2).' BDT');
        }

        return self::SUCCESS;
    }
}
