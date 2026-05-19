<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketSlaBreachedMail extends Notification
{
    public function __construct(public SupportTicket $ticket, public int $escalationLevel) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('filament.admin.resources.support-tickets.edit', $this->ticket);

        return (new MailMessage)
            ->subject('[SLA] Overdue ticket '.$this->ticket->ticket_number)
            ->line('Ticket **'.$this->ticket->ticket_number.'** is past its SLA resolve time and is still open.')
            ->line('Escalation level: '.$this->escalationLevel)
            ->action('Review ticket', $url);
    }
}
