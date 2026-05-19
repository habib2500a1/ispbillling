<?php

namespace App\Services\Notifications\Channels;

use App\Services\Notifications\Gateways\KhudeBartaSmsGateway;
use App\Support\NotificationChannel;
use Illuminate\Support\Facades\Http;

final class SmsNotificationChannel implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::SMS;
    }

    public function isEnabled(): bool
    {
        if (! (bool) config('notifications.sms.enabled', false)) {
            return false;
        }

        $provider = (string) config('notifications.sms.provider', 'bulksmsbd');
        if ($provider === 'khudebarta') {
            return filled(config('notifications.sms.api_key'))
                && filled(config('notifications.sms.secret_key'));
        }

        return filled(config('notifications.sms.api_key'));
    }

    public function send(string $recipient, string $message, array $context = []): void
    {
        $phone = $this->normalizePhone($recipient);
        if ($phone === '') {
            throw new \InvalidArgumentException('Invalid phone number.');
        }

        $provider = (string) config('notifications.sms.provider', 'bulksmsbd');

        match ($provider) {
            'khudebarta' => app(KhudeBartaSmsGateway::class)->send($phone, $message, $context),
            'sslwireless' => $this->sendSslWireless($phone, $message),
            'custom' => $this->sendCustomHttp($phone, $message),
            default => $this->sendBulksmsbd($phone, $message),
        };
    }

    private function sendBulksmsbd(string $phone, string $message): void
    {
        $response = Http::timeout(20)
            ->asForm()
            ->post((string) config('notifications.sms.api_url', 'https://bulksmsbd.net/api/smsapi'), [
                'api_key' => (string) config('notifications.sms.api_key'),
                'senderid' => (string) config('notifications.sms.sender_id', 'ISP'),
                'number' => $phone,
                'message' => $message,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('SMS gateway HTTP '.$response->status().': '.$response->body());
        }
    }

    private function sendSslWireless(string $phone, string $message): void
    {
        $response = Http::timeout(20)
            ->asForm()
            ->post((string) config('notifications.sms.api_url'), [
                'api_token' => (string) config('notifications.sms.api_key'),
                'sid' => (string) config('notifications.sms.sender_id'),
                'msisdn' => $phone,
                'sms' => $message,
                'csms_id' => uniqid('isp_', true),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('SSL Wireless SMS HTTP '.$response->status());
        }
    }

    private function sendCustomHttp(string $phone, string $message): void
    {
        $response = Http::timeout(20)
            ->post((string) config('notifications.sms.api_url'), [
                'api_key' => (string) config('notifications.sms.api_key'),
                'sender' => (string) config('notifications.sms.sender_id'),
                'to' => $phone,
                'message' => $message,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Custom SMS HTTP '.$response->status());
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '880')) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '88'.$digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '880'.$digits;
        }

        return $digits;
    }
}
