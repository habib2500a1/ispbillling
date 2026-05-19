<?php

namespace App\Services\Staff;

use App\Models\ActivityLog;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $event,
        ?string $description = null,
        ?Model $subject = null,
        array $properties = [],
        ?string $logName = 'staff',
    ): ActivityLog {
        $user = auth()->user();
        $tenantId = $user?->tenant_id ?? TenantResolver::currentTenantId();

        return ActivityLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
            'log_name' => $logName,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties !== [] ? $properties : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
