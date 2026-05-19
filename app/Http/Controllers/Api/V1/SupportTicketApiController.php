<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SupportTicket::class);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'channel' => ['nullable', Rule::in(array_keys(SupportTicket::CHANNELS))],
            'department' => ['required', Rule::in(array_keys(SupportTicket::DEPARTMENTS))],
            'priority' => ['nullable', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'issue_type' => ['nullable', 'string', 'max:120'],
        ]);

        $customer = Customer::query()->whereKey($data['customer_id'])->firstOrFail();

        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'channel' => $data['channel'] ?? 'app',
            'department' => $data['department'],
            'priority' => $data['priority'] ?? 'medium',
            'issue_type' => $data['issue_type'] ?? null,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'status' => 'open',
        ]);

        return response()->json([
            'ticket_number' => $ticket->ticket_number,
            'id' => $ticket->id,
        ], 201);
    }
}
