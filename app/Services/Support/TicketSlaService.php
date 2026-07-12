<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use Carbon\Carbon;

/**
 * Lightweight SLA checks using existing support_tickets columns only.
 */
final class TicketSlaService
{
    public function resolveHours(string $priority): int
    {
        $priority = strtolower($priority ?: 'medium');

        return max(1, (int) config("support.sla_resolve_hours.{$priority}", 24));
    }

    public function firstResponseMinutes(string $priority): int
    {
        $priority = strtolower($priority ?: 'medium');

        return max(1, (int) config("support.sla_first_response_minutes.{$priority}", 120));
    }

    public function resolveDueAt(SupportTicket $ticket): Carbon
    {
        return Carbon::parse($ticket->created_at)->addHours($this->resolveHours((string) $ticket->priority));
    }

    public function firstResponseDueAt(SupportTicket $ticket): Carbon
    {
        return Carbon::parse($ticket->created_at)->addMinutes($this->firstResponseMinutes((string) $ticket->priority));
    }

    public function isOpen(SupportTicket $ticket): bool
    {
        return in_array($ticket->status, ['open', 'in_progress'], true);
    }

    public function isFirstResponseBreached(SupportTicket $ticket): bool
    {
        if (! $this->isOpen($ticket)) {
            return false;
        }

        if ($ticket->replied_at !== null) {
            return false;
        }

        return $this->firstResponseDueAt($ticket)->isPast();
    }

    public function isResolveBreached(SupportTicket $ticket): bool
    {
        if (! $this->isOpen($ticket)) {
            return false;
        }

        return $this->resolveDueAt($ticket)->isPast();
    }

    public function statusLabel(SupportTicket $ticket): string
    {
        if (! $this->isOpen($ticket)) {
            return 'ok';
        }

        if ($this->isResolveBreached($ticket)) {
            return 'resolve_breached';
        }

        if ($this->isFirstResponseBreached($ticket)) {
            return 'first_response_breached';
        }

        return 'within_sla';
    }

    /**
     * @return array{open: int, in_progress: int, breached: int, high_open: int}
     */
    public function summaryCounts(): array
    {
        $open = SupportTicket::where('status', 'open')->count();
        $inProgress = SupportTicket::where('status', 'in_progress')->count();
        $highOpen = SupportTicket::whereIn('status', ['open', 'in_progress'])
            ->where('priority', 'high')
            ->count();

        $breached = SupportTicket::whereIn('status', ['open', 'in_progress'])
            ->get()
            ->filter(fn (SupportTicket $t) => $this->isResolveBreached($t) || $this->isFirstResponseBreached($t))
            ->count();

        return [
            'open' => $open,
            'in_progress' => $inProgress,
            'breached' => $breached,
            'high_open' => $highOpen,
        ];
    }
}
