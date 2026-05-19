<?php

namespace App\Filament\Resources\SmsTemplateResource\Pages;

use App\Filament\Resources\SmsTemplateResource;
use App\Models\SmsTemplate;
use Filament\Resources\Pages\ListRecords;

class ListSmsTemplates extends ListRecords
{
    protected static string $resource = SmsTemplateResource::class;

    protected static string $view = 'filament.resources.sms-template-resource.pages.list-sms-templates';

    /**
     * @return array{total: int, enabled: int, disabled: int}
     */
    public function getTemplateStats(): array
    {
        $base = SmsTemplate::query();

        return [
            'total' => (int) (clone $base)->count(),
            'enabled' => (int) (clone $base)->where('is_enabled', true)->count(),
            'disabled' => (int) (clone $base)->where('is_enabled', false)->count(),
        ];
    }
}
