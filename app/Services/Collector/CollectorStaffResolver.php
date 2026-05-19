<?php

namespace App\Services\Collector;

use App\Models\Payment;
use App\Models\User;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

final class CollectorStaffResolver
{
    /**
     * Admin / manager can attribute collection to any field staff.
     */
    public function canPickCollector(?User $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->hasRole(['super-admin', 'isp-admin', 'admin'])
            || $user->can('collections.manage');
    }

    public function defaultCollectorId(?User $user = null): int
    {
        $user ??= auth()->user();

        return (int) ($user?->id ?? 0);
    }

    /**
     * @return array<int, string> id => display label
     */
    public function collectableStaffOptions(?int $tenantId = null): array
    {
        $tenantId ??= TenantResolver::requiredTenantId();

        $query = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        if ($this->canPickCollector()) {
            // Admin/manager: any active staff user in tenant.
            $query->orderBy('name');
        } else {
            $roles = config('collector.collectable_roles', ['cashier', 'branch-manager']);
            $query->where(function ($q) use ($roles): void {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roles));
                if (auth()->id()) {
                    $q->orWhere('id', auth()->id());
                }
            })->orderBy('name');
        }

        return $query->with('roles')->get(['id', 'name', 'email'])->mapWithKeys(function (User $user): array {
            $label = $user->name;
            $roles = $user->roles->pluck('name')->take(2)->implode(', ');
            if ($roles !== '') {
                $label .= ' · '.$roles;
            }
            if (auth()->id() === $user->id) {
                $label .= ' (me)';
            }

            return [$user->id => $label];
        })->all();
    }

    /**
     * Resolve which staff member owns this collection (settlement / due).
     */
    public function resolveCollectorUserIdFromPayment(Payment $payment): ?int
    {
        $meta = $payment->meta ?? [];
        if (! empty($meta['collector_attributed_to'])) {
            return (int) $meta['collector_attributed_to'];
        }

        return $payment->recorded_by !== null ? (int) $payment->recorded_by : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentMetaForCollector(int $collectorId, ?int $enteredBy = null): array
    {
        $enteredBy ??= (int) auth()->id();
        $meta = [
            'collector_attributed_to' => $collectorId,
        ];

        if ($enteredBy > 0 && $enteredBy !== $collectorId) {
            $enterer = User::query()->find($enteredBy);
            $meta['entered_by'] = $enteredBy;
            $meta['entered_by_name'] = $enterer?->name;
        }

        return $meta;
    }

    public function resolveCollectorUser(int $collectorId): User
    {
        return User::query()->findOrFail($collectorId);
    }
}
