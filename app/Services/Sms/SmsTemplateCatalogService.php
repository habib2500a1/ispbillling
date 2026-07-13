<?php

namespace App\Services\Sms;

use App\Models\SmsTemplate;
use App\Support\SmsTemplateCatalog;
use Illuminate\Support\Facades\Schema;

final class SmsTemplateCatalogService
{
    public function syncMissing(): int
    {
        if (! Schema::hasTable('sms_templates')) {
            return 0;
        }

        $created = 0;

        foreach (SmsTemplateCatalog::defaults() as $row) {
            $exists = SmsTemplate::query()
                ->where('template_name', $row['key'])
                ->exists();

            if ($exists) {
                continue;
            }

            SmsTemplate::query()->create([
                'template_name' => $row['key'],
                'display_name' => $row['name'],
                'event_key' => $row['event_key'],
                'template' => $row['body'],
                'template_ex_en' => $row['example_en'] ?? '',
                'template_ex_bn' => $row['example_bn'] ?? '',
                'placeholders' => $row['placeholders'],
                'sort_order' => $row['sort_order'],
                'is_active' => true,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * @return array{total: int, enabled: int, disabled: int}
     */
    public function stats(): array
    {
        if (! Schema::hasTable('sms_templates')) {
            return ['total' => 0, 'enabled' => 0, 'disabled' => 0];
        }

        $total = SmsTemplate::query()->count();
        $enabled = SmsTemplate::query()->where('is_active', true)->count();

        return [
            'total' => $total,
            'enabled' => $enabled,
            'disabled' => $total - $enabled,
        ];
    }
}
