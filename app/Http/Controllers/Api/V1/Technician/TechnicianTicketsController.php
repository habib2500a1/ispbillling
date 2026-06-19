<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Support\SupportTicketAttachmentService;
use App\Services\Support\SupportTicketWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TechnicianTicketsController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tickets = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->where(function ($q): void {
                $q->whereDate('eta_at', today())
                    ->orWhereDate('created_at', today())
                    ->orWhereNull('eta_at');
            })
            ->with(['customer:id,name,customer_code,phone,address'])
            ->orderBy('eta_at')
            ->orderByDesc('priority')
            ->limit(50)
            ->get();

        $visits = FieldVisit::query()
            ->with(['ticket.customer:id,name,phone,address'])
            ->where('assigned_to', $user->id)
            ->whereDate('scheduled_at', today())
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'tickets' => $tickets->map(fn (SupportTicket $t): array => $this->ticketRow($t)),
            'field_visits' => $visits->map(fn (FieldVisit $v): array => [
                'id' => $v->id,
                'status' => $v->status,
                'scheduled_at' => $v->scheduled_at?->toIso8601String(),
                'ticket_number' => $v->ticket?->ticket_number,
                'customer_name' => $v->ticket?->customer?->name,
                'customer_phone' => $v->ticket?->customer?->phone,
                'address' => $v->ticket?->customer?->address,
            ]),
        ]);
    }

    public function accept(Request $request, int $ticket): JsonResponse
    {
        $user = $request->user();
        $model = $this->findAssignedTicket($user, $ticket);

        $updates = ['status' => 'in_progress'];
        if ($model->assigned_to === null) {
            $updates['assigned_to'] = $user->id;
        }

        $model->update($updates);

        FieldVisit::query()->firstOrCreate(
            [
                'support_ticket_id' => $model->id,
                'assigned_to' => $user->id,
            ],
            [
                'tenant_id' => $model->tenant_id,
                'status' => 'in_progress',
                'scheduled_at' => now(),
            ],
        );

        SupportTicketMessage::query()->create([
            'tenant_id' => $model->tenant_id,
            'support_ticket_id' => $model->id,
            'user_id' => $user->id,
            'body' => 'Technician '.$user->name.' accepted the job.',
            'is_internal' => true,
        ]);

        return response()->json([
            'message' => 'Job accepted.',
            'ticket' => $this->ticketRow($model->fresh()->load('customer')),
        ]);
    }

    public function close(Request $request, int $ticket): JsonResponse
    {
        $user = $request->user();
        $model = $this->findAssignedTicket($user, $ticket);

        $data = $request->validate([
            'report' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        if (filled($data['report'] ?? null)) {
            SupportTicketMessage::query()->create([
                'tenant_id' => $model->tenant_id,
                'support_ticket_id' => $model->id,
                'user_id' => $user->id,
                'body' => (string) $data['report'],
                'is_internal' => false,
            ]);
        }

        if ($request->hasFile('photo')) {
            $message = SupportTicketMessage::query()->create([
                'tenant_id' => $model->tenant_id,
                'support_ticket_id' => $model->id,
                'user_id' => $user->id,
                'body' => 'Field closure photo uploaded.',
                'is_internal' => false,
            ]);
            app(SupportTicketAttachmentService::class)->attachUploadsToMessage($message, [$request->file('photo')]);
        }

        $model->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        FieldVisit::query()
            ->where('support_ticket_id', $model->id)
            ->where('assigned_to', $user->id)
            ->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json([
            'message' => 'Ticket resolved.',
            'ticket' => $this->ticketRow($model->fresh()),
        ]);
    }

    public function addNote(Request $request, int $ticket): JsonResponse
    {
        $user = $request->user();
        $model = $this->findAssignedTicket($user, $ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $message = SupportTicketMessage::query()->create([
            'tenant_id' => $model->tenant_id,
            'support_ticket_id' => $model->id,
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => (bool) ($data['is_internal'] ?? true),
        ]);

        return response()->json([
            'message' => 'Note saved.',
            'note_id' => $message->id,
        ]);
    }

    public function uploadPhoto(Request $request, int $ticket): JsonResponse
    {
        $user = $request->user();
        $model = $this->findAssignedTicket($user, $ticket);

        $request->validate([
            'photo' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,mp4,mov,webm'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $message = SupportTicketMessage::query()->create([
            'tenant_id' => $model->tenant_id,
            'support_ticket_id' => $model->id,
            'user_id' => $user->id,
            'body' => $request->input('caption') ?: 'Photo uploaded from field app.',
            'is_internal' => false,
        ]);

        app(SupportTicketAttachmentService::class)->attachUploadsToMessage($message, [$request->file('photo')]);

        return response()->json(['message' => 'Photo uploaded.', 'message_id' => $message->id]);
    }

    public function onuPanel(Request $request, int $customer, SupportTicketWorkspaceService $workspace): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('assigned_to', $user->id)
            ->where('customer_id', $customer)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest('id')
            ->first();

        if ($ticket === null) {
            abort(404, 'No active assigned ticket for this customer.');
        }

        $ticket->load('customer');
        $panel = $workspace->customer360($ticket, $ticket->customer);

        return response()->json([
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'customer' => $panel,
        ]);
    }

    private function findAssignedTicket(User $user, int $ticketId): SupportTicket
    {
        $model = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($ticketId)
            ->firstOrFail();

        if ((int) $model->assigned_to !== (int) $user->id) {
            abort(403, 'Ticket is not assigned to you.');
        }

        if (in_array($model->status, ['resolved', 'closed'], true)) {
            abort(422, 'Ticket is already closed.');
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketRow(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'eta_at' => $ticket->eta_at?->toIso8601String(),
            'customer' => $ticket->customer ? [
                'id' => $ticket->customer->id,
                'name' => $ticket->customer->name,
                'code' => $ticket->customer->customer_code,
                'phone' => $ticket->customer->phone,
                'address' => $ticket->customer->address,
            ] : null,
        ];
    }
}
