<?php

namespace App\Services\Notifications\Channels;

use App\Support\NotificationChannel;
use Illuminate\Support\Facades\Http;

final class WhatsAppNotificationChannel implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::WHATSAPP;
    }

    public function isEnabled(): bool
    {
        return (bool) config('notifications.whatsapp.enabled', false)
            && filled(config('notifications.whatsapp.phone_number_id'))
            && filled(config('notifications.whatsapp.access_token'));
    }

    public function send(string $recipient, string $message, array $context = []): void
    {
        $phone = preg_replace('/\D+/', '', $recipient) ?? '';
        if ($phone === '') {
            throw new \InvalidArgumentException('Invalid WhatsApp number.');
        }

        $phoneNumberId = (string) config('notifications.whatsapp.phone_number_id');
        $version = (string) config('notifications.whatsapp.api_version', 'v21.0');
        $token = (string) config('notifications.whatsapp.access_token');

        $response = Http::timeout(25)
            ->withToken($token)
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('WhatsApp API '.$response->status().': '.$response->body());
        }
    }
}
