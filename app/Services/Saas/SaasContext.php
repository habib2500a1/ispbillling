<?php

namespace App\Services\Saas;

use App\Models\SaasOperator;
use App\Models\User;

final class SaasContext
{
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

    public static function isPlatformOwner(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('Super Admin') && ! $user->hasRole('Operator');
    }
}
