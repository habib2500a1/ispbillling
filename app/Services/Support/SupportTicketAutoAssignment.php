<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\PopBox;
use App\Models\SupportAssignmentRule;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Collection;

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

        $this->hydrateRoutingFields($ticket, $customer);

        $userId = $this->pickAssignee($ticket, $customer);
        if ($userId === null) {
            return;
        }

        $ticket->forceFill([
            'assigned_to' => $userId,
            'status' => $ticket->status === 'open' ? 'assigned' : $ticket->status,
        ])->saveQuietly();
    }

    private function hydrateRoutingFields(SupportTicket $ticket, Customer $customer): void
    {
        $dirty = [];

        if ($ticket->pop_box_id === null && $customer->area_id !== null) {
            $popId = PopBox::query()
                ->where('tenant_id', $ticket->tenant_id)
                ->where('area_id', $customer->area_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
            if ($popId !== null) {
                $dirty['pop_box_id'] = (int) $popId;
            }
        }

        if ($ticket->olt_device_id === null) {
            $customer->loadMissing(['onuDevice']);
            $onu = $customer->primaryOnu();
            if ($onu?->olt_id !== null) {
                $dirty['olt_device_id'] = (int) $onu->olt_id;
            }
        }

        if ($dirty !== []) {
            $ticket->forceFill($dirty)->saveQuietly();
        }
    }

    private function pickAssignee(SupportTicket $ticket, Customer $customer): ?int
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $isVip = ! empty($meta['tag_vip']) || ! empty($meta['tag_corporate']);

        $rules = SupportAssignmentRule::query()
            ->where('tenant_id', $ticket->tenant_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $candidates = $this->matchingRuleUserIds($rules, $ticket, $customer, $isVip);
        if ($candidates->isEmpty()) {
            return $this->loadBalancedFallback($ticket->tenant_id, $isVip);
        }

        return $this->leastLoadedUser($candidates->all(), $ticket->tenant_id);
    }

    /**
     * @return Collection<int, int>
     */
    private function matchingRuleUserIds(Collection $rules, SupportTicket $ticket, Customer $customer, bool $isVip): Collection
    {
        $ids = collect();

        foreach ($rules as $rule) {
            if (! User::withoutGlobalScopes()->whereKey($rule->user_id)->exists()) {
                continue;
            }
            if ($rule->area_id !== null && (int) $customer->area_id !== (int) $rule->area_id) {
                continue;
            }
            if ($rule->pop_box_id !== null && (int) $ticket->pop_box_id !== (int) $rule->pop_box_id) {
                continue;
            }
            if ($rule->department !== null && $rule->department !== $ticket->department) {
                continue;
            }
            if ($rule->vip_priority && ! $isVip) {
                continue;
            }
            if (filled($rule->skill_tag) && ! $this->skillMatches((string) $rule->skill_tag, $ticket)) {
                continue;
            }
            if ($rule->max_open_tickets !== null && $this->openTicketCount((int) $rule->user_id) >= (int) $rule->max_open_tickets) {
                continue;
            }

            $ids->push((int) $rule->user_id);
        }

        return $ids->unique()->values();
    }

    private function skillMatches(string $skill, SupportTicket $ticket): bool
    {
        $issue = (string) ($ticket->issue_type ?? '');
        $dept = (string) ($ticket->department ?? '');

        return $issue === $skill
            || $dept === $skill
            || str_contains($issue, $skill)
            || str_contains($dept, $skill);
    }

    private function loadBalancedFallback(int $tenantId, bool $vipPreferred): ?int
    {
        $query = User::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId): void {
                $q->where('users.tenant_id', $tenantId)->orWhereNull('users.tenant_id');
            })
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['isp-engineer', 'isp-support', 'isp-manager', 'isp-admin', 'super-admin']));

        $users = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($users === []) {
            return null;
        }

        return $this->leastLoadedUser($users, $tenantId);
    }

    /**
     * @param  list<int>  $userIds
     */
    private function leastLoadedUser(array $userIds, int $tenantId): ?int
    {
        if ($userIds === []) {
            return null;
        }

        $loads = SupportTicket::withoutGlobalScopes()
            ->selectRaw('assigned_to, COUNT(*) as open_count')
            ->where('tenant_id', $tenantId)
            ->whereIn('assigned_to', $userIds)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->groupBy('assigned_to')
            ->pluck('open_count', 'assigned_to');

        $bestId = null;
        $bestLoad = PHP_INT_MAX;

        foreach ($userIds as $userId) {
            $load = (int) ($loads[$userId] ?? 0);
            if ($load < $bestLoad) {
                $bestLoad = $load;
                $bestId = $userId;
            }
        }

        return $bestId;
    }

    private function openTicketCount(int $userId): int
    {
        return SupportTicket::withoutGlobalScopes()
            ->where('assigned_to', $userId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();
    }
}
