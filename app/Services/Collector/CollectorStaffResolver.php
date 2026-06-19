<?php

namespace App\Services\Collector;

use App\Models\Payment;
use App\Models\User;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
     * Field staff (cashier/collector) see only their own collections in reports; admin/finance see all.
     */
    public function scopedCollectorIdForReports(?User $user = null): ?int
    {
        $user ??= auth()->user();
        if ($user === null || $this->canPickCollector($user)) {
            return null;
        }

        $capability = StaffCapability::for($user);
        if ($capability->canCollect() || $capability->canAny(['payments.add'])) {
            return $this->defaultCollectorId($user);
        }

        return null;
    }

    public function paymentBelongsToCollector(Payment $payment, int $collectorId): bool
    {
        if ($collectorId < 1) {
            return false;
        }

        $attributed = $this->resolveCollectorUserIdFromPayment($payment);

        return $attributed === $collectorId
            || (int) ($payment->recorded_by ?? 0) === $collectorId;
    }

    public function scopePaymentsToCollector(Builder $query, int $collectorId): Builder
    {
        $user = User::query()->find($collectorId);
        $nameKeys = [];
        if ($user !== null) {
            $nameKeys[] = mb_strtolower(trim($user->name));
            if (str_contains($user->email, '@import.local')) {
                $local = Str::contains($user->email, 'legacyportal+')
                    ? Str::before(Str::after($user->email, 'legacyportal+'), '@')
                    : Str::before(Str::after($user->email, 'ispdigital+'), '@');
                if ($local !== '') {
                    $nameKeys[] = mb_strtolower($local);
                }
            }
        }
        $nameKeys = array_values(array_unique(array_filter($nameKeys)));

        return $query->where(function (Builder $q) use ($collectorId, $nameKeys): void {
            $q->where('recorded_by', $collectorId)
                ->orWhere('meta->collector_attributed_to', $collectorId);

            foreach ($nameKeys as $nameKey) {
                $q->orWhereRaw('LOWER(meta->>\'received_by\') = ?', [$nameKey]);
            }
        });
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
            // Field collector: only themselves — cannot attribute to another staff.
            $selfId = auth()->id();
            if ($selfId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('id', $selfId);
            }
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
        $meta = is_array($payment->meta) ? $payment->meta : [];
        if (! empty($meta['collector_attributed_to'])) {
            return (int) $meta['collector_attributed_to'];
        }

        if ($payment->recorded_by !== null) {
            return (int) $payment->recorded_by;
        }

        $receivedBy = trim((string) ($meta['received_by'] ?? ''));
        if ($receivedBy !== '') {
            $tenantId = (int) ($payment->tenant_id ?? TenantResolver::requiredTenantId());
            $resolved = $this->resolveStaffUserIdFromName($receivedBy, $tenantId);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    public function resolveStaffDisplayNameFromPayment(Payment $payment, ?int $tenantId = null): ?string
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $staffId = $this->resolveCollectorUserIdFromPayment($payment);
        if ($staffId !== null) {
            return User::query()->find($staffId)?->name;
        }

        $receivedBy = trim((string) ($meta['received_by'] ?? ''));
        if ($receivedBy !== '') {
            return $receivedBy;
        }

        return $payment->recorder?->name;
    }

    public function resolveStaffUserIdFromName(string $name, int $tenantId): ?int
    {
        $key = mb_strtolower(trim($name));
        if ($key === '') {
            return null;
        }

        $lookup = $this->staffNameLookup($tenantId);

        return $lookup[$key]['id'] ?? null;
    }

    /**
     * @return array<string, array{id: int, label: string}>
     */
    public function staffNameLookup(int $tenantId): array
    {
        return Cache::remember(
            'collector:staff_name_lookup:'.$tenantId,
            now()->addMinutes(30),
            function () use ($tenantId): array {
                $lookup = [];

                foreach (User::query()->where('tenant_id', $tenantId)->get(['id', 'name', 'email']) as $user) {
                    $lookup[mb_strtolower($user->name)] = ['id' => (int) $user->id, 'label' => $user->name];

                    if (str_contains($user->email, '@import.local')) {
                        $local = Str::contains($user->email, 'legacyportal+')
                            ? Str::before(Str::after($user->email, 'legacyportal+'), '@')
                            : Str::before(Str::after($user->email, 'ispdigital+'), '@');
                        if ($local !== '') {
                            $lookup[mb_strtolower($local)] = ['id' => (int) $user->id, 'label' => $user->name];
                        }
                    }
                }

                return $lookup;
            },
        );
    }

    public function flushStaffNameLookup(int $tenantId): void
    {
        Cache::forget('collector:staff_name_lookup:'.$tenantId);
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

    /**
     * Collection entry must be credited to this user (self for field staff).
     */
    public function requireSelfCollectorId(?int $requestedCollectorId = null, ?User $user = null): int
    {
        $user ??= auth()->user();
        $selfId = $this->defaultCollectorId($user);

        if ($this->canPickCollector($user)) {
            if ($requestedCollectorId !== null && $requestedCollectorId > 0) {
                if ($requestedCollectorId === $selfId) {
                    return $selfId;
                }

                $options = $this->collectableStaffOptions($user?->tenant_id);

                if (! array_key_exists($requestedCollectorId, $options)) {
                    throw ValidationException::withMessages([
                        'collector_user_id' => 'Select a valid staff member for this collection.',
                    ]);
                }

                return $requestedCollectorId;
            }

            if ($selfId > 0) {
                return $selfId;
            }

            throw ValidationException::withMessages([
                'collector_user_id' => 'Select which staff member receives credit for this collection.',
            ]);
        }

        if ($requestedCollectorId !== null && $requestedCollectorId > 0 && $requestedCollectorId !== $selfId) {
            throw ValidationException::withMessages([
                'collector_user_id' => 'You can only record collections under your own name.',
            ]);
        }

        return $selfId;
    }
}
