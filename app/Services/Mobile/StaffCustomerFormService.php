<?php

namespace App\Services\Mobile;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Area;
use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\User;
use App\Models\Zone;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
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
        $tenantId = (int) $user->tenant_id;

        return [
            'packages' => Package::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'download_mbps', 'upload_mbps', 'price_monthly', 'mikrotik_profile_name'])
                ->map(fn (Package $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'download_mbps' => $p->download_mbps,
                    'upload_mbps' => $p->upload_mbps,
                    'price_monthly' => (float) $p->price_monthly,
                    'mikrotik_profile' => $p->mikrotik_profile_name,
                ]),
            'areas' => Area::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'zones' => Zone::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'area_id']),
            'mikrotik_servers' => MikrotikServer::query()
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
            'defaults' => [
                'status' => CustomerStatus::ACTIVE,
                'network_access_state' => 'active',
                'subscriber_type' => SubscriberType::STANDARD,
                'billing_mode' => 'postpaid',
                'billing_day' => min(28, (int) now()->day),
                'joined_at' => now()->toDateString(),
                'service_expires_at' => now()->addMonth()->endOfMonth()->toDateString(),
                'provision_mikrotik' => true,
            ],
        ];
    }

    /**
     * @return array{customer: Customer, network: array<string, mixed>}
     */
    public function create(User $user, Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'customer_code' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(array_keys(CustomerStatus::options()))],
            'network_access_state' => ['nullable', 'in:active,suspended'],
            'billing_mode' => ['nullable', 'in:postpaid,prepaid,advance'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'joined_at' => ['nullable', 'date'],
            'service_expires_at' => ['nullable', 'date'],
            'mikrotik_server_id' => ['nullable', 'integer', 'exists:mikrotik_servers,id'],
            'mikrotik_secret_name' => ['nullable', 'string', 'max:128'],
            'mikrotik_ppp_password' => ['nullable', 'string', 'max:128'],
            'radius_username' => ['nullable', 'string', 'max:255'],
            'portal_password' => ['nullable', 'string', 'min:4', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'provision_mikrotik' => ['nullable', 'boolean'],
        ]);

        $package = Package::query()
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail((int) $data['package_id']);

        $phone = preg_replace('/\D+/', '', $data['phone']) ?: $data['phone'];
        $pppUser = filled($data['mikrotik_secret_name'] ?? null)
            ? trim((string) $data['mikrotik_secret_name'])
            : $phone;

        $attrs = [
            'tenant_id' => $user->tenant_id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'package_id' => $package->id,
            'area_id' => $data['area_id'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'status' => CustomerStatus::normalize($data['status'] ?? CustomerStatus::ACTIVE),
            'network_access_state' => $data['network_access_state'] ?? 'active',
            'subscriber_type' => SubscriberType::STANDARD,
            'billing_mode' => $data['billing_mode'] ?? 'postpaid',
            'billing_day' => (int) ($data['billing_day'] ?? min(28, now()->day)),
            'joined_at' => isset($data['joined_at'])
                ? Carbon::parse($data['joined_at'])->toDateString()
                : now()->toDateString(),
            'service_expires_at' => $data['service_expires_at'] ?? null,
            'mikrotik_server_id' => $data['mikrotik_server_id'] ?? $package->mikrotik_server_id,
            'mikrotik_secret_name' => $pppUser,
            'radius_username' => $data['radius_username'] ?? $pppUser,
            'kyc_status' => 'pending',
            'notes' => trim(($data['notes'] ?? '')."\n\nCreated via mobile app by {$user->name}"),
        ];

        if (filled($data['customer_code'] ?? null)) {
            $attrs['customer_code'] = trim((string) $data['customer_code']);
        }

        if (filled($data['mikrotik_ppp_password'] ?? null)) {
            $attrs['mikrotik_ppp_password'] = $data['mikrotik_ppp_password'];
        }

        if (filled($data['portal_password'] ?? null)) {
            $attrs['portal_password'] = Hash::make($data['portal_password']);
        }

        $customer = Customer::createTrusted($attrs);

        $network = ['provisioned' => false, 'message' => 'MikroTik sync skipped.'];

        if ($request->boolean('provision_mikrotik', true) && $customer->status === CustomerStatus::ACTIVE) {
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

        return ['customer' => $customer, 'network' => $network];
    }
}
