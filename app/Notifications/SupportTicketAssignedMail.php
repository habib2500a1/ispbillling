<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketAssignedMail extends Notification
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
            ->subject('Ticket '.$this->ticket->ticket_number.' assigned to you')
            ->line('You have been assigned support ticket **'.$this->ticket->ticket_number.'**.')
            ->line('Subject: '.$this->ticket->subject)
            ->action('Open ticket', $url);
    }
}
