<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketFirstResponseBreachedMail extends Notification
{
    public function __construct(public SupportTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('filament.admin.resources.support-tickets.edit', $this->ticket);

        return (new MailMessage)
            ->subject('[First response SLA] '.$this->ticket->ticket_number)
            ->line('Ticket **'.$this->ticket->ticket_number.'** has no staff response and is past the first-response deadline.')
            ->action('Respond now', $url);
    }
}
