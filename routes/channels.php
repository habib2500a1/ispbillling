<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}.dashboard', function (User $user, int $tenantId): bool {
    return (int) $user->tenant_id === $tenantId;
});

Broadcast::channel('tenant.{tenantId}.mobile', function (User $user, int $tenantId): bool {
    return (int) $user->tenant_id === $tenantId;
});
