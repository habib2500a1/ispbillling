<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Support\BillingDefaults;
use App\Support\CustomerCodeGenerator;
use App\Support\CustomerStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffCustomerUpdateController extends Controller
{
    public function update(Request $request, int $customer): JsonResponse
    {
        $user = $this->manager($request);

        $model = Customer::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($customer)
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'string', 'max:500'],
            'area_id' => ['sometimes', 'integer', 'exists:areas,id'],
            'zone_id' => ['sometimes', 'integer', 'exists:zones,id'],
            'package_id' => ['sometimes', 'integer', 'exists:packages,id'],
            'status' => ['sometimes', Rule::in(array_keys(CustomerStatus::options()))],
            'billing_mode' => ['sometimes', 'in:postpaid,prepaid,advance'],
            'expire_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'mikrotik_secret_name' => ['nullable', 'string', 'max:128'],
            'mikrotik_ppp_password' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'customer_code' => ['sometimes', 'string', 'max:64'],
        ]);

        if (isset($data['customer_code'])) {
            $code = trim((string) $data['customer_code']);
            if ($code === '') {
                throw ValidationException::withMessages(['customer_code' => ['Customer ID cannot be empty.']]);
            }
            if (! CustomerCodeGenerator::isValidManualCode($code)) {
                throw ValidationException::withMessages(['customer_code' => ['Invalid Customer ID for the current format.']]);
            }
            $duplicate = Customer::withoutGlobalScopes()
                ->where('tenant_id', $user->tenant_id)
                ->where('customer_code', $code)
                ->where('id', '!=', $model->id)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['customer_code' => ['This Customer ID is already in use.']]);
            }
            $data['customer_code'] = $code;
        }

        if (isset($data['package_id'])) {
            Package::query()
                ->where('tenant_id', $user->tenant_id)
                ->findOrFail((int) $data['package_id']);
        }

        if (isset($data['expire_day'])) {
            $data['service_expires_at'] = BillingDefaults::dateFromExpireDay((int) $data['expire_day']);
            unset($data['expire_day']);
        }

        if (array_key_exists('mikrotik_secret_name', $data)) {
            $ppp = filled($data['mikrotik_secret_name']) ? trim((string) $data['mikrotik_secret_name']) : null;
            $data['mikrotik_secret_name'] = $ppp;
            if (! array_key_exists('radius_username', $data)) {
                $data['radius_username'] = $ppp;
            }
        }

        if (isset($data['mikrotik_ppp_password']) && blank($data['mikrotik_ppp_password'])) {
            unset($data['mikrotik_ppp_password']);
        }

        $model->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json([
            'message' => 'Customer updated.',
            'customer' => [
                'id' => $model->id,
                'customer_code' => $model->customer_code,
                'name' => $model->name,
                'phone' => $model->phone,
                'status' => $model->status,
                'package' => $model->package?->name,
            ],
        ]);
    }

    private function manager(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'branch-manager', 'cashier', 'collector']),
            403,
        );

        return $user;
    }
}
