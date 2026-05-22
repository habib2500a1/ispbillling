<?php

namespace App\Services\Subscribers;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Models\CustomerLineActivation;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Inventory\InvoiceHardwareLineService;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use App\Support\CustomerBalanceDue;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerLineActivationService
{
    public function __construct(
        private readonly CustomerOnuAutoProvisionService $onuProvisioner,
        private readonly InvoiceHardwareLineService $hardwareLines,
    ) {}

    /**
     * Staff assigns a new line: optional device, charges on invoice, optional wallet debit.
     *
     * @param  array{
     *     line_charge?: float|null,
     *     device_id?: int|null,
     *     device_charge?: float|null,
     *     use_wallet?: bool,
     *     cash_amount?: float|null,
     *     cash_method?: string|null,
     *     notes?: string|null,
     *     allow_device_only?: bool,
     * }  $input
     * @return array{
     *     activation: CustomerLineActivation,
     *     invoice: ?Invoice,
     *     device: ?Device,
     *     wallet_applied: float,
     *     cash_collected: float,
     *     remaining_due: float,
     *     message: string,
     * }
     */
    public function activate(Customer $customer, array $input, ?User $performer = null): array
    {
        $lineCharge = round(max(0, (float) ($input['line_charge'] ?? 0)), 2);
        $deviceId = filled($input['device_id'] ?? null) ? (int) $input['device_id'] : null;
        $deviceCharge = round(max(0, (float) ($input['device_charge'] ?? 0)), 2);
        $useWallet = (bool) ($input['use_wallet'] ?? true);
        $cashAmount = round(max(0, (float) ($input['cash_amount'] ?? 0)), 2);
        $cashMethod = filled($input['cash_method'] ?? null)
            ? (string) $input['cash_method']
            : PaymentGateway::CASH;
        $notes = filled($input['notes'] ?? null) ? (string) $input['notes'] : null;
        $allowDeviceOnly = (bool) ($input['allow_device_only'] ?? false);

        if ($lineCharge <= 0 && $deviceCharge <= 0 && $deviceId === null) {
            throw ValidationException::withMessages([
                'line_charge' => 'Enter a line charge, device sale price, or select a device.',
            ]);
        }

        if (! $allowDeviceOnly && $lineCharge <= 0 && $deviceCharge <= 0 && $deviceId !== null) {
            throw ValidationException::withMessages([
                'line_charge' => 'Enter line charge or device sale amount.',
            ]);
        }

        $performer ??= auth()->user();

        return DB::transaction(function () use (
            $customer,
            $lineCharge,
            $deviceId,
            $deviceCharge,
            $useWallet,
            $notes,
            $performer,
            $cashAmount,
            $cashMethod,
            $allowDeviceOnly,
        ): array {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $device = null;
            $cashCollected = 0.0;

            if ($deviceId !== null) {
                $device = $this->assignDevice($customer, $deviceId);
                if ($deviceCharge <= 0) {
                    $deviceCharge = round((float) ($device->lease_monthly_fee ?? 0), 2);
                    if ($deviceCharge <= 0 && $device->product_id) {
                        $device->loadMissing('catalogProduct');
                        $deviceCharge = round((float) ($device->catalogProduct?->effectiveSellPrice() ?? 0), 2);
                    }
                }
            }

            $invoice = null;
            $walletApplied = 0.0;
            $walletPaymentId = null;

            $totalCharge = round($lineCharge + $deviceCharge, 2);

            if ($deviceId !== null && $totalCharge <= 0 && $allowDeviceOnly) {
                $meta = is_array($customer->meta) ? $customer->meta : [];
                $meta['installation_status'] = 'completed';
                $meta['assigned_device_id'] = $device->id;
                $meta['assigned_device_label'] = $device->display_name ?: $device->serial_number;
                if ($lineCharge > 0) {
                    $meta['installation_charge'] = $lineCharge;
                }
                $customer->forceFill(['meta' => $meta])->save();

                $activation = CustomerLineActivation::query()->create([
                    'tenant_id' => $customer->tenant_id,
                    'customer_id' => $customer->id,
                    'device_id' => $device->id,
                    'performed_by' => $performer?->id,
                    'line_charge' => $lineCharge,
                    'device_charge' => $deviceCharge,
                    'total_charged' => 0,
                    'wallet_applied' => 0,
                    'cash_collected' => 0,
                    'notes' => $notes,
                    'meta' => ['device_only' => true],
                ]);

                SyncCustomerNetworkAccessJob::dispatch(
                    (int) $customer->tenant_id,
                    (int) $customer->id,
                )->afterResponse();

                return [
                    'activation' => $activation->fresh(['device']),
                    'invoice' => null,
                    'device' => $device->fresh(),
                    'wallet_applied' => 0.0,
                    'cash_collected' => 0.0,
                    'remaining_due' => 0.0,
                    'message' => 'Device linked: '.($device->display_name ?: $device->serial_number),
                ];
            }

            if ($totalCharge > 0) {
                $invoice = $this->createActivationInvoice(
                    $customer,
                    $lineCharge,
                    $device,
                    $deviceCharge,
                    $notes,
                );

                if ($useWallet) {
                    $walletApplied = $this->applyWalletToInvoice($customer, $invoice, $performer);
                    if ($walletApplied > 0) {
                        $walletPaymentId = Payment::query()
                            ->where('customer_id', $customer->id)
                            ->where('invoice_id', $invoice->id)
                            ->where('payment_type', PaymentType::WALLET_APPLY)
                            ->orderByDesc('id')
                            ->value('id');
                    }
                }

                if ($cashAmount > 0) {
                    $cashCollected = $this->applyCashToInvoice(
                        $customer,
                        $invoice,
                        $cashAmount,
                        $cashMethod,
                        $performer,
                    );
                }
            }

            $meta = is_array($customer->meta) ? $customer->meta : [];
            if ($lineCharge > 0) {
                $meta['installation_charge'] = $lineCharge;
            }
            $meta['installation_status'] = 'completed';
            if ($device !== null) {
                $meta['assigned_device_id'] = $device->id;
                $meta['assigned_device_label'] = $device->display_name ?: $device->serial_number;
            }

            $customer->forceFill(['meta' => $meta])->save();

            $activation = CustomerLineActivation::query()->create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'device_id' => $device?->id,
                'invoice_id' => $invoice?->id,
                'wallet_payment_id' => $walletPaymentId,
                'performed_by' => $performer?->id,
                'line_charge' => $lineCharge,
                'device_charge' => $deviceCharge,
                'total_charged' => $totalCharge,
                'wallet_applied' => $walletApplied,
                'cash_collected' => $cashCollected,
                'notes' => $notes,
                'meta' => [
                    'device_serial' => $device?->serial_number,
                    'device_mac' => $device?->mac_address,
                    'device_type' => $device?->type,
                    'invoice_number' => $invoice?->invoice_number,
                    'remaining_due' => $invoice?->fresh()?->balanceDue() ?? 0,
                ],
            ]);

            SyncCustomerNetworkAccessJob::dispatch(
                (int) $customer->tenant_id,
                (int) $customer->id,
            )->afterResponse();

            CustomerBalanceDue::refreshMetaAfterPayment($customer->fresh());

            $remainingDue = $invoice?->fresh()?->balanceDue() ?? 0.0;
            $message = $this->buildMessage($invoice, $device, $walletApplied, $cashCollected, $totalCharge, $remainingDue);

            return [
                'activation' => $activation->fresh(['device', 'invoice', 'walletPayment']),
                'invoice' => $invoice?->fresh(),
                'device' => $device?->fresh(),
                'wallet_applied' => $walletApplied,
                'cash_collected' => $cashCollected,
                'remaining_due' => $remainingDue,
                'message' => $message,
            ];
        });
    }

    /**
     * Whether create/register form should run line activation after save.
     *
     * @param  array<string, mixed>  $formState
     */
    public function shouldActivateFromRegisterForm(array $formState): bool
    {
        if (! (bool) ($formState['apply_line_charges'] ?? false)) {
            return false;
        }

        $lineCharge = round(max(0, (float) (Arr::get($formState, 'meta.installation_charge') ?? 0)), 2);
        $deviceCharge = round(max(0, (float) ($formState['line_device_charge'] ?? 0)), 2);
        $deviceId = filled($formState['onu_device_pick'] ?? null);

        return $lineCharge > 0 || $deviceCharge > 0 || $deviceId;
    }

    /**
     * @param  array<string, mixed>  $formState
     * @return array<string, mixed>
     */
    public function inputFromRegisterForm(array $formState): array
    {
        return [
            'line_charge' => round(max(0, (float) (Arr::get($formState, 'meta.installation_charge') ?? 0)), 2),
            'device_id' => filled($formState['onu_device_pick'] ?? null) ? (int) $formState['onu_device_pick'] : null,
            'device_charge' => round(max(0, (float) ($formState['line_device_charge'] ?? 0)), 2),
            'use_wallet' => (bool) ($formState['use_wallet_on_register'] ?? true),
            'cash_amount' => round(max(0, (float) ($formState['line_cash_amount'] ?? 0)), 2),
            'cash_method' => (string) ($formState['line_cash_method'] ?? PaymentGateway::CASH),
            'notes' => 'New subscriber registration',
            'allow_device_only' => true,
        ];
    }

    public function defaultLineCharge(Customer $customer): float
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        if (isset($meta['installation_charge']) && (float) $meta['installation_charge'] > 0) {
            return round((float) $meta['installation_charge'], 2);
        }

        $customer->loadMissing('package');

        return round((float) ($customer->package?->setup_fee ?? 0), 2);
    }

    private function assignDevice(Customer $customer, int $deviceId): Device
    {
        $device = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->find($deviceId);

        if ($device === null) {
            throw ValidationException::withMessages([
                'device_id' => 'Device not found.',
            ]);
        }

        if ($device->customer_id !== null && (int) $device->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'device_id' => 'Device is already assigned to another subscriber.',
            ]);
        }

        if ($device->type === 'onu') {
            $linked = $this->onuProvisioner->assignOnuToCustomer($customer, $device->id, 'line_activation');
            if ($linked === null) {
                throw ValidationException::withMessages([
                    'device_id' => 'Could not assign ONU to this subscriber.',
                ]);
            }

            return $linked->fresh();
        }

        $device->forceFill([
            'customer_id' => $customer->id,
            'status' => 'assigned',
            'lease_started_at' => $device->lease_started_at ?? now(),
        ])->save();

        return $device->fresh();
    }

    private function createActivationInvoice(
        Customer $customer,
        float $lineCharge,
        ?Device $device,
        float $deviceCharge,
        ?string $notes,
    ): Invoice {
        $invoice = Invoice::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(min(7, max(1, (int) ($customer->grace_period_days ?? 7))))->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'sd_amount' => 0,
            'withholding_amount' => 0,
            'discount_amount' => 0,
            'coupon_discount_amount' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'status' => 'open',
            'notes' => 'Line activation'.($notes ? ' — '.$notes : ''),
        ]);

        $sort = 0;

        if ($lineCharge > 0) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'item_type' => 'installation_fee',
                'description' => 'New line / connection charge',
                'quantity' => 1,
                'unit_price' => $lineCharge,
                'line_total' => $lineCharge,
                'sort_order' => $sort++,
                'meta' => ['source' => 'line_activation'],
            ]);
        }

        if ($device !== null && $deviceCharge > 0) {
            $this->hardwareLines->linkDeviceLine($invoice->fresh(), $device, $deviceCharge);
        } elseif ($device !== null) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'item_type' => 'hardware',
                'description' => 'Device issued — '.($device->display_name ?: $device->serial_number ?: 'Device #'.$device->id),
                'quantity' => 1,
                'unit_price' => 0,
                'line_total' => 0,
                'sort_order' => $sort++,
                'device_id' => $device->id,
                'product_id' => $device->product_id,
                'meta' => [
                    'device_id' => $device->id,
                    'source' => 'line_activation',
                ],
            ]);
            InvoiceCalculator::recalculate($invoice->fresh());
        }

        if ($lineCharge > 0 && ($device === null || $deviceCharge <= 0)) {
            InvoiceCalculator::recalculate($invoice->fresh());
        }

        return $invoice->fresh();
    }

    private function applyWalletToInvoice(Customer $customer, Invoice $invoice, ?User $performer): float
    {
        $customer = $customer->fresh();
        $invoice = $invoice->fresh();
        $due = $invoice->balanceDue();
        $balance = (float) $customer->account_balance;

        if ($due <= 0.009 || $balance <= 0.009) {
            return 0.0;
        }

        $amount = round(min($balance, $due), 2);

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'payment_type' => PaymentType::WALLET_APPLY,
            'amount' => $amount,
            'method' => PaymentGateway::OTHER,
            'reference' => 'wallet-line-activation',
            'notes' => 'Wallet applied when staff assigned new line',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $performer?->id,
            'meta' => ['source' => 'line_activation'],
        ]);

        return $amount;
    }

    private function applyCashToInvoice(
        Customer $customer,
        Invoice $invoice,
        float $cashAmount,
        string $method,
        ?User $performer,
    ): float {
        $invoice = $invoice->fresh();
        $due = $invoice->balanceDue();

        if ($due <= 0.009) {
            return 0.0;
        }

        $amount = round(min($cashAmount, $due), 2);
        if ($amount <= 0) {
            return 0.0;
        }

        Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'payment_type' => PaymentType::PAYMENT,
            'amount' => $amount,
            'method' => $method,
            'reference' => 'line-activation-cash',
            'notes' => 'Cash collected when assigning line',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $performer?->id,
            'meta' => ['source' => 'line_activation'],
        ]);

        return $amount;
    }

    private function buildMessage(
        ?Invoice $invoice,
        ?Device $device,
        float $walletApplied,
        float $cashCollected,
        float $totalCharge,
        float $remainingDue,
    ): string {
        $parts = [];

        if ($device !== null) {
            $parts[] = 'Device: '.($device->display_name ?: $device->serial_number ?: '#'.$device->id);
        }

        if ($invoice !== null) {
            $parts[] = 'Invoice '.$invoice->invoice_number.' — '.number_format($totalCharge, 2).' BDT';
        }

        if ($walletApplied > 0) {
            $parts[] = 'Wallet '.number_format($walletApplied, 2).' BDT';
        }

        if ($cashCollected > 0) {
            $parts[] = 'Cash '.number_format($cashCollected, 2).' BDT';
        }

        if ($remainingDue > 0.009) {
            $parts[] = 'Due '.number_format($remainingDue, 2).' BDT';
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Line activation recorded.';
    }
}
