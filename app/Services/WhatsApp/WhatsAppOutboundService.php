<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;

final class WhatsAppOutboundService
{
    public function isConfigured(): bool
    {
        return (bool) config('notifications.whatsapp.enabled', false)
            && filled(config('notifications.whatsapp.phone_number_id'))
            && filled(config('notifications.whatsapp.access_token'));
    }

    public function sendText(string $phoneE164Digits, string $message): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $phone = preg_replace('/\D+/', '', $phoneE164Digits) ?? '';
        if ($phone === '') {
            return;
        }

        $phoneNumberId = (string) config('notifications.whatsapp.phone_number_id');
        $version = (string) config('notifications.whatsapp.api_version', 'v21.0');
        $token = (string) config('notifications.whatsapp.access_token');

        Http::timeout(25)
            ->withToken($token)
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
            ])
            ->throw();
    }
}
