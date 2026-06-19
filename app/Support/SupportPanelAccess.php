<?php

namespace App\Support;

use App\Models\User;
use App\Support\Rbac\StaffCapability;

/**
 * Ticket / support UI authorization without requiring Spatie permission rows
 * (avoids PermissionDoesNotExist when migrations ran but seeders did not).
 */
final class SupportPanelAccess
{
    /** Roles that may appear in ticket assignee pickers (auto-assign uses a subset). */
    private const ASSIGNABLE_ROLES = [
        'super-admin',
        'isp-admin',
        'admin',
        'isp-manager',
        'branch-manager',
        'isp-support',
        'support-agent',
        'isp-engineer',
        'technician',
        'noc-engineer',
        'mikrotik-engineer',
        'gpon-engineer',
    ];

    /** Roles allowed to search subscribers when creating tickets. */
    private const TICKET_SEARCH_ROLES = [
        'super-admin',
        'isp-admin',
        'admin',
        'isp-manager',
        'branch-manager',
        'isp-support',
        'support-agent',
        'isp-engineer',
        'technician',
        'cashier',
        'collector',
    ];
    public static function viewTickets(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(StaffCapability::FULL_ACCESS_ROLES)) {
            return true;
        }

        return $user->hasAnyRole([
            'super-admin',
            'isp-admin',
            'admin',
            'isp-support',
            'isp-engineer',
            'isp-manager',
        ]) || $user->can('support.view');
    }

    public static function manageTickets(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(StaffCapability::FULL_ACCESS_ROLES)) {
            return true;
        }

        return $user->hasAnyRole([
            'super-admin',
            'isp-admin',
            'admin',
            'isp-support',
            'isp-manager',
            'isp-engineer',
        ]) || $user->can('support.manage') || $user->can('support.view');
    }

    public static function assignTickets(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]);
    }

    public static function manageKnowledge(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]);
    }

    public static function manageOutages(?User $user): bool
    {
        return self::manageKnowledge($user);
    }

    public static function canSearchTicketSubscribers(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(StaffCapability::FULL_ACCESS_ROLES)) {
            return true;
        }

        return $user->hasAnyRole(self::TICKET_SEARCH_ROLES) || $user->can('support.view');
    }

    /**
     * @return array<string, string>
     */
    public static function assignableStaffOptions(?int $includeUserId = null): array
    {
        $options = self::assignableStaffQuery()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [(string) $user->id => self::staffOptionLabel($user)])
            ->all();

        if ($options === []) {
            $options = User::query()
                ->where('is_active', true)
                ->with('roles')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (User $user): array => [(string) $user->id => self::staffOptionLabel($user)])
                ->all();
        }

        if ($includeUserId !== null && ! isset($options[(string) $includeUserId])) {
            $extra = User::query()->with('roles')->find($includeUserId);
            if ($extra !== null) {
                $suffix = $extra->is_active ? '' : ' (inactive)';
                $options[(string) $extra->id] = self::staffOptionLabel($extra).$suffix;
            }
        }

        return $options;
    }

    public static function assignableStaffQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = User::query()->where('is_active', true);

        $user = auth()->user();
        if ($user !== null && ! $user->hasRole(StaffCapability::FULL_ACCESS_ROLES)) {
            $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', self::ASSIGNABLE_ROLES));
        }

        return $query;
    }

    public static function staffOptionLabel(User $user): string
    {
        $role = $user->roles->first()?->name;
        if ($role === null) {
            return $user->name;
        }

        $label = str_replace(['-', '_'], ' ', $role);

        return $user->name.' · '.$label;
    }

    public static function applyAssignableStaffScope(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}
