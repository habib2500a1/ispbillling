<?php

namespace App\Services\Mobile;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Area;
use App\Models\Customer;
use App\Models\District;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Subzone;
use App\Models\Upazila;
use App\Models\User;
use App\Models\Zone;
use App\Services\Billing\CustomerActivationBillingService;
use App\Services\Network\FiberPlantMapService;
use App\Services\Subscribers\CustomerLineActivationService;
use App\Services\Mikrotik\MikrotikServerService;
use App\Support\BillingDefaults;
use App\Support\CustomerCodeGenerator;
use App\Models\CustomerContact;
use App\Support\CustomerStatus;
use App\Support\OnuOwnership;
use App\Support\SubscriberGpsMeta;
use App\Support\SubscriberIdSettings;
use App\Support\SubscriberType;
use App\Support\TenantResolver;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

final class StaffCustomerFormService
{
    /**
     * @return array<string, mixed>
     */
    public function formOptions(User $user): array
    {
        $tenantId = $user->tenant_id !== null ? (int) $user->tenant_id : TenantResolver::requiredTenantId();

        $this->ensurePackagesForTenant($tenantId);

        return [
            'packages' => Package::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'download_mbps', 'upload_mbps', 'price_monthly', 'mikrotik_profile_name', 'mikrotik_server_id'])
                ->map(fn (Package $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'download_mbps' => $p->download_mbps,
                    'upload_mbps' => $p->upload_mbps,
                    'price_monthly' => (float) $p->price_monthly,
                    'mikrotik_profile' => $p->mikrotik_profile_name,
                    'mikrotik_server_id' => $p->mikrotik_server_id,
                ]),
            'areas' => Area::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'zones' => Zone::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'area_id']),
            'subzones' => Subzone::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'zone_id']),
            'districts' => District::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'upazilas' => Upazila::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'district_id']),
            'segments' => [
                ['value' => 'residential', 'label' => 'Home / residential'],
                ['value' => 'corporate', 'label' => 'Corporate'],
                ['value' => 'commercial', 'label' => 'Commercial'],
                ['value' => 'vip', 'label' => 'VIP'],
            ],
            'subscriber_types' => collect(SubscriberType::options())->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'connection_types' => [
                ['value' => 'fiber', 'label' => 'Fiber (FTTH)'],
                ['value' => 'wireless', 'label' => 'Wireless'],
                ['value' => 'dedicated', 'label' => 'Dedicated line'],
                ['value' => 'hotspot', 'label' => 'Hotspot'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'onu_ownership_options' => collect(OnuOwnership::options())->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'gender_options' => [
                ['value' => 'male', 'label' => 'Male'],
                ['value' => 'female', 'label' => 'Female'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'mikrotik_servers' => MikrotikServer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_enabled', true)
                ->orderBy('name')
                ->get(['id', 'name', 'host']),
            'status_options' => collect(CustomerStatus::options())->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'billing_modes' => [
                ['value' => 'postpaid', 'label' => 'Postpaid'],
                ['value' => 'prepaid', 'label' => 'Prepaid'],
                ['value' => 'advance', 'label' => 'Advance'],
            ],
            'first_bill_cycles' => [
                ['value' => 'this_month', 'label' => 'This month (bill today)'],
                ['value' => 'next_month', 'label' => 'Next month (on bill day)'],
            ],
            'defaults' => [
                'status' => CustomerStatus::ACTIVE,
                'network_access_state' => 'active',
                'subscriber_type' => SubscriberType::STANDARD,
                'billing_mode' => 'prepaid',
                'billing_day' => BillingDefaults::billingDay(),
                'first_bill_cycle' => 'this_month',
                'joined_at' => now()->toDateString(),
                'expire_day' => BillingDefaults::defaultExpireDay(),
                'provision_mikrotik' => true,
            ],
            'expire_days' => collect(range(1, 31))->map(fn (int $d) => ['value' => $d, 'label' => 'Day '.$d])->values()->all(),
            'customer_id' => [
                'auto_generate' => SubscriberIdSettings::autoGenerateEnabled(),
                'format' => SubscriberIdSettings::codeFormat(),
                'next_example' => SubscriberIdSettings::autoGenerateEnabled()
                    ? SubscriberIdSettings::previewNext($tenantId)
                    : null,
            ],
        ];
    }

    /**
     * @return array{customer: Customer, network: array<string, mixed>}
     */
    public function create(User $user, Request $request): array
    {
        $tenantId = $user->tenant_id !== null ? (int) $user->tenant_id : TenantResolver::requiredTenantId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'alternate_phone' => ['nullable', 'string', 'max:32'],
            'nid_number' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:32'],
            'date_of_birth' => ['nullable', 'date'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'segment' => ['nullable', 'string', 'max:32'],
            'subscriber_type' => ['nullable', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:500'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'upazila_id' => ['nullable', 'integer', 'exists:upazilas,id'],
            'area_id' => [
                Rule::requiredIf(fn () => Area::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()),
                'nullable',
                'integer',
                'exists:areas,id',
            ],
            'zone_id' => [
                Rule::requiredIf(fn () => Zone::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()),
                'nullable',
                'integer',
                'exists:zones,id',
            ],
            'subzone_id' => ['nullable', 'integer', 'exists:subzones,id'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'customer_code' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(array_keys(CustomerStatus::options()))],
            'network_access_state' => ['nullable', 'in:active,suspended'],
            'billing_mode' => ['nullable', 'in:postpaid,prepaid,advance'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'joined_at' => ['nullable', 'date'],
            'service_expires_at' => ['nullable', 'date'],
            'expire_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'mikrotik_server_id' => ['nullable', 'integer', 'exists:mikrotik_servers,id'],
            'mikrotik_secret_name' => ['nullable', 'string', 'max:128'],
            'mikrotik_ppp_password' => ['nullable', 'string', 'max:128'],
            'radius_username' => ['nullable', 'string', 'max:255'],
            'portal_password' => ['nullable', 'string', 'min:4', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'provision_mikrotik' => ['nullable', 'boolean'],
            'first_bill_cycle' => ['nullable', 'in:this_month,next_month'],
            'apply_line_charges' => ['nullable', 'boolean'],
            'installation_charge' => ['nullable', 'numeric', 'min:0'],
            'line_charge' => ['nullable', 'numeric', 'min:0'],
            'device_charge' => ['nullable', 'numeric', 'min:0'],
            'line_device_charge' => ['nullable', 'numeric', 'min:0'],
            'onu_device_id' => ['nullable', 'integer'],
            'device_id' => ['nullable', 'integer'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'line_cash_amount' => ['nullable', 'numeric', 'min:0'],
            'cash_method' => ['nullable', 'string', 'max:32'],
            'line_cash_method' => ['nullable', 'string', 'max:32'],
            'use_wallet' => ['nullable', 'boolean'],
            'use_wallet_on_register' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        $this->assertValidCustomerCode($tenantId, $data['customer_code'] ?? null);

        $package = Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $data['package_id']);

        $pppUser = filled($data['mikrotik_secret_name'] ?? null)
            ? trim((string) $data['mikrotik_secret_name'])
            : null;

        $metaInput = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        foreach (['gender', 'date_of_birth', 'occupation', 'house_no', 'road_no', 'connection_type', 'onu_ownership', 'box_name', 'epon_port', 'onu_mac', 'cable_length_m', 'gps_lat', 'gps_lng', 'monthly_discount_bdt'] as $metaKey) {
            if (array_key_exists($metaKey, $data) && filled($data[$metaKey])) {
                $metaInput[$metaKey] = $data[$metaKey];
            }
        }

        $meta = SubscriberGpsMeta::normalize(array_merge([
            'notify_sms' => true,
            'auto_invoice' => true,
            'auto_pppoe' => true,
            'auto_onu' => (bool) config('optical.auto_provision_customer_onu', true),
            'auto_activate' => true,
            'auto_suspend' => true,
            'installation_status' => 'pending',
            'onu_ownership' => OnuOwnership::COMPANY,
            'registered_by_id' => $user->id,
            'technician_id' => $user->id,
        ], $metaInput));

        $attrs = [
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'phone' => CustomerContact::normalizePhone($data['phone']),
            'email' => $data['email'] ?? null,
            'nid_number' => $data['nid_number'] ?? null,
            'segment' => $data['segment'] ?? 'residential',
            'subscriber_type' => SubscriberType::normalize($data['subscriber_type'] ?? SubscriberType::STANDARD),
            'address' => $data['address'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'upazila_id' => $data['upazila_id'] ?? null,
            'package_id' => $package->id,
            'area_id' => $data['area_id'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'subzone_id' => $data['subzone_id'] ?? null,
            'status' => CustomerStatus::normalize($data['status'] ?? CustomerStatus::ACTIVE),
            'network_access_state' => $data['network_access_state'] ?? 'active',
            'billing_mode' => $data['billing_mode'] ?? 'prepaid',
            'joined_at' => isset($data['joined_at'])
                ? Carbon::parse($data['joined_at'])->toDateString()
                : now()->toDateString(),
            'billing_day' => max(1, min(28, (int) ($data['billing_day'] ?? BillingDefaults::billingDay()))),
            'service_expires_at' => isset($data['service_expires_at'])
                ? $data['service_expires_at']
                : (isset($data['expire_day'])
                    ? BillingDefaults::dateFromExpireDay((int) $data['expire_day'])
                    : BillingDefaults::dateFromExpireDay(BillingDefaults::defaultExpireDay())),
            'mikrotik_server_id' => $data['mikrotik_server_id'] ?? $package->mikrotik_server_id,
            'mikrotik_secret_name' => $pppUser,
            'radius_username' => filled($data['radius_username'] ?? null)
                ? trim((string) $data['radius_username'])
                : $pppUser,
            'kyc_status' => 'pending',
            'notes' => trim(($data['notes'] ?? '')."\n\nCreated via mobile app by {$user->name}"),
            'meta' => $meta,
        ];

        if (filled($data['customer_code'] ?? null)) {
            $attrs['customer_code'] = trim((string) $data['customer_code']);
        }

        if (filled($data['mikrotik_ppp_password'] ?? null)) {
            $attrs['mikrotik_ppp_password'] = $data['mikrotik_ppp_password'];
        }

        if (filled($data['portal_password'] ?? null)) {
            $attrs['portal_password'] = Hash::make($data['portal_password']);
        } else {
            $attrs['portal_password'] = Hash::make((string) config('portal.default_password', '123456'));
        }

        $customer = Customer::createTrusted($attrs);

        $alternate = trim((string) ($data['alternate_phone'] ?? ''));
        if ($alternate !== '') {
            $customer->contacts()->firstOrCreate(
                ['phone' => CustomerContact::normalizePhone($alternate)],
                ['label' => 'alternate', 'is_primary' => false, 'is_whatsapp' => false],
            );
        }

        try {
            app(FiberPlantMapService::class)->syncCustomerNodeFromGps($customer->fresh());
        } catch (\Throwable) {
            // Map sync is optional when GPS missing.
        }

        $network = ['provisioned' => false, 'message' => 'MikroTik sync skipped.'];

        if ($request->boolean('provision_mikrotik', true)
            && filled($customer->mikrotik_secret_name)
            && $customer->status === CustomerStatus::ACTIVE) {
            try {
                SyncCustomerNetworkAccessJob::dispatchSync((int) $customer->tenant_id, (int) $customer->id);
                $customer->refresh();
                $network = [
                    'provisioned' => true,
                    'message' => 'MikroTik / PPP provisioned.',
                    'network_access_state' => $customer->network_access_state,
                    'is_online' => $customer->isPppOnline(),
                ];
            } catch (\Throwable $e) {
                $network = [
                    'provisioned' => false,
                    'message' => 'Customer saved but MikroTik sync failed: '.$e->getMessage(),
                ];
            }
        }

        $billing = ['invoice' => null, 'message' => ''];
        if (config('billing.bill_on_customer_create', true)) {
            $cycle = (string) ($data['first_bill_cycle']
                ?? CustomerActivationBillingService::defaultFirstBillCycle((string) ($customer->billing_mode ?? 'postpaid')));
            $bill = app(CustomerActivationBillingService::class)->issueFirstBillIfRequested($customer->fresh(), $cycle);
            $billing = [
                'invoice' => $bill['invoice'] ? [
                    'id' => $bill['invoice']->id,
                    'invoice_number' => $bill['invoice']->invoice_number,
                    'total' => (float) $bill['invoice']->total,
                    'balance_due' => $bill['invoice']->balanceDue(),
                ] : null,
                'message' => $bill['message'],
                'settled' => $bill['settled'],
            ];
        }

        $lineActivation = ['applied' => false, 'message' => ''];
        $activationService = app(CustomerLineActivationService::class);
        $registerPayload = array_merge($data, [
            'apply_line_charges' => $request->boolean('apply_line_charges', false),
            'meta' => array_merge(
                is_array($data['meta'] ?? null) ? $data['meta'] : [],
                ['installation_charge' => $data['installation_charge'] ?? $data['line_charge'] ?? 0],
            ),
            'onu_device_pick' => $data['device_id'] ?? $data['onu_device_id'] ?? null,
            'line_device_charge' => $data['device_charge'] ?? $data['line_device_charge'] ?? 0,
            'use_wallet_on_register' => $request->boolean('use_wallet', true),
            'line_cash_amount' => $data['cash_amount'] ?? 0,
            'line_cash_method' => $data['cash_method'] ?? 'cash',
        ]);

        if ($activationService->shouldActivateFromRegisterForm($registerPayload)) {
            try {
                $activation = $activationService->activate(
                    $customer->fresh(),
                    $activationService->inputFromRegisterForm($registerPayload),
                );
                $lineActivation = [
                    'applied' => true,
                    'message' => $activation['message'],
                    'invoice_id' => $activation['invoice']?->id,
                    'wallet_applied' => $activation['wallet_applied'],
                    'cash_collected' => $activation['cash_collected'],
                    'remaining_due' => $activation['remaining_due'],
                ];
            } catch (\Illuminate\Validation\ValidationException $e) {
                $lineActivation = [
                    'applied' => false,
                    'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                ];
            }
        }

        return [
            'customer' => $customer->fresh(),
            'network' => $network,
            'billing' => $billing,
            'line_activation' => $lineActivation,
        ];
    }

    private function assertValidCustomerCode(int $tenantId, mixed $rawCode): void
    {
        $code = is_string($rawCode) ? trim($rawCode) : '';

        if ($code === '') {
            if (! SubscriberIdSettings::autoGenerateEnabled()) {
                throw ValidationException::withMessages([
                    'customer_code' => ['Customer ID is required. Turn on automatic ID in Company setup, or enter an ID.'],
                ]);
            }

            return;
        }

        if (! CustomerCodeGenerator::isValidManualCode($code)) {
            throw ValidationException::withMessages([
                'customer_code' => ['Invalid Customer ID for the current format.'],
            ]);
        }

        if (Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_code', $code)
            ->exists()) {
            throw ValidationException::withMessages([
                'customer_code' => ['This Customer ID is already in use.'],
            ]);
        }
    }

    private function ensurePackagesForTenant(int $tenantId): void
    {
        $hasPackages = Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();

        if ($hasPackages) {
            return;
        }

        $servers = MikrotikServer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->get();

        if ($servers->isEmpty()) {
            return;
        }

        $sync = app(MikrotikServerService::class);
        foreach ($servers as $server) {
            try {
                $sync->syncPackagesFromPppProfiles($server);
            } catch (\Throwable) {
                // Router unreachable — staff can add packages manually.
            }
        }
    }
}
