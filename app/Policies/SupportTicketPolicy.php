<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SupportPanelAccess;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return SupportPanelAccess::viewTickets($user);
    }

    public function view(User $user, SupportTicket $supportTicket): bool
    {
        return SupportPanelAccess::viewTickets($user);
    }

    public function create(User $user): bool
    {
        return SupportPanelAccess::manageTickets($user);
    }

    public function update(User $user, SupportTicket $supportTicket): bool
    {
        return SupportPanelAccess::manageTickets($user);
    }

    public function delete(User $user, SupportTicket $supportTicket): bool
    {
        return SupportPanelAccess::manageTickets($user);
    }
}
