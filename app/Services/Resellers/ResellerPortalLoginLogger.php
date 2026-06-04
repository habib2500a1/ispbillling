<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerPortalLoginLog;
use App\Models\ResellerStaff;
use Illuminate\Http\Request;

final class ResellerPortalLoginLogger
{
    public function logAttempt(
        ?Reseller $reseller,
        Request $request,
        bool $success,
        ?string $loginId = null,
        ?ResellerStaff $staff = null,
        ?string $failureReason = null,
    ): void {
        if ($reseller === null) {
            return;
        }

        ResellerPortalLoginLog::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'reseller_staff_id' => $staff?->id,
            'login_id' => $loginId,
            'success' => $success,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'device_fingerprint' => $request->header('X-Device-Fingerprint'),
            'failure_reason' => $failureReason,
            'created_at' => now(),
        ]);
    }

    public function isIpAllowed(Reseller $reseller, ?string $ip): bool
    {
        $allowed = $reseller->allowed_ips;
        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        if ($ip === null || $ip === '') {
            return false;
        }

        return in_array($ip, $allowed, true);
    }
}
