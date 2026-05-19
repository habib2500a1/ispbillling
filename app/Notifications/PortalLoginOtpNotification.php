<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalLoginOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $code,
        protected int $ttlSeconds,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = max(1, (int) ceil($this->ttlSeconds / 60));

        return (new MailMessage)
            ->subject(__('Your portal login code'))
            ->line(__('Use this one-time code to finish signing in to the customer portal.'))
            ->line(__('Code: :code', ['code' => $this->code]))
            ->line(__('This code expires in :count minutes.', ['count' => $minutes]));
    }
}
