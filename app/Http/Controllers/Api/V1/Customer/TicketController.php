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

        $ticket->load(['publicMessagesForCustomer']);

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

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'customer_id' => $customer->id,
            'body' => $data['body'],
            'is_internal' => false,
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return response()->json(['message' => 'Reply sent.']);
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
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }
}
