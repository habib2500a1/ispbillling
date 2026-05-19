<?php

namespace App\Services\Notifications\Channels;

use App\Support\NotificationChannel;
use Illuminate\Support\Facades\Mail;

final class EmailNotificationChannel implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::EMAIL;
    }

    public function isEnabled(): bool
    {
        return (bool) config('notifications.email.enabled', true);
    }

    public function send(string $recipient, string $message, array $context = []): void
    {
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        $subject = (string) ($context['subject'] ?? 'Notification from your ISP');

        Mail::raw($message, function ($mail) use ($recipient, $subject): void {
            $mail->to($recipient)->subject($subject);
        });
    }
}
