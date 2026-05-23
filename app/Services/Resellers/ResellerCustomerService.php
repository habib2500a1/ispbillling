<?php

namespace App\Services\Resellers;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Reseller;
use App\Models\Zone;
use App\Services\Billing\CustomerActivationBillingService;
use App\Support\BillingDefaults;
use App\Support\CustomerCodeGenerator;
use App\Support\CustomerStatus;
use App\Support\ResellerPortalPermission;
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

        return [
            'packages' => Package::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'price_monthly']),
            'areas' => Area::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'zones' => Zone::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'area_id']),
            'status_options' => CustomerStatus::options(),
            'billing_modes' => ['prepaid' => 'Prepaid', 'postpaid' => 'Postpaid'],
            'defaults' => [
                'status' => CustomerStatus::ACTIVE,
                'billing_mode' => 'prepaid',
                'billing_day' => BillingDefaults::billingDay(),
                'joined_at' => now()->toDateString(),
                'expire_day' => BillingDefaults::defaultExpireDay(),
            ],
            'auto_generate_code' => SubscriberIdSettings::autoGenerateEnabled(),
            'client_id_prefix' => $reseller->client_id_prefix,
        ];
    }

    /**
     * @return array{customer: Customer, billing: array<string, mixed>}
     */
    public function create(Reseller $reseller, Request $request): array
    {
        if (! $reseller->canPortal(ResellerPortalPermission::CUSTOMER_CREATE)) {
            throw ValidationException::withMessages(['permission' => 'Create subscriber is not allowed for this partner account.']);
        }

        $tenantId = (int) $reseller->tenant_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'customer_code' => ['nullable', 'string', 'max:64'],
            'billing_mode' => ['nullable', 'in:prepaid,postpaid'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'joined_at' => ['nullable', 'date'],
            'expire_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'provision_mikrotik' => ['nullable', 'boolean'],
        ]);

        $package = Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $data['package_id']);

        $code = filled($data['customer_code'] ?? null)
            ? trim((string) $data['customer_code'])
            : CustomerCodeGenerator::generate($tenantId);

        $customer = Customer::createTrusted([
            'tenant_id' => $tenantId,
            'reseller_id' => $reseller->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'],
            'package_id' => $package->id,
            'area_id' => $data['area_id'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'customer_code' => $code,
            'status' => CustomerStatus::ACTIVE,
            'network_access_state' => 'active',
            'subscriber_type' => SubscriberType::STANDARD,
            'billing_mode' => $data['billing_mode'] ?? 'prepaid',
            'billing_day' => max(1, min(28, (int) ($data['billing_day'] ?? BillingDefaults::billingDay()))),
            'joined_at' => isset($data['joined_at']) ? Carbon::parse($data['joined_at'])->toDateString() : now()->toDateString(),
            'service_expires_at' => isset($data['expire_day'])
                ? BillingDefaults::dateFromExpireDay((int) $data['expire_day'])
                : BillingDefaults::dateFromExpireDay(BillingDefaults::defaultExpireDay()),
            'mikrotik_server_id' => $package->mikrotik_server_id,
            'kyc_status' => 'pending',
            'notes' => trim(($data['notes'] ?? '')."\n\nCreated via reseller portal ({$reseller->code})."),
            'meta' => [
                'auto_invoice' => (bool) $reseller->auto_invoice_enabled,
                'auto_suspend' => (bool) $reseller->auto_suspend_enabled,
            ],
        ]);

        if ($request->boolean('provision_mikrotik', true) && filled($customer->mikrotik_secret_name)) {
            try {
                SyncCustomerNetworkAccessJob::dispatchSync($tenantId, (int) $customer->id);
                $customer->refresh();
            } catch (\Throwable) {
                // customer saved; network sync can be retried from admin
            }
        }

        $billing = ['invoice' => null, 'message' => ''];
        if ($reseller->auto_invoice_enabled && config('billing.bill_on_customer_create', true)) {
            $bill = app(CustomerActivationBillingService::class)->issueFirstBillIfRequested($customer->fresh(), 'this_month');
            $billing['message'] = $bill['message'];
            $billing['invoice'] = $bill['invoice']?->invoice_number;
        }

        return ['customer' => $customer->fresh(), 'billing' => $billing];
    }

    public function update(Reseller $reseller, Customer $customer, Request $request): Customer
    {
        if (! $reseller->canPortal(ResellerPortalPermission::CUSTOMER_EDIT)) {
            throw ValidationException::withMessages(['permission' => 'Edit subscriber is not allowed.']);
        }

        $this->assertOwned($reseller, $customer);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'string', 'max:500'],
            'package_id' => ['sometimes', 'integer', 'exists:packages,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'status' => ['sometimes', Rule::in(array_keys(CustomerStatus::options()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer->fill(collect($data)->only([
            'name', 'phone', 'email', 'address', 'package_id', 'area_id', 'zone_id', 'status', 'notes',
        ])->all());

        if (isset($data['status'])) {
            $customer->status = CustomerStatus::normalize($data['status']);
        }

        $customer->save();

        return $customer->fresh();
    }
}
