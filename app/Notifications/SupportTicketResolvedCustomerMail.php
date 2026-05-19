<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketResolvedCustomerMail extends Notification
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
            ->subject('Ticket '.$this->ticket->ticket_number.' marked resolved')
            ->line('Your support ticket has been marked **resolved**.')
            ->line('You can rate our support from the portal.')
            ->action('Open ticket', $url);
    }
}
