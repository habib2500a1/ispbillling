<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketCustomerOpenedMail extends Notification
{
    public function __construct(public SupportTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('portal.tickets.show', $this->ticket);

        return (new MailMessage)
            ->subject('We received your ticket '.$this->ticket->ticket_number)
            ->line('Thanks for contacting support.')
            ->line('**Tracking ID:** '.$this->ticket->ticket_number)
            ->line('**Subject:** '.$this->ticket->subject)
            ->action('View ticket', $url);
    }
}
