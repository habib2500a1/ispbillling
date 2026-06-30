<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\Rbac\StaffCapability;

final class AiToolPermissionGuard
{
    public function canRunTool(string $tool, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $cap = StaffCapability::for($user);
        $prefix = explode('.', $tool, 2)[0] ?? '';

        return match ($prefix) {
            'billing', 'bi', 'actions' => $cap->canBilling() || $cap->canReports(),
            'network', 'gis' => $cap->canNetwork() || $cap->canReports(),
            'support' => $cap->canSupport() || $cap->canReports(),
            'inventory' => $cap->canInventory() || $cap->canReports(),
            'hr' => $cap->canHrm() || $cap->canReports(),
            default => $cap->canReports(),
        };
    }

    public function assertCanRunTool(string $tool, ?User $user): void
    {
        abort_unless($this->canRunTool($tool, $user), 403, 'You do not have permission to run this AI tool.');
    }
}
