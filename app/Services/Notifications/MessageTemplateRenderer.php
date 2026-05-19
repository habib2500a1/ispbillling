<?php

namespace App\Services\Notifications;

final class MessageTemplateRenderer
{
    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    public static function render(string $event, array $variables = []): string
    {
        $template = (string) config("notifications.templates.{$event}", '');

        if ($template === '') {
            return '';
        }

        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = (string) ($value ?? '');
        }

        return strtr($template, $replacements);
    }
}
