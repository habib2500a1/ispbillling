<?php

namespace App\Services\Saas;

use App\Models\SaasOperator;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class SaasContext
{
    private static ?SaasOperator $hostOperator = null;

    private static bool $hostResolved = false;

    private static ?int $forcedTenantId = null;

    public static function rememberHostOperator(?SaasOperator $operator): void
    {
        self::$hostOperator = $operator;
        self::$hostResolved = true;
    }

    public static function hostOperator(): ?SaasOperator
    {
        if (! self::$hostResolved) {
            self::$hostResolved = true;
            self::$hostOperator = SaasDomain::findByHost(request()->getHost());
        }

        return self::$hostOperator;
    }

    public static function operator(?User $user = null): ?SaasOperator
    {
        $user ??= auth()->user();
        if (! $user) {
            return null;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin') && ! $user->hasRole('Operator')) {
            return null;
        }

        if (method_exists($user, 'saasOperator')) {
            $own = $user->saasOperator;
            if ($own) {
                return $own;
            }
        }

        if (! empty($user->saas_operator_id)) {
            return SaasOperator::query()->find($user->saas_operator_id);
        }

        return SaasOperator::query()->where('user_id', $user->id)->first();
    }

    public static function operatorId(?User $user = null): ?int
    {
        return self::operator($user)?->id;
    }

    public static function forceTenant(?int $id): void
    {
        self::$forcedTenantId = $id && $id > 0 ? $id : null;
    }

    public static function forcedTenantId(): ?int
    {
        return self::$forcedTenantId;
    }

    public static function tenantId(?User $user = null): ?int
    {
        if (self::$forcedTenantId) {
            return self::$forcedTenantId;
        }

        return self::operatorId($user) ?? self::hostOperator()?->id;
    }

    public static function isPlatformOwner(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('Super Admin') && ! $user->hasRole('Operator');
    }

    /**
     * tenant = sold ISP only, platform = Anetbd (null tenant), all = no filter (cron / tests).
     */
    public static function tenantScopeMode(): string
    {
        if (self::operatorId()) {
            return 'tenant';
        }

        if (app()->runningInConsole() && ! auth()->check()) {
            return 'all';
        }

        if (! auth()->check() && self::hostOperator()) {
            return 'tenant';
        }

        return 'platform';
    }

    public static function constrainToTenantCustomers($query, string $column): void
    {
        if (! Schema::hasTable('customers_infos') || ! Schema::hasColumn('customers_infos', 'saas_operator_id')) {
            return;
        }

        $mode = self::tenantScopeMode();
        if ($mode === 'all') {
            return;
        }

        if ($mode === 'tenant') {
            $id = self::tenantId();
            $query->whereIn($column, function ($q) use ($id) {
                $q->select('customer_unique_id')->from('customers_infos')->where('saas_operator_id', $id);
            });

            return;
        }

        $query->whereIn($column, function ($q) {
            $q->select('customer_unique_id')->from('customers_infos')->whereNull('saas_operator_id');
        });
    }
}
