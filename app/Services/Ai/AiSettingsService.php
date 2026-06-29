<?php

namespace App\Services\Ai;

use App\Models\Tenant;
use App\Support\TenantResolver;

final class AiSettingsService
{
    public function isEnabled(?int $tenantId = null): bool
    {
        return (bool) $this->get('enabled', true, $tenantId);
    }

    public function llmEnabled(?int $tenantId = null): bool
    {
        return $this->isEnabled($tenantId) && (bool) $this->get('llm.enabled', false, $tenantId);
    }

    public function bengaliReplies(?int $tenantId = null): bool
    {
        return (bool) $this->get('bengali_replies', true, $tenantId);
    }

    public function maskPii(?int $tenantId = null): bool
    {
        return (bool) $this->get('mask_pii', true, $tenantId);
    }

    public function customerAiEnabled(?int $tenantId = null): bool
    {
        return $this->isEnabled($tenantId) && (bool) $this->get('customer_ai_enabled', true, $tenantId);
    }

    public function resellerAiEnabled(?int $tenantId = null): bool
    {
        return $this->isEnabled($tenantId) && (bool) $this->get('reseller_ai_enabled', true, $tenantId);
    }

    public function proactiveDigestEnabled(?int $tenantId = null): bool
    {
        return $this->isEnabled($tenantId) && (bool) $this->get('proactive_digest_enabled', true, $tenantId);
    }

    public function ragEnabled(?int $tenantId = null): bool
    {
        return $this->isEnabled($tenantId) && (bool) $this->get('rag_enabled', true, $tenantId);
    }

    public function actionsEnabled(?int $tenantId = null): bool
    {
        return $this->isEnabled($tenantId) && (bool) $this->get('actions_enabled', true, $tenantId);
    }

    public function dailyQueryLimit(?int $tenantId = null): int
    {
        return max(10, (int) $this->get('daily_query_limit', 500, $tenantId));
    }

    /**
     * @return list<string>
     */
    public function allowedTools(?int $tenantId = null): array
    {
        $tools = $this->get('allowed_tools', config('ai.allowed_tools', []), $tenantId);

        return is_array($tools) ? array_values(array_filter(array_map('strval', $tools))) : [];
    }

    public function toolAllowed(string $tool, ?int $tenantId = null): bool
    {
        $allowed = $this->allowedTools($tenantId);

        return $allowed === [] || in_array($tool, $allowed, true);
    }

    public function withinDailyQuota(?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $count = \App\Models\AiInteractionLog::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return $count < $this->dailyQueryLimit($tenantId);
    }

  /**
     * @param  array<string, mixed>  $overrides
     */
    public function saveTenantOverrides(int $tenantId, array $overrides): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $ai = is_array($settings['ai'] ?? null) ? $settings['ai'] : [];
        $settings['ai'] = array_merge($ai, $overrides);
        $tenant->forceFill(['settings' => $settings])->saveQuietly();
    }

    public function get(string $key, mixed $default = null, ?int $tenantId = null): mixed
    {
        $tenantId = $tenantId ?? TenantResolver::currentTenantId();
        if ($tenantId !== null) {
            $tenant = Tenant::query()->find($tenantId);
            $ai = is_array($tenant?->settings['ai'] ?? null) ? $tenant->settings['ai'] : [];
            if (array_key_exists($key, $ai)) {
                return $ai[$key];
            }
            if (str_contains($key, '.')) {
                [$group, $sub] = explode('.', $key, 2);
                if (isset($ai[$group]) && is_array($ai[$group]) && array_key_exists($sub, $ai[$group])) {
                    return $ai[$group][$sub];
                }
            }
        }

        return config('ai.'.$key, $default);
    }
}
