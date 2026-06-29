<?php

namespace App\Services\Ai;

use App\Models\AiInteractionLog;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\User;
use App\Support\TenantResolver;

final class AiAuditLogger
{
    public function __construct(
        private readonly AiSettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(
        string $channel,
        string $query,
        ?string $reply,
        ?string $tool = null,
        ?string $domain = null,
        bool $llmUsed = false,
        ?int $latencyMs = null,
        ?object $actor = null,
        array $meta = [],
        ?int $tenantId = null,
    ): void {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $maskedQuery = $this->settings->maskPii($tenantId) ? $this->maskText($query) : $query;
        $maskedReply = $reply !== null && $this->settings->maskPii($tenantId) ? $this->maskText($reply) : $reply;

        AiInteractionLog::createTrusted([
            'tenant_id' => $tenantId,
            'channel' => $channel,
            'actor_type' => $actor !== null ? class_basename($actor) : null,
            'actor_id' => $this->actorId($actor),
            'locale' => $this->detectLocale($query),
            'query' => $maskedQuery,
            'reply' => $maskedReply,
            'tool' => $tool,
            'domain' => $domain,
            'latency_ms' => $latencyMs,
            'llm_used' => $llmUsed,
            'meta' => $meta,
        ]);
    }

    private function actorId(?object $actor): ?int
    {
        if ($actor === null) {
            return null;
        }

        return match (true) {
            $actor instanceof User, $actor instanceof Customer, $actor instanceof Reseller => (int) $actor->getKey(),
            method_exists($actor, 'getKey') => (int) $actor->getKey(),
            default => null,
        };
    }

    private function detectLocale(string $text): string
    {
        return preg_match('/[\x{0980}-\x{09FF}]/u', $text) ? 'bn' : 'en';
    }

    private function maskText(string $text): string
    {
        $masked = preg_replace('/\b01[3-9]\d{8}\b/', '01*********', $text) ?? $text;
        $masked = preg_replace('/\b\d{10,17}\b/', '***********', $masked) ?? $masked;

        return $masked;
    }
}
