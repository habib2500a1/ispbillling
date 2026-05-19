<?php

namespace App\Services\Notifications\Channels;

use App\Support\NotificationChannel;
use Illuminate\Support\Facades\Http;

final class TelegramNotificationChannel implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::TELEGRAM;
    }

    public function isEnabled(): bool
    {
        return (bool) config('notifications.telegram.enabled', false)
            && filled(config('notifications.telegram.bot_token'));
    }

    public function send(string $recipient, string $message, array $context = []): void
    {
        $chatId = $recipient !== '' ? $recipient : (string) config('notifications.telegram.ops_chat_id', '');
        if ($chatId === '') {
            throw new \InvalidArgumentException('Telegram chat ID is required.');
        }

        $token = (string) config('notifications.telegram.bot_token');
        $response = Http::timeout(20)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Telegram API '.$response->status().': '.$response->json('description', $response->body()));
        }
    }
}
