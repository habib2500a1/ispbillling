<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Support\PlatformSuperAdmin;
use App\Support\PrimaryTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

final class TenantObserver
{
    public function creating(Tenant $tenant): void
    {
        $this->assertPlatformSuperAdmin('create tenants');
    }

    public function updating(Tenant $tenant): void
    {
        $this->assertPlatformSuperAdmin('update tenants');

        if (PrimaryTenant::isPrimary($tenant->getKey()) && $tenant->isDirty('is_active') && ! $tenant->is_active) {
            $tenant->is_active = true;
        }
    }

    public function deleting(Tenant $tenant): void
    {
        $this->assertPlatformSuperAdmin('delete tenants');

        if (PrimaryTenant::isPrimary($tenant->getKey())) {
            throw new AuthorizationException('Primary ISP tenant cannot be deleted.');
        }
    }

    private function assertPlatformSuperAdmin(string $action): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $user = Auth::user();
        if ($user !== null && ! PlatformSuperAdmin::allows($user)) {
            throw new AuthorizationException("Only the platform super-admin may {$action}.");
        }
    }
}
