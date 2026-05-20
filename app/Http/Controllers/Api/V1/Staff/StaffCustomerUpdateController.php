<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Support\CustomerStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'address' => ['nullable', 'string', 'max:500'],
            'package_id' => ['sometimes', 'integer', 'exists:packages,id'],
            'status' => ['sometimes', Rule::in(array_keys(CustomerStatus::options()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($data['package_id'])) {
            Package::query()
                ->where('tenant_id', $user->tenant_id)
                ->findOrFail((int) $data['package_id']);
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
