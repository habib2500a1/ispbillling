<?php

use App\Models\Reseller;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}.dashboard', function (User $user, int $tenantId): bool {
    return (int) $user->tenant_id === $tenantId;
});

Broadcast::channel('tenant.{tenantId}.mobile', function (User $user, int $tenantId): bool {
    return (int) $user->tenant_id === $tenantId;
});

Broadcast::channel('tenant.{tenantId}.reseller.{resellerId}', function ($user, int $tenantId, int $resellerId): bool {
    if ($user instanceof Reseller) {
        return (int) $user->tenant_id === $tenantId && (int) $user->id === $resellerId;
    }

    return false;
});
