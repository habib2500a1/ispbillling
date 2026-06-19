<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $tickets = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => collect($tickets->items())->map(fn (SupportTicket $t) => $this->ticketPayload($t)),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'department' => ['required', Rule::in(array_keys(SupportTicket::DEPARTMENTS))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
        ]);

        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'channel' => 'app',
            'department' => $validated['department'],
            'priority' => $validated['priority'],
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status' => 'open',
        ]);

        return response()->json([
            'ticket' => $this->ticketPayload($ticket),
            'message' => 'Ticket submitted.',
        ], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);

        $ticket->load(['publicMessagesForCustomer', 'assignee:id,name']);

        return response()->json([
            'ticket' => $this->ticketPayload($ticket),
            'messages' => $ticket->publicMessagesForCustomer->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'from_customer' => $m->customer_id !== null,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
        ]);

        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'customer_id' => $customer->id,
            'body' => $data['body'],
            'is_internal' => false,
        ]);

        if ($request->hasFile('attachments')) {
            app(\App\Services\Support\SupportTicketAttachmentService::class)->attachUploadsToMessage(
                $message,
                $request->file('attachments'),
            );
        }

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return response()->json(['message' => 'Reply sent.']);
    }

    public function rate(Request $request, SupportTicket $ticket): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);

        if (! in_array($ticket->status, ['resolved', 'closed'], true)) {
            return response()->json(['message' => 'Ticket must be resolved before rating.'], 422);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket->update([
            'customer_rating' => $data['rating'],
            'customer_rating_comment' => $data['comment'] ?? null,
        ]);

        return response()->json(['message' => 'Thank you for your feedback.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'department' => $ticket->department,
            'assignee_name' => $ticket->assignee?->name,
            'eta_at' => $ticket->eta_at?->toIso8601String(),
            'sla_resolve_due_at' => $ticket->sla_resolve_due_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }
}
