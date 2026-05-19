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

        return $template?->is_enabled ?? (bool) config("notifications.events.{$eventOrKey}.enabled", true);
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
}
