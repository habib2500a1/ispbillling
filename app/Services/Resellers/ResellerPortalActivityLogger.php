<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerPortalActivityLog;
use App\Support\ResellerPortalSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class ResellerPortalActivityLogger
{
    public function log(Reseller $reseller, string $action, ?Model $subject = null, array $meta = [], ?Request $request = null): void
    {
        $portal = app(ResellerPortalSession::class);
        $request ??= request();

        ResellerPortalActivityLog::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'reseller_staff_id' => $portal->staff()?->id,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta !== [] ? $meta : null,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
