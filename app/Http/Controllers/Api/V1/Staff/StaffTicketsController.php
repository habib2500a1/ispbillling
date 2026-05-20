<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffTicketsController extends Controller
{
    private const ACCESS_ROLES = [
        'super-admin', 'isp-admin', 'admin', 'isp-manager', 'branch-manager', 'isp-support', 'isp-engineer',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $tenantId = (int) $user->tenant_id;

        $query = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['customer:id,name,customer_code'])
            ->orderByDesc('id');

        $status = $request->query('status');
        if (is_string($status) && $status !== '' && $status !== 'all') {
            if ($status === 'active') {
                $query->whereIn('status', ['open', 'in_progress', 'pending']);
            } elseif ($status === 'complete') {
                $query->whereIn('status', ['resolved', 'closed']);
            } else {
                $query->where('status', $status);
            }
        }

        $tickets = $query->paginate(25);

        return response()->json([
            'data' => collect($tickets->items())->map(fn (SupportTicket $t) => $this->listRow($t)),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function show(Request $request, int $ticket): JsonResponse
    {
        $user = $this->staffUser($request);
        $model = $this->findTicket($user, $ticket);
        $model->load([
            'customer:id,name,customer_code,phone',
            'messages' => fn ($q) => $q->with(['user:id,name', 'customer:id,name'])->orderBy('created_at'),
        ]);

        return response()->json([
            'ticket' => $this->detailRow($model),
            'messages' => $model->messages->map(fn (SupportTicketMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'is_internal' => (bool) $m->is_internal,
                'from_staff' => $m->user_id !== null,
                'author' => $m->user?->name ?? $m->customer?->name ?? 'Customer',
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function reply(Request $request, int $ticket): JsonResponse
    {
        $user = $this->staffUser($request);
        $model = $this->findTicket($user, $ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        SupportTicketMessage::query()->create([
            'tenant_id' => $model->tenant_id,
            'support_ticket_id' => $model->id,
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => (bool) ($data['is_internal'] ?? false),
        ]);

        if (in_array($model->status, ['open', 'pending'], true)) {
            $model->update(['status' => 'in_progress']);
        }

        return response()->json(['message' => 'Reply sent.']);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'department' => ['required', Rule::in(array_keys(SupportTicket::DEPARTMENTS))],
            'priority' => ['nullable', Rule::in(array_keys(SupportTicket::PRIORITIES))],
        ]);

        $customer = Customer::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($data['customer_id'])
            ->firstOrFail();

        $ticket = SupportTicket::query()->create([
            'tenant_id' => $user->tenant_id,
            'customer_id' => $customer->id,
            'channel' => 'app',
            'department' => $data['department'],
            'priority' => $data['priority'] ?? 'medium',
            'subject' => $data['subject'],
            'description' => $data['description'],
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Ticket created.',
            'ticket' => $this->listRow($ticket->load('customer:id,name,customer_code')),
        ], 201);
    }

    public function update(Request $request, int $ticket): JsonResponse
    {
        $user = $this->staffUser($request);
        $model = $this->findTicket($user, $ticket);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(SupportTicket::PRIORITIES))],
        ]);

        $model->update(array_filter([
            'status' => $data['status'] ?? null,
            'priority' => $data['priority'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json(['ticket' => $this->detailRow($model->fresh())]);
    }

    private function staffUser(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasAnyRole(self::ACCESS_ROLES)) {
            abort(403, 'Ticket access not allowed.');
        }

        return $user;
    }

    private function findTicket(User $user, int $id): SupportTicket
    {
        return SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(SupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'subject' => $t->subject,
            'status' => $t->status,
            'priority' => $t->priority,
            'department' => $t->department,
            'customer_name' => $t->customer?->name,
            'customer_code' => $t->customer?->customer_code,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRow(SupportTicket $t): array
    {
        return [
            ...$this->listRow($t),
            'description' => $t->description,
            'customer_phone' => $t->customer?->phone,
        ];
    }
}
