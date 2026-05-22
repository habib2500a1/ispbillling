<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Support\StaffTenantScope;
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
        $tenantId = StaffTenantScope::tenantIdFor($user);

        $query = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['customer:id,name,customer_code', 'assignee:id,name'])
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

        if ($request->boolean('mine')) {
            $query->where('assigned_to', $user->id);
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('assigned_to');
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
            'assignee:id,name',
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

        $customer = StaffTenantScope::customerForStaff($user, (int) $data['customer_id']);

        $ticket = SupportTicket::query()->create([
            'tenant_id' => $customer->tenant_id,
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
            'assigned_to' => ['nullable', 'integer'],
        ]);

        $updates = [];
        if (isset($data['status'])) {
            $updates['status'] = $data['status'];
        }
        if (isset($data['priority'])) {
            $updates['priority'] = $data['priority'];
        }
        if (array_key_exists('assigned_to', $data)) {
            if ($data['assigned_to'] !== null) {
                $assignee = User::query()
                    ->whereKey((int) $data['assigned_to'])
                    ->where('is_active', true)
                    ->first();
                if ($assignee === null || (int) $assignee->tenant_id !== (int) $model->tenant_id) {
                    abort(422, 'Invalid assignee for this tenant.');
                }
            }
            $updates['assigned_to'] = $data['assigned_to'];
        }

        if ($updates !== []) {
            $model->update($updates);
        }

        return response()->json(['ticket' => $this->detailRow($model->fresh()->load(['customer:id,name,customer_code,phone', 'assignee:id,name']))]);
    }

    public function assignees(Request $request): JsonResponse
    {
        $user = $this->staffUser($request);
        $tenantId = StaffTenantScope::tenantIdFor($user);

        $staff = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ACCESS_ROLES))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $staff->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->values(),
        ]);
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
            ->where('tenant_id', StaffTenantScope::tenantIdFor($user))
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
            'assigned_to' => $t->assigned_to,
            'assignee_name' => $t->assignee?->name,
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
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
