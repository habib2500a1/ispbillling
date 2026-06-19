<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Models\SupportTicket;
use App\Models\User;
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
