<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketPublicReplyCustomerMail extends Notification
{
    public function __construct(public SupportTicket $ticket, public string $snippet) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('portal.tickets.show', $this->ticket);

        return (new MailMessage)
            ->subject('Update on ticket '.$this->ticket->ticket_number)
            ->line('Support added a reply to your ticket.')
            ->line(mb_substr($this->snippet, 0, 400).(mb_strlen($this->snippet) > 400 ? '…' : ''))
            ->action('View ticket', $url);
    }
}
