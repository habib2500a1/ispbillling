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

    public function resolveHours(?Customer $customer, string $priority): int
    {
        $profile = $this->resolveProfile($customer);

        return (int) (config("support.sla_profiles.{$profile}.resolve_hours.{$priority}")
            ?? config('support.sla_resolve_hours.'.$priority, 48));
    }

    public function firstResponseMinutes(?Customer $customer, string $priority): int
    {
        $profile = $this->resolveProfile($customer);

        return (int) (config("support.sla_profiles.{$profile}.first_response_minutes.{$priority}")
            ?? config("support.sla_profiles.standard.first_response_minutes.{$priority}", 30));
    }

    public function previewResolveDueAt(?Customer $customer, string $priority): \Illuminate\Support\Carbon
    {
        return now()->addHours(max(1, $this->resolveHours($customer, $priority)));
    }

    public function previewLabel(?Customer $customer, string $priority): string
    {
        $profile = $this->resolveProfile($customer);
        $hours = $this->resolveHours($customer, $priority);
        $due = $this->previewResolveDueAt($customer, $priority);
        $label = SupportTicket::PRIORITIES[$priority] ?? $priority;
        $profileNote = $profile !== 'standard' ? ' · '.ucfirst($profile).' SLA' : '';

        return $due->format('M j, Y · g:i A').' ('.$hours.'h · '.$label.' priority'.$profileNote.')';
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

    public function escalateManually(SupportTicket $ticket, ?int $targetLevel, \App\Models\User $actor): int
    {
        $maxLevel = 3;
        $current = (int) $ticket->escalation_level;
        $next = $targetLevel ?? min($maxLevel, $current + 1);
        $next = max(1, min($maxLevel, $next));

        if ($next <= $current && $current < $maxLevel) {
            $next = $current + 1;
        }

        $updates = [
            'escalation_level' => $next,
            'escalated_at' => now(),
        ];

        if ($next >= 2 && in_array($ticket->priority, ['low', 'medium'], true)) {
            $updates['priority'] = $next >= 3 ? 'critical' : 'high';
        }

        $ticket->forceFill($updates)->save();

        $ladder = collect((array) config('support.escalation_ladder', []))
            ->firstWhere('level', $next);
        $label = is_array($ladder) ? ($ladder['label'] ?? 'Level '.$next) : 'Level '.$next;

        \App\Models\SupportTicketMessage::query()->create([
            'tenant_id' => $ticket->tenant_id,
            'support_ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'body' => 'Manual escalation to '.$label.' (level '.$next.') by '.$actor->name.'.',
            'is_internal' => true,
        ]);

        app(SupportTicketNotifier::class)->notifySlaEscalation($ticket->fresh(), $next);

        return $next;
    }

    /**
     * @return list<array{level: int, label: string}>
     */
    public function escalationOptions(SupportTicket $ticket): array
    {
        $current = (int) $ticket->escalation_level;
        $ladder = (array) config('support.escalation_ladder', []);
        $options = [];

        foreach ($ladder as $step) {
            $level = (int) ($step['level'] ?? 0);
            if ($level <= $current || $level === 0) {
                continue;
            }
            $options[] = [
                'level' => $level,
                'label' => (string) ($step['label'] ?? 'Level '.$level),
            ];
        }

        return $options;
    }
}
