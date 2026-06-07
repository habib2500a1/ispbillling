<?php

namespace App\Services\Sms;

use App\Models\SmsTemplate;
use App\Support\SmsTemplateCatalog;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Schema;

final class SmsTemplateService
{
    public function isEnabled(string $eventOrKey, ?int $tenantId = null): bool
    {
        if (! Schema::hasTable('sms_templates')) {
            return (bool) config("notifications.events.{$eventOrKey}.enabled", true);
        }

        $template = $this->find($eventOrKey, $tenantId);
        if ($template !== null) {
            return (bool) $template->is_enabled;
        }

        return (bool) config("notifications.events.{$eventOrKey}.enabled", true);
    }

    /**
     * Voice is used when SMS is off for this template (fallback reminder call).
     */
    public function isVoiceFallbackEnabled(string $eventOrKey, ?int $tenantId = null): bool
    {
        if (! config('call_center.voice_call.enabled', false)) {
            return false;
        }

        if (! Schema::hasTable('sms_templates')) {
            return false;
        }

        $template = $this->find($eventOrKey, $tenantId);
        if ($template === null) {
            return false;
        }

        return (bool) ($template->voice_enabled ?? true) && $template->voice_template_id !== null;
    }

    /** @deprecated Use isVoiceFallbackEnabled — kept for callers */
    public function isVoiceEnabled(string $eventOrKey, ?int $tenantId = null): bool
    {
        return $this->isVoiceFallbackEnabled($eventOrKey, $tenantId);
    }

    public function voiceTemplateFor(string $eventOrKey, ?int $tenantId = null): ?\App\Models\VoiceTemplate
    {
        if (! $this->isVoiceFallbackEnabled($eventOrKey, $tenantId)) {
            return null;
        }

        $template = $this->find($eventOrKey, $tenantId);
        if ($template === null || $template->voice_template_id === null) {
            return null;
        }

        return \App\Models\VoiceTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $template->tenant_id)
            ->where('id', $template->voice_template_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    public function render(string $eventOrKey, array $variables = [], ?int $tenantId = null): string
    {
        $template = $this->find($eventOrKey, $tenantId);

        if ($template !== null) {
            if (! $template->is_enabled) {
                return '';
            }

            $body = $template->body;
        } else {
            $body = (string) config("notifications.templates.{$eventOrKey}", '');
        }

        if ($body === '') {
            return '';
        }

        $merged = array_merge(SmsTemplateVariableBuilder::defaults(), $variables);
        $replacements = [];
        foreach ($merged as $key => $value) {
            $replacements['{'.$key.'}'] = (string) ($value ?? '');
        }

        return strtr($body, $replacements);
    }

    public function find(string $eventOrKey, ?int $tenantId = null): ?SmsTemplate
    {
        if (! Schema::hasTable('sms_templates')) {
            return null;
        }

        return SmsTemplate::findByKey($eventOrKey, $tenantId);
    }

    public function seedDefaults(?int $tenantId = null): int
    {
        if (! Schema::hasTable('sms_templates')) {
            return 0;
        }

        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $count = 0;

        foreach (SmsTemplateCatalog::defaults() as $row) {
            SmsTemplate::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'key' => $row['key']],
                [
                    'name' => $row['name'],
                    'template_type' => 'default',
                    'event_key' => $row['event_key'],
                    'body' => $row['body'],
                    'placeholders' => $row['placeholders'],
                    'is_enabled' => true,
                    'sort_order' => $row['sort_order'],
                ],
            );
            $count++;
        }

        return $count;
    }

    /** Add templates shipped in GitHub that are not in the database yet (deploy-safe). */
    public function syncMissingDefaults(?int $tenantId = null): int
    {
        if (! Schema::hasTable('sms_templates')) {
            return 0;
        }

        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $created = 0;

        foreach (SmsTemplateCatalog::defaults() as $row) {
            $exists = SmsTemplate::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('key', $row['key'])
                ->exists();

            if ($exists) {
                continue;
            }

            SmsTemplate::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'key' => $row['key'],
                'name' => $row['name'],
                'template_type' => 'default',
                'event_key' => $row['event_key'],
                'body' => $row['body'],
                'placeholders' => $row['placeholders'],
                'is_enabled' => true,
                'sort_order' => $row['sort_order'],
            ]);
            $created++;
        }

        return $created;
    }
}
