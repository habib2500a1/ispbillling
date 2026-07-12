<?php

namespace App\Services\CallCenter;

use App\Models\BillingInfo;
use App\Models\CallDeskLog;
use App\Models\CustomersInfo;
use App\Models\SupportTicket;
use App\Services\Support\TicketSlaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Call Desk (Call Center lite) — queues + customer context + call logs.
 * Uses existing tickets/billing; optional call_desk_logs for outcomes.
 */
final class CallDeskService
{
    public function __construct(
        private readonly TicketSlaService $sla,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(int $limit = 25): array
    {
        $today = Carbon::today();

        $openTickets = SupportTicket::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (SupportTicket $t) => $this->ticketRow($t));

        $callQueue = $this->billingCallQueue($limit);

        $recentCalls = CallDeskLog::query()
            ->with(['customer:id,customer_unique_id,customer_name,mobile', 'staff:id,name'])
            ->orderByDesc('called_at')
            ->limit(15)
            ->get()
            ->map(fn (CallDeskLog $log) => $this->callRow($log));

        $callbacks = CallDeskLog::query()
            ->with('customer:id,customer_unique_id,customer_name,mobile')
            ->where('outcome', 'callback')
            ->where('called_at', '>=', now()->subDays(7))
            ->orderByDesc('called_at')
            ->limit(15)
            ->get()
            ->map(fn (CallDeskLog $log) => $this->callRow($log));

        return [
            'updated_at' => now()->toIso8601String(),
            'stats' => [
                'calls_today' => CallDeskLog::query()->whereDate('called_at', $today)->count(),
                'callbacks' => CallDeskLog::query()
                    ->where('outcome', 'callback')
                    ->where('called_at', '>=', now()->subDays(7))
                    ->count(),
                'no_answer_today' => CallDeskLog::query()
                    ->whereDate('called_at', $today)
                    ->whereIn('outcome', ['no_answer', 'busy'])
                    ->count(),
                'open_tickets' => SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count(),
                'sla_breached' => $this->sla->summaryCounts()['breached'] ?? 0,
                'due_to_call' => $this->billingCallQueueCount(),
            ],
            'open_tickets' => $openTickets,
            'call_queue' => $callQueue,
            'recent_calls' => $recentCalls,
            'callbacks' => $callbacks,
            'outcomes' => CallDeskLog::OUTCOMES,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchCustomers(string $q, int $limit = 12): array
    {
        $q = trim($q);
        if ($q === '' || Str::length($q) < 2) {
            return [];
        }

        return CustomersInfo::query()
            ->with(['billing', 'pppUser:id,username'])
            ->whereNull('deleted_at')
            ->where(function ($query) use ($q) {
                $query->where('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_unique_id', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('alternative_mobile', 'like', "%{$q}%")
                    ->orWhereHas('pppUser', fn ($pq) => $pq->where('username', 'like', "%{$q}%"));
            })
            ->orderBy('customer_name')
            ->limit($limit)
            ->get()
            ->map(fn (CustomersInfo $c) => $this->customerCard($c))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function customerContext(string $customerUniqueId): ?array
    {
        $customer = CustomersInfo::query()
            ->with(['billing', 'pppUser:id,username'])
            ->where('customer_unique_id', $customerUniqueId)
            ->whereNull('deleted_at')
            ->first();

        if (! $customer) {
            return null;
        }

        $tickets = SupportTicket::query()
            ->where('customer_unique_id', $customerUniqueId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (SupportTicket $t) => $this->ticketRow($t));

        $calls = CallDeskLog::query()
            ->where('customer_unique_id', $customerUniqueId)
            ->orderByDesc('called_at')
            ->limit(8)
            ->get()
            ->map(fn (CallDeskLog $log) => $this->callRow($log));

        return [
            'customer' => $this->customerCard($customer),
            'tickets' => $tickets,
            'calls' => $calls,
        ];
    }

    /**
     * @param  array{phone?: string, direction?: string, outcome?: string, duration_seconds?: int, remarks?: string, create_ticket?: bool}  $data
     */
    public function logCall(string $customerUniqueId, array $data): CallDeskLog
    {
        $customer = CustomersInfo::query()
            ->with('pppUser:id,username')
            ->where('customer_unique_id', $customerUniqueId)
            ->firstOrFail();

        $outcome = (string) ($data['outcome'] ?? 'answered');
        if (! array_key_exists($outcome, CallDeskLog::OUTCOMES)) {
            $outcome = 'answered';
        }

        $ticketId = null;
        if (! empty($data['create_ticket'])) {
            $ticket = SupportTicket::query()->create([
                'ticket_no' => 'CD-'.now()->format('ymdHis').'-'.Str::upper(Str::random(3)),
                'customer_unique_id' => $customer->customer_unique_id,
                'ppp_username' => $customer->pppUser?->username,
                'subject' => 'Call desk: '.CallDeskLog::OUTCOMES[$outcome],
                'description' => trim((string) ($data['remarks'] ?? 'Logged from Call Desk')),
                'priority' => $outcome === 'callback' ? 'medium' : 'low',
                'status' => 'open',
                'category' => 'call',
            ]);
            $ticketId = $ticket->id;
        }

        return CallDeskLog::query()->create([
            'customer_unique_id' => $customer->customer_unique_id,
            'phone' => $data['phone'] ?? $customer->mobile,
            'staff_user_id' => Auth::id(),
            'direction' => in_array(($data['direction'] ?? 'outbound'), ['inbound', 'outbound'], true)
                ? $data['direction']
                : 'outbound',
            'outcome' => $outcome,
            'duration_seconds' => max(0, (int) ($data['duration_seconds'] ?? 0)),
            'remarks' => $data['remarks'] ?? null,
            'support_ticket_id' => $ticketId,
            'called_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function billingCallQueue(int $limit): array
    {
        return $this->billingCallQueueQuery()
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'customer_unique_id' => $row->customer_unique_id,
                'customer_name' => $row->customer_name,
                'mobile' => $row->mobile,
                'status' => $row->customer_status,
                'due_amount' => round((float) $row->due_amount, 2),
                'auto_disable_date' => $row->auto_disable_date
                    ? Carbon::parse($row->auto_disable_date)->toDateString()
                    : null,
            ])
            ->all();
    }

    private function billingCallQueueCount(): int
    {
        return (int) $this->billingCallQueueQuery()->count();
    }

    private function billingCallQueueQuery()
    {
        $today = Carbon::today();

        return BillingInfo::query()
            ->join('customers_infos', 'customers_infos.customer_unique_id', '=', 'billing_infos.customer_bill_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->whereNotIn('customers_infos.status', ['deleted'])
            ->where(function ($q) use ($today) {
                $q->where('billing_infos.due_amount', '>', 0)
                    ->orWhere(function ($q2) use ($today) {
                        $q2->whereNotNull('billing_infos.auto_disable_date')
                            ->whereDate('billing_infos.auto_disable_date', '<=', $today->copy()->addDays(3));
                    });
            })
            ->select([
                'customers_infos.customer_unique_id',
                'customers_infos.customer_name',
                'customers_infos.mobile',
                'customers_infos.status as customer_status',
                'billing_infos.due_amount',
                'billing_infos.auto_disable_date',
            ])
            ->orderByDesc('billing_infos.due_amount')
            ->orderBy('billing_infos.auto_disable_date');
    }

    /**
     * @return array<string, mixed>
     */
    private function customerCard(CustomersInfo $c): array
    {
        $billing = $c->billing;

        return [
            'customer_unique_id' => $c->customer_unique_id,
            'customer_name' => $c->customer_name,
            'mobile' => $c->mobile,
            'alternative_mobile' => $c->alternative_mobile,
            'status' => $c->status,
            'ppp_username' => $c->pppUser?->username,
            'due_amount' => round((float) ($billing?->due_amount ?? 0), 2),
            'previous_due' => round((float) ($billing?->previous_due ?? 0), 2),
            'monthly_rent' => round((float) ($billing?->monthly_rent ?? 0), 2),
            'auto_disable_date' => $billing?->auto_disable_date
                ? Carbon::parse($billing->auto_disable_date)->toDateString()
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketRow(SupportTicket $t): array
    {
        $label = $this->sla->statusLabel($t);

        return [
            'id' => $t->id,
            'ticket_no' => $t->ticket_no,
            'subject' => $t->subject,
            'priority' => $t->priority,
            'status' => $t->status,
            'category' => $t->category,
            'customer_unique_id' => $t->customer_unique_id,
            'ppp_username' => $t->ppp_username,
            'sla' => $label,
            'sla_badge' => match ($label) {
                'resolve_breached', 'first_response_breached' => 'danger',
                'within_sla' => 'success',
                default => 'secondary',
            },
            'created_at' => optional($t->created_at)?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function callRow(CallDeskLog $log): array
    {
        return [
            'id' => $log->id,
            'customer_unique_id' => $log->customer_unique_id,
            'customer_name' => $log->customer?->customer_name ?? $log->customer_unique_id,
            'phone' => $log->phone,
            'direction' => $log->direction,
            'outcome' => $log->outcome,
            'outcome_label' => $log->outcome_label,
            'duration_seconds' => $log->duration_seconds,
            'remarks' => $log->remarks,
            'staff' => $log->staff?->name,
            'support_ticket_id' => $log->support_ticket_id,
            'called_at' => optional($log->called_at)?->format('Y-m-d H:i'),
            'called_human' => optional($log->called_at)?->diffForHumans(),
        ];
    }
}
