<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\SupportTicket;

final class SupportSlaService
{
    public function resolveProfile(?Customer $customer): string
    {
        if ($customer === null) {
            return (string) config('support.default_sla_profile', 'standard');
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];

        if (! empty($meta['tag_corporate'])) {
            return 'corporate';
        }

        return (string) config('support.default_sla_profile', 'standard');
    }

    public function applyTargets(SupportTicket $ticket, ?Customer $customer = null): void
    {
        $customer ??= $ticket->customer;
        $profile = $this->resolveProfile($customer);
        $priority = (string) ($ticket->priority ?: 'medium');

        $firstMinutes = (int) (config("support.sla_profiles.{$profile}.first_response_minutes.{$priority}")
            ?? config("support.sla_profiles.standard.first_response_minutes.{$priority}", 30));
        $resolveHours = (int) (config("support.sla_profiles.{$profile}.resolve_hours.{$priority}")
            ?? config('support.sla_resolve_hours.'.$priority, 48));

        $ticket->sla_profile = $profile;

        if ($ticket->first_response_due_at === null) {
            $ticket->first_response_due_at = now()->addMinutes(max(1, $firstMinutes));
        }

        if ($ticket->sla_resolve_due_at === null) {
            $ticket->sla_resolve_due_at = now()->addHours(max(1, $resolveHours));
        }

        if ($ticket->eta_at === null && $ticket->sla_resolve_due_at !== null) {
            $ticket->eta_at = $ticket->sla_resolve_due_at;
        }
    }

    public function markFirstResponse(SupportTicket $ticket): void
    {
        if ($ticket->first_responded_at !== null) {
            return;
        }

        $ticket->forceFill(['first_responded_at' => now()])->saveQuietly();
    }

    public function isFirstResponseBreached(SupportTicket $ticket): bool
    {
        return $ticket->isOpen()
            && $ticket->first_responded_at === null
            && $ticket->first_response_due_at !== null
            && $ticket->first_response_due_at->isPast();
    }

    public function firstResponseRemainingLabel(SupportTicket $ticket): string
    {
        if ($ticket->first_responded_at !== null) {
            return 'Responded '.$ticket->first_responded_at->diffForHumans();
        }

        if ($ticket->first_response_due_at === null || ! $ticket->isOpen()) {
            return '—';
        }

        if ($this->isFirstResponseBreached($ticket)) {
            return 'First response overdue '.$ticket->first_response_due_at->diffForHumans(now(), true);
        }

        return 'First response in '.$ticket->first_response_due_at->diffForHumans(now(), true);
    }
}
