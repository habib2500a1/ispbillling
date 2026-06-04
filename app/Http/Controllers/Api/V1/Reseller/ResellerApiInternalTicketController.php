<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerInternalTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiInternalTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $tickets = $reseller->internalTickets()
            ->latest()
            ->limit(50)
            ->get(['id', 'subject', 'body', 'status', 'priority', 'category', 'created_at']);

        return response()->json(['tickets' => $tickets]);
    }

    public function store(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:48'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        $ticket = ResellerInternalTicket::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'category' => $validated['category'] ?? 'general',
            'priority' => $validated['priority'] ?? 'normal',
            'status' => ResellerInternalTicket::STATUS_OPEN,
        ]);

        return response()->json(['ticket' => $ticket, 'message' => 'Support ticket submitted.'], 201);
    }
}
