<?php

namespace App\Services\Resellers;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\Zone;
use App\Models\Invoice;
use App\Services\Billing\CustomerActivationBillingService;
use App\Services\Billing\InvoiceGenerator;
use App\Support\BillingDefaults;
use App\Support\CustomerCodeGenerator;
use App\Support\CustomerNetworkSync;
use App\Support\CustomerStatus;
use App\Support\ResellerCollectionPaymentMethod;
use App\Support\ResellerNewCustomerChargeMode;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use App\Support\SubscriberIdSettings;
use App\Support\SubscriberType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ResellerCustomerService
{
    public function assertOwned(Reseller $reseller, Customer $customer): void
    {
        if ((int) $customer->reseller_id !== (int) $reseller->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function formOptions(Reseller $reseller): array
    {
        $tenantId = (int) $reseller->tenant_id;
        $areas = Area::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
        $zones = Zone::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'area_id']);
        $filtered = app(ResellerTerritoryService::class)->filterFormOptions($reseller, $areas, $zones);

        return [
            'packages' => app(ResellerPackageCatalogService::class)->portalPackageOptions($reseller),
            'areas' => $filtered['areas']->values(),
            'zones' => $filtered['zones']->values(),
            'status_options' => CustomerStatus::options(),
            'billing_modes' => ['prepaid' => 'Prepaid', 'postpaid' => 'Postpaid'],
            'charge_modes' => ResellerNewCustomerChargeMode::labels(),
            'payment_methods' => ResellerCollectionPaymentMethod::options(),
            'defaults' => [
                'status' => CustomerStatus::ACTIVE,
                'billing_mode' => $reseller->default_customer_billing_mode ?? 'prepaid',
                'billing_day' => BillingDefaults::billingDay(),
                'joined_at' => now()->toDateString(),
                'expire_day' => BillingDefaults::defaultExpireDay(),
                'grace_period_days' => $reseller->default_customer_billing_mode === 'postpaid'
                    ? (int) ($reseller->default_postpaid_grace_days ?? 10)
                    : (int) ($reseller->default_prepaid_grace_days ?? 5),
            ],
            'can_override_charge_mode' => (bool) ($reseller->reseller_can_override_charge_mode
                || config('reseller_billing.reseller_can_override_charge_mode', false)),
            'default_charge_mode' => $reseller->new_customer_charge_mode ?? ResellerNewCustomerChargeMode::PRORATED,
            'auto_generate_code' => SubscriberIdSettings::autoGenerateEnabled(),
            'client_id_prefix' => $reseller->client_id_prefix,
        ];
    }

    /**
     * @return array{customer: Customer, billing: array<string, mixed>}
     */
    public function create(Reseller $reseller, Request $request): array
    {
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::CUSTOMER_CREATE)) {
            throw ValidationException::withMessages(['permission' => 'Create subscriber is not allowed for this partner account.']);
        }

        $tenantId = (int) $reseller->tenant_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64', 'regex:/^-?\d+$/'],
            'address' => ['required', 'string', 'max:500'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'customer_code' => ['nullable', 'string', 'max:64'],
            'billing_mode' => ['nullable', 'in:prepaid,postpaid'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'allow_active_when_due' => ['nullable', 'boolean'],
            'new_customer_charge_mode' => ['nullable', 'string', Rule::in(array_keys(ResellerNewCustomerChargeMode::labels()))],
            'joined_at' => ['nullable', 'date'],
            'expire_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mikrotik_secret_name' => ['nullable', 'string', 'max:128'],
            'mikrotik_ppp_password' => ['nullable', 'string', 'min:4', 'max:64'],
            'provision_mikrotik' => ['nullable', 'boolean'],
            'generate_bill' => ['nullable', 'boolean'],
            'collect_payment' => ['nullable', 'boolean'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', Rule::in(ResellerCollectionPaymentMethod::values())],
            'payment_reference' => ['nullable', 'string', 'max:128'],
            'payment_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $packageId = (int) $data['package_id'];
        if (! app(ResellerPackageCatalogService::class)->resellerMaySellPackage($reseller, $packageId)) {
            throw ValidationException::withMessages([
                'package_id' => 'This package is not assigned to your reseller account.',
            ]);
        }

        $this->assertClientLimit($reseller);
        app(ResellerTerritoryService::class)->assertCustomerLocationAllowed(
            $reseller,
            isset($data['area_id']) ? (int) $data['area_id'] : null,
            isset($data['zone_id']) ? (int) $data['zone_id'] : null,
        );

        $package = Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($packageId);

        $code = filled($data['customer_code'] ?? null)
            ? trim((string) $data['customer_code'])
            : CustomerCodeGenerator::generate($tenantId);

        $pppUsername = $this->resolvePppUsername($data['mikrotik_secret_name'] ?? null, $data['phone'], $code);

        $shouldBill = $request->boolean(
            'generate_bill',
            $reseller->auto_invoice_enabled && config('billing.bill_on_customer_create', true),
        );

        $meta = [
            'auto_invoice' => $shouldBill,
            'auto_suspend' => (bool) $reseller->auto_suspend_enabled,
        ];
        if ($request->boolean('allow_active_when_due')) {
            $meta['allow_active_when_due'] = true;
        }
        if (filled($data['new_customer_charge_mode'] ?? null)
            && ($reseller->reseller_can_override_charge_mode || config('reseller_billing.reseller_can_override_charge_mode', false))) {
            $meta['new_customer_charge_mode'] = $data['new_customer_charge_mode'];
        }

        $customer = Customer::createTrusted([
            'tenant_id' => $tenantId,
            'reseller_id' => $reseller->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'telegram_chat_id' => filled($data['telegram_chat_id'] ?? null) ? trim((string) $data['telegram_chat_id']) : null,
            'address' => $data['address'],
            'package_id' => $package->id,
            'area_id' => $data['area_id'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'customer_code' => $code,
            'status' => CustomerStatus::ACTIVE,
            'network_access_state' => 'active',
            'subscriber_type' => SubscriberType::STANDARD,
            'billing_mode' => $data['billing_mode'] ?? ($reseller->default_customer_billing_mode ?? 'prepaid'),
            'billing_day' => max(1, min(28, (int) ($data['billing_day'] ?? BillingDefaults::billingDay()))),
            'grace_period_days' => max(0, (int) ($data['grace_period_days'] ?? ($data['billing_mode'] ?? 'prepaid') === 'postpaid'
                ? ($reseller->default_postpaid_grace_days ?? 10)
                : ($reseller->default_prepaid_grace_days ?? 5))),
            'joined_at' => isset($data['joined_at']) ? Carbon::parse($data['joined_at'])->toDateString() : now()->toDateString(),
            'service_expires_at' => isset($data['expire_day'])
                ? BillingDefaults::dateFromExpireDay((int) $data['expire_day'])
                : BillingDefaults::dateFromExpireDay(BillingDefaults::defaultExpireDay()),
            'mikrotik_server_id' => $package->mikrotik_server_id,
            'mikrotik_secret_name' => $pppUsername,
            'mikrotik_ppp_password' => filled($data['mikrotik_ppp_password'] ?? null)
                ? (string) $data['mikrotik_ppp_password']
                : null,
            'kyc_status' => 'pending',
            'notes' => trim(($data['notes'] ?? '')."\n\nCreated via reseller portal ({$reseller->code})."),
            'meta' => $meta,
        ]);

        app(ResellerCustomerBillingEngine::class)->applyDefaultsToNewCustomer($customer, $reseller);
        $customer->saveQuietly();

        if ($request->boolean('provision_mikrotik', true)) {
            try {
                CustomerNetworkSync::provisionOnCreate($customer->fresh());
                $customer->refresh();
            } catch (\Throwable) {
                // customer saved; network sync can be retried from admin
            }
        }

        $billing = $shouldBill
            ? $this->issueInitialBill($reseller, $customer->fresh(), $request)
            : ['invoice' => null, 'message' => 'Subscriber created. No bill generated.', 'payment' => null];

        app(ResellerPortalActivityLogger::class)->log($reseller, 'customer.create', $customer->fresh());

        return ['customer' => $customer->fresh(), 'billing' => $billing];
    }

    public function update(Reseller $reseller, Customer $customer, Request $request): Customer
    {
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::CUSTOMER_EDIT)) {
            throw ValidationException::withMessages(['permission' => 'Edit subscriber is not allowed.']);
        }

        $this->assertOwned($reseller, $customer);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64', 'regex:/^-?\d+$/'],
            'address' => ['sometimes', 'string', 'max:500'],
            'package_id' => ['sometimes', 'integer', 'exists:packages,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'status' => ['sometimes', Rule::in(array_keys(CustomerStatus::options()))],
            'billing_mode' => ['sometimes', 'in:prepaid,postpaid'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'allow_active_when_due' => ['nullable', 'boolean'],
            'mikrotik_secret_name' => ['nullable', 'string', 'max:128'],
            'mikrotik_ppp_password' => ['nullable', 'string', 'min:4', 'max:64'],
            'provision_mikrotik' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($data['package_id']) && ! app(ResellerPackageCatalogService::class)->resellerMaySellPackage($reseller, (int) $data['package_id'])) {
            throw ValidationException::withMessages(['package_id' => 'This package is not assigned to your account.']);
        }

        $areaId = array_key_exists('area_id', $data) ? ($data['area_id'] !== null ? (int) $data['area_id'] : null) : ($customer->area_id ? (int) $customer->area_id : null);
        $zoneId = array_key_exists('zone_id', $data) ? ($data['zone_id'] !== null ? (int) $data['zone_id'] : null) : ($customer->zone_id ? (int) $customer->zone_id : null);
        app(ResellerTerritoryService::class)->assertCustomerLocationAllowed($reseller, $areaId, $zoneId);

        $previousStatus = CustomerStatus::normalize((string) $customer->status);
        $activating = isset($data['status'])
            && CustomerStatus::normalize($data['status']) === CustomerStatus::ACTIVE
            && $previousStatus !== CustomerStatus::ACTIVE;

        if ($activating) {
            $this->assertActiveClientLimit($reseller, $customer);
        }

        $customer->fill(collect($data)->only([
            'name', 'phone', 'email', 'telegram_chat_id', 'address', 'package_id', 'area_id', 'zone_id', 'status', 'notes', 'billing_mode', 'grace_period_days',
        ])->all());

        if (array_key_exists('allow_active_when_due', $data)) {
            $meta = is_array($customer->meta) ? $customer->meta : [];
            if ($request->boolean('allow_active_when_due')) {
                $meta['allow_active_when_due'] = true;
            } else {
                unset($meta['allow_active_when_due']);
            }
            $customer->meta = $meta;
        }

        if (isset($data['status'])) {
            $customer->status = CustomerStatus::normalize($data['status']);
        }

        if (array_key_exists('mikrotik_secret_name', $data)) {
            $customer->mikrotik_secret_name = filled($data['mikrotik_secret_name'])
                ? trim((string) $data['mikrotik_secret_name'])
                : $this->resolvePppUsername(null, $customer->phone, $customer->customer_code);
        }

        if (filled($data['mikrotik_ppp_password'] ?? null)) {
            $customer->mikrotik_ppp_password = (string) $data['mikrotik_ppp_password'];
        }

        if (isset($data['package_id'])) {
            $package = Package::withoutGlobalScopes()
                ->where('tenant_id', (int) $reseller->tenant_id)
                ->find((int) $data['package_id']);
            if ($package !== null) {
                $customer->mikrotik_server_id = $package->mikrotik_server_id;
            }
        }

        $customer->save();

        $shouldSync = $request->boolean('provision_mikrotik', true)
            && $customer->wasChanged(['mikrotik_secret_name', 'mikrotik_ppp_password', 'package_id', 'mikrotik_server_id']);

        if ($shouldSync) {
            try {
                CustomerNetworkSync::runNow($customer->fresh());
            } catch (\Throwable) {
                // saved; admin can retry sync
            }
        }

        app(ResellerPortalActivityLogger::class)->log($reseller, 'customer.update', $customer->fresh());

        return $customer->fresh();
    }

    public function isActivating(Customer $customer, Request $request): bool
    {
        if (! $request->has('status')) {
            return false;
        }

        $previous = CustomerStatus::normalize((string) $customer->status);
        $next = CustomerStatus::normalize((string) $request->input('status'));

        return $previous !== CustomerStatus::ACTIVE && $next === CustomerStatus::ACTIVE;
    }

    /**
     * Create this month's invoice when reactivating a subscriber (if none exists for the period).
     *
     * @return array{invoice: ?Invoice, message: string}
     */
    public function generateBillOnActivate(Reseller $reseller, Customer $customer): array
    {
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::INVOICE_GENERATE)) {
            return ['invoice' => null, 'message' => ''];
        }

        $customer->loadMissing('package');
        if ($customer->package === null) {
            return ['invoice' => null, 'message' => 'No package assigned — bill was not created.'];
        }

        app(ResellerSuspendedBillingService::class)->voidStaleOpenInvoicesBeforeActivation($customer);

        try {
            $noProrate = app(ResellerCustomerBillingEngine::class)->shouldSkipProration($customer);
            $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::today(), $noProrate, null);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return ['invoice' => null, 'message' => is_string($message) ? $message : 'Bill could not be created.'];
        }

        if ($invoice === null) {
            return ['invoice' => null, 'message' => 'Bill for this month already exists or could not be created.'];
        }

        app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.generate_on_activate', $invoice);

        $message = 'Bill '.$invoice->invoice_number.' generated.';
        $wholesaleNote = app(ResellerWholesaleDebitService::class)->messageForInvoice($invoice->fresh());
        if ($wholesaleNote !== '') {
            $message .= ' '.$wholesaleNote;
        }

        return ['invoice' => $invoice, 'message' => $message];
    }

    /**
     * @return array{invoice: ?string, message: string, payment: ?Payment}
     */
    private function issueInitialBill(Reseller $reseller, Customer $customer, Request $request): array
    {
        $bill = app(CustomerActivationBillingService::class)->issueFirstBillIfRequested(
            $customer,
            CustomerActivationBillingService::CYCLE_THIS_MONTH,
            false,
        );

        $invoice = $bill['invoice'];
        if ($invoice === null) {
            return [
                'invoice' => null,
                'message' => $bill['message'],
                'payment' => null,
            ];
        }

        $billingMode = (string) ($customer->billing_mode ?? 'prepaid');
        $message = 'Bill '.$invoice->invoice_number.' created.';
        $wholesaleNote = app(ResellerWholesaleDebitService::class)->messageForInvoice($invoice->fresh());
        if ($wholesaleNote !== '') {
            $message .= ' '.$wholesaleNote;
        }
        $payment = null;

        if (in_array($billingMode, ['prepaid', 'advance'], true)) {
            if ($request->boolean('collect_payment', true)
                && app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::PAYMENT_COLLECT)) {
                $amount = $request->filled('payment_amount')
                    ? round((float) $request->input('payment_amount'), 2)
                    : round((float) $invoice->balanceDue(), 2);

                if ($amount > 0) {
                    try {
                        $result = app(ResellerCollectionPaymentService::class)->collect($reseller, $customer->fresh(), [
                            'amount' => $amount,
                            'method' => $request->input('payment_method', 'cash'),
                            'reference' => $request->input('payment_reference'),
                            'notes' => $request->input('payment_notes'),
                            'invoice_id' => $invoice->id,
                        ]);
                        $payment = $result['payment'];
                        $message = 'Bill '.$invoice->invoice_number.' created. Payment recorded ('.number_format($amount, 2).' BDT).';
                    } catch (ValidationException $exception) {
                        $first = collect($exception->errors())->flatten()->first();
                        $message = 'Bill '.$invoice->invoice_number.' created. Payment not recorded: '.$first;
                    }
                } else {
                    $message = 'Bill '.$invoice->invoice_number.' created. Enter payment amount to collect.';
                }
            } else {
                $message = 'Bill '.$invoice->invoice_number.' created. Collect payment from customer.';
            }
        } else {
            $due = round((float) $invoice->balanceDue(), 2);
            $message = 'Bill '.$invoice->invoice_number.' created. Due: '.number_format($due, 2).' BDT.';
        }

        return [
            'invoice' => $invoice->invoice_number,
            'message' => $message,
            'payment' => $payment,
        ];
    }

    private function resolvePppUsername(?string $username, string $phone, string $fallbackCode): string
    {
        if (filled($username)) {
            return trim($username);
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits !== '' ? $digits : $fallbackCode;
    }

    public function assertClientLimit(Reseller $reseller): void
    {
        if ($reseller->max_clients === null) {
            return;
        }

        $count = $reseller->customers()->count();
        if ($count >= (int) $reseller->max_clients) {
            throw ValidationException::withMessages([
                'limit' => 'Client limit reached ('.$reseller->max_clients.'). Contact admin to increase limit.',
            ]);
        }
    }

    public function assertActiveClientLimit(Reseller $reseller, ?Customer $excluding = null): void
    {
        if ($reseller->max_active_clients === null) {
            return;
        }

        $query = $reseller->customers()->where('status', CustomerStatus::ACTIVE);
        if ($excluding !== null) {
            $query->where('id', '!=', $excluding->id);
        }

        if ($query->count() >= (int) $reseller->max_active_clients) {
            throw ValidationException::withMessages([
                'limit' => 'Active client limit reached ('.$reseller->max_active_clients.').',
            ]);
        }
    }
}
