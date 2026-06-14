<?php

namespace App\Services\Import;

use App\Models\LegacyPortalMirrorRecord;
use App\Models\LegacyPortalMirrorRun;
use App\Support\TenantResolver;
use Illuminate\Support\Str;

final class LegacyPortalRawMirrorService
{
    public function startRun(string $baseUrl, string $mode = 'mirror', array $options = []): LegacyPortalMirrorRun
    {
        return LegacyPortalMirrorRun::create([
            'tenant_id' => $this->tenantId(),
            'run_uuid' => (string) Str::uuid(),
            'mode' => $mode,
            'base_url' => rtrim($baseUrl, '/'),
            'status' => 'running',
            'options' => $options,
            'started_at' => now(),
        ]);
    }

    public function finishRun(LegacyPortalMirrorRun $run, string $status = 'completed', array $summary = []): void
    {
        $run->update([
            'status' => $status,
            'summary' => array_merge($run->summary ?? [], $summary),
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  mixed  $payload
     */
    public function record(
        LegacyPortalMirrorRun $run,
        string $domain,
        string $method,
        string $url,
        array $request,
        mixed $payload,
        ?string $sourceKey = null,
        ?int $httpStatus = 200,
        ?string $contentType = 'application/json',
    ): LegacyPortalMirrorRecord {
        $jsonPayload = is_array($payload) ? $payload : null;
        $textPayload = is_string($payload) ? $payload : null;
        $encoded = $jsonPayload !== null
            ? json_encode($jsonPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $textPayload;
        $checksum = hash('sha256', (string) $encoded);

        return LegacyPortalMirrorRecord::firstOrCreate(
            [
                'legacy_portal_mirror_run_id' => $run->id,
                'domain' => $domain,
                'source_key' => $sourceKey,
                'checksum' => $checksum,
            ],
            [
                'tenant_id' => $run->tenant_id,
                'method' => strtoupper($method),
                'url' => $url,
                'request' => $request,
                'http_status' => $httpStatus,
                'content_type' => $contentType,
                'payload_json' => $jsonPayload,
                'payload_text' => $textPayload,
                'fetched_at' => now(),
            ],
        );
    }

    private function tenantId(): ?int
    {
        try {
            return TenantResolver::currentTenantId();
        } catch (\Throwable) {
            return null;
        }
    }
}
