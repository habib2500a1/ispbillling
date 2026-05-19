<?php

namespace App\Auth;

use App\Support\TenantResolver;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;

class CustomerUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier)
    {
        $model = $this->createModel();

        return TenantResolver::withoutAuthRecursion(
            fn () => $model->newQueryWithoutScopes()
                ->where($model->getAuthIdentifierName(), $identifier)
                ->first()
        );
    }

    public function retrieveByToken($identifier, $token)
    {
        $model = $this->createModel();

        $retrievedModel = TenantResolver::withoutAuthRecursion(
            fn () => $model->newQueryWithoutScopes()
                ->where($model->getAuthIdentifierName(), $identifier)
                ->first()
        );

        if (! $retrievedModel) {
            return null;
        }

        $rememberToken = $retrievedModel->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token) ? $retrievedModel : null;
    }

    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials)
            || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        $query = TenantResolver::withoutAuthRecursion(
            fn () => $this->newModelQuery($this->createModel())
        );

        foreach ($credentials as $key => $value) {
            if (str_contains($key, 'password')) {
                continue;
            }

            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    protected function newModelQuery($model = null)
    {
        $model ??= $this->createModel();

        return TenantResolver::withoutAuthRecursion(
            fn () => $model->newQueryWithoutScopes()
        );
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Portal login uses portal_password, not the default password column.
    }
}
