<?php

namespace App\Services\Notifications;

use App\Services\Sms\SmsTemplateService;

final class MessageTemplateRenderer
{
    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    public static function render(string $event, array $variables = []): string
    {
        return app(SmsTemplateService::class)->render($event, $variables);
    }
}
