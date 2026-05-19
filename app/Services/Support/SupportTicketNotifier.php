<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Mobile\PushNotificationService;
use App\Notifications\SupportTicketAssignedMail;
use App\Notifications\SupportTicketCustomerOpenedMail;
use App\Notifications\SupportTicketNewStaffMail;
use App\Notifications\SupportTicketPublicReplyCustomerMail;
use App\Notifications\SupportTicketResolvedCustomerMail;
use App\Notifications\SupportTicketSlaBreachedMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class SupportTicketNotifier
{
    public function notifyCustomerTicketOpened(SupportTicket $ticket): void
    {
        $email = $ticket->customer?->email;
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Notification::route('mail', $email)->notify(new SupportTicketCustomerOpenedMail($ticket));
    }

    public function notifyStaffNewTicket(SupportTicket $ticket): void
    {
        $users = $this->staffForTenant($ticket->tenant_id, 'support.manage');
        Notification::send($users, new SupportTicketNewStaffMail($ticket));
    }

    public function notifyAssignee(SupportTicket $ticket): void
    {
        $user = $ticket->assignee;
        if ($user === null || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $user->notify(new SupportTicketAssignedMail($ticket));
    }

    public function notifyCustomerPublicReply(SupportTicket $ticket, string $body): void
    {
        $email = $ticket->customer?->email;
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Notification::route('mail', $email)->notify(new SupportTicketPublicReplyCustomerMail($ticket, $body));

        $customer = $ticket->customer;
        if ($customer !== null) {
            app(PushNotificationService::class)->sendTo(
                $customer,
                'customer',
                'Support reply: '.$ticket->ticket_number,
                \Illuminate\Support\Str::limit($body, 120),
                ['type' => 'ticket_reply', 'ticket_id' => $ticket->id],
            );
        }
    }

    public function notifyCustomerResolved(SupportTicket $ticket): void
    {
        $email = $ticket->customer?->email;
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Notification::route('mail', $email)->notify(new SupportTicketResolvedCustomerMail($ticket));
    }

    public function notifySlaEscalation(SupportTicket $ticket, int $level): void
    {
        $recipients = $this->slaRecipients($ticket);
        if ($ticket->assigned_to !== null) {
            $assignee = User::withoutGlobalScopes()->find($ticket->assigned_to);
            if ($assignee !== null) {
                $recipients = $recipients->push($assignee);
            }
        }

        Notification::send($recipients->unique('id'), new SupportTicketSlaBreachedMail($ticket, $level));
    }

    /**
     * @return Collection<int, User>
     */
    protected function staffForTenant(int $tenantId, string $permission): Collection
    {
        return User::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId): void {
                $q->where('users.tenant_id', $tenantId)->orWhereNull('users.tenant_id');
            })
            ->where(function ($q) use ($permission): void {
                $q->whereHas('permissions', fn ($p) => $p->where('name', $permission)->where('guard_name', 'web'))
                    ->orWhereHas('roles', fn ($rq) => $rq->whereHas('permissions', fn ($p) => $p->where('name', $permission)->where('guard_name', 'web')));
            })
            ->get()
            ->unique('id')
            ->filter(fn (User $u): bool => (bool) filter_var($u->email, FILTER_VALIDATE_EMAIL));
    }

    /**
     * @return Collection<int, User>
     */
    protected function slaRecipients(SupportTicket $ticket): Collection
    {
        $tenantScoped = User::withoutGlobalScopes()
            ->where(function ($q) use ($ticket): void {
                $q->where('users.tenant_id', $ticket->tenant_id)->orWhereNull('users.tenant_id');
            });

        $byRole = (clone $tenantScoped)
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['isp-manager', 'isp-admin', 'super-admin']))
            ->get();

        $byPerm = (clone $tenantScoped)
            ->where(function ($q): void {
                $q->whereHas('permissions', fn ($p) => $p->where('name', 'support.manage')->where('guard_name', 'web'))
                    ->orWhereHas('roles', fn ($rq) => $rq->whereHas('permissions', fn ($p) => $p->where('name', 'support.manage')->where('guard_name', 'web')));
            })
            ->get();

        return $byRole
            ->merge($byPerm)
            ->unique('id')
            ->filter(fn (User $u): bool => (bool) filter_var($u->email, FILTER_VALIDATE_EMAIL));
    }
}
