<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DeviceTokenService
{
    public function register(Model $owner, string $app, string $platform, string $token): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            [
                'tokenable_type' => $owner::class,
                'tokenable_id' => $owner->getKey(),
                'token' => $token,
            ],
            [
                'app' => $app,
                'platform' => $platform,
                'last_used_at' => now(),
            ]
        );
    }

    public function unregister(Model $owner, string $token): void
    {
        DeviceToken::query()
            ->where('tokenable_type', $owner::class)
            ->where('tokenable_id', $owner->getKey())
            ->where('token', $token)
            ->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DeviceToken>
     */
    public function tokensFor(Model $owner, ?string $app = null)
    {
        $query = DeviceToken::query()
            ->where('tokenable_type', $owner::class)
            ->where('tokenable_id', $owner->getKey());

        if ($app !== null) {
            $query->where('app', $app);
        }

        return $query->get();
    }
}
