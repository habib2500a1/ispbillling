<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerApiKey;
use App\Models\ResellerApiUsageLog;
use Illuminate\Http\Request;

final class ResellerApiKeyService
{
    /**
     * @return array{model: ResellerApiKey, plain: string}
     */
    public function create(Reseller $reseller, string $name, ?array $abilities = null): array
    {
        if (! $reseller->api_access_enabled) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'api' => 'API access is not enabled for this reseller account.',
            ]);
        }

        return ResellerApiKey::generate($reseller, $name, $abilities);
    }

    public function revoke(ResellerApiKey $key): void
    {
        $key->update(['is_active' => false]);
    }

    public function logUsage(
        ResellerApiKey $key,
        Request $request,
        int $statusCode,
        int $durationMs,
    ): void {
        ResellerApiUsageLog::query()->create([
            'tenant_id' => $key->tenant_id,
            'reseller_api_key_id' => $key->id,
            'reseller_id' => $key->reseller_id,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status_code' => $statusCode,
            'ip_address' => $request->ip(),
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);

        $key->update(['last_used_at' => now()]);
    }
}
