<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerStaff;
use App\Support\ResellerPortalPermission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ResellerStaffService
{
    /**
     * @return array<string, string>
     */
    public function permissionOptions(Reseller $reseller): array
    {
        $allowed = array_intersect_key(
            ResellerPortalPermission::labels(),
            array_flip($reseller->portalPermissions()),
        );
        unset($allowed[ResellerPortalPermission::STAFF_MANAGE]);

        return $allowed;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Reseller $reseller, array $data): ResellerStaff
    {
        $validated = $this->validate($reseller, $data, creating: true);

        $plain = (string) $validated['password'];

        $staff = ResellerStaff::create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'name' => $validated['name'],
            'login' => $validated['login'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'password' => $plain,
            'portal_permissions' => $this->normalizePermissions($reseller, $validated['portal_permissions'] ?? []),
            'meta' => ['portal_password_plain' => $plain],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return $staff->fresh() ?? $staff;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ResellerStaff $staff, Reseller $reseller, array $data): ResellerStaff
    {
        $validated = $this->validate($reseller, $data, creating: false, staff: $staff);

        $staff->forceFill([
            'name' => $validated['name'],
            'login' => $validated['login'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'portal_permissions' => $this->normalizePermissions($reseller, $validated['portal_permissions'] ?? []),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ])->save();

        if (filled($validated['password'] ?? null)) {
            $staff->setPassword((string) $validated['password']);
        }

        return $staff->fresh() ?? $staff;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(Reseller $reseller, array $data, bool $creating, ?ResellerStaff $staff = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'login' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('reseller_staff', 'login')
                    ->where('tenant_id', $reseller->tenant_id)
                    ->ignore($staff?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:4', 'max:255'],
            'portal_permissions' => ['nullable', 'array'],
            'portal_permissions.*' => ['string', Rule::in(ResellerPortalPermission::assignableToStaff())],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @param  list<string>  $requested
     * @return list<string>
     */
    private function normalizePermissions(Reseller $reseller, array $requested): array
    {
        $parent = $reseller->portalPermissions();
        $filtered = array_values(array_intersect($requested, ResellerPortalPermission::assignableToStaff(), $parent));

        if ($filtered !== []) {
            return $filtered;
        }

        return array_values(array_intersect(
            [
                ResellerPortalPermission::CUSTOMER_VIEW,
                ResellerPortalPermission::BILLING_VIEW,
                ResellerPortalPermission::PAYMENT_COLLECT,
            ],
            $parent,
        ));
    }
}
