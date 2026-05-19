<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketNewStaffMail extends Notification
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
            ->subject('New ticket '.$this->ticket->ticket_number)
            ->line('A new support ticket was opened.')
            ->line('**ID:** '.$this->ticket->ticket_number)
            ->line('**Customer:** '.$this->ticket->customer?->name)
            ->line('**Subject:** '.$this->ticket->subject)
            ->action('Open in admin', $url);
    }
}
