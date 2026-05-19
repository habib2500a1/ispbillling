<?php

namespace App\Services\Support;

use App\Models\SupportAssignmentRule;
use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketAutoAssignment
{
    public function assignIfUnassigned(SupportTicket $ticket): void
    {
        if ($ticket->assigned_to !== null) {
            return;
        }

        $customer = $ticket->customer;
        if ($customer === null) {
            return;
        }

        $rules = SupportAssignmentRule::query()
            ->where('tenant_id', $ticket->tenant_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if (! User::withoutGlobalScopes()->whereKey($rule->user_id)->exists()) {
                continue;
            }
            if ($rule->area_id !== null && (int) $customer->area_id !== (int) $rule->area_id) {
                continue;
            }
            if ($rule->department !== null && $rule->department !== $ticket->department) {
                continue;
            }

            $ticket->forceFill(['assigned_to' => $rule->user_id])->saveQuietly();

            return;
        }
    }
}
