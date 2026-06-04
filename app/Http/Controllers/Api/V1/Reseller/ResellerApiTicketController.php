<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerPortalActivityLogger;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResellerApiTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customerIds = $request->user()->customers()->pluck('id');

        $rows = SupportTicket::query()
            ->whereIn('customer_id', $customerIds)
            ->with(['customer:id,name,customer_code'])
            ->latest('id')
            ->paginate(min(30, (int) $request->query('per_page', 15)));

        return response()->json($rows);
    }

    public function store(Request $request, ResellerCustomerService $customers): JsonResponse
    {
        $reseller = $request->user();
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::TICKET_CREATE)) {
            throw ValidationException::withMessages(['permission' => 'Ticket creation is not allowed.']);
        }

        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'department' => ['required', Rule::in(array_keys(SupportTicket::DEPARTMENTS))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'issue_type' => ['nullable', 'string', Rule::in(array_keys(SupportTicket::ISSUE_TYPES))],
        ]);

        $customer = Customer::query()->findOrFail((int) $validated['customer_id']);
        $customers->assertOwned($reseller, $customer);

        $ticket = new SupportTicket([
            'tenant_id' => $reseller->tenant_id,
            'customer_id' => $customer->id,
            'channel' => 'portal',
            'department' => $validated['department'],
            'priority' => $validated['priority'],
            'issue_type' => $validated['issue_type'] ?? null,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status' => 'open',
        ]);
        $ticket->save();

        app(ResellerPortalActivityLogger::class)->log($reseller, 'ticket.create', $ticket);

        return response()->json(['ticket' => $ticket], 201);
    }

    public function show(Request $request, SupportTicket $ticket, ResellerCustomerService $customers): JsonResponse
    {
        $customers->assertOwned($request->user(), $ticket->customer);

        return response()->json($ticket->load(['customer:id,name,customer_code', 'publicMessagesForCustomer']));
    }

    public function reply(Request $request, SupportTicket $ticket, ResellerCustomerService $customers): JsonResponse
    {
        $reseller = $request->user();
        $customers->assertOwned($reseller, $ticket->customer);

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return response()->json(['message' => 'Ticket is closed.'], 422);
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'body' => '[Reseller API] '.$data['body'],
            'is_internal' => false,
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        app(ResellerPortalActivityLogger::class)->log($reseller, 'ticket.reply', $ticket);

        return response()->json(['message' => 'Reply sent.']);
    }
}
