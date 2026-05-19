<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

class BtrcDisReportService
{
    /**
     * @return Collection<int, array<string, string|null>>
     */
    public function rows(?int $tenantId = null): Collection
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return Customer::query()
            ->withoutGlobalScopes()
            ->with(['package:id,name,btrc_label,btrc_bandwidth,btrc_notes', 'area:id,name', 'zone:id,name'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                CustomerStatus::ACTIVE,
                CustomerStatus::SUSPENDED,
            ])
            ->orderBy('customer_code')
            ->get()
            ->map(fn (Customer $c): array => [
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'phone' => $c->phone,
                'email' => $c->email,
                'nid_number' => $c->nid_number,
                'status' => $c->status,
                'package_name' => $c->package?->name,
                'btrc_label' => $c->package?->btrc_label,
                'btrc_bandwidth' => $c->package?->btrc_bandwidth,
                'btrc_notes' => $c->package?->btrc_notes,
                'area' => $c->area?->name,
                'zone' => $c->zone?->name,
                'joined_at' => $c->joined_at?->format('Y-m-d'),
                'radius_username' => $c->radius_username ?? $c->mikrotik_secret_name,
            ]);
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'customer_code',
            'name',
            'phone',
            'email',
            'nid_number',
            'status',
            'package_name',
            'btrc_label',
            'btrc_bandwidth',
            'btrc_notes',
            'area',
            'zone',
            'joined_at',
            'radius_username',
        ];
    }
}
