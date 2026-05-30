<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerPortalActivityLogger;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResellerTicketController extends Controller
{
    public function index(): View
    {
        $reseller = auth('reseller')->user();
        $customerIds = $reseller->customers()->pluck('id');

        $tickets = SupportTicket::query()
            ->whereIn('customer_id', $customerIds)
            ->with(['customer:id,name,customer_code'])
            ->latest('id')
            ->paginate(15);

        return view('reseller.tickets.index', [
            'reseller' => $reseller,
            'tickets' => $tickets,
        ]);
    }

    public function create(): View
    {
        $reseller = auth('reseller')->user();
        $customers = $reseller->customers()->orderBy('name')->get(['id', 'name', 'customer_code']);

        return view('reseller.tickets.create', [
            'reseller' => $reseller,
            'customers' => $customers,
            'departments' => SupportTicket::DEPARTMENTS,
            'priorities' => SupportTicket::PRIORITIES,
            'issueTypes' => SupportTicket::ISSUE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $reseller = auth('reseller')->user();
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
        app(ResellerCustomerService::class)->assertOwned($reseller, $customer);

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

        return redirect()
            ->route('reseller.tickets.show', $ticket)
            ->with('status', 'Ticket submitted: '.$ticket->ticket_number);
    }

    public function show(SupportTicket $ticket, ResellerCustomerService $customers): View
    {
        $reseller = auth('reseller')->user();
        $ticket->load(['customer', 'publicMessagesForCustomer']);
        $customers->assertOwned($reseller, $ticket->customer);

        return view('reseller.tickets.show', [
            'reseller' => $reseller,
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, ResellerCustomerService $customers): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $ticket->customer);

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return back()->withErrors(['body' => 'This ticket is closed.']);
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'body' => '[Reseller portal] '.$data['body'],
            'is_internal' => false,
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        app(ResellerPortalActivityLogger::class)->log($reseller, 'ticket.reply', $ticket);

        return back()->with('status', 'Reply sent.');
    }
}
