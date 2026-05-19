<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalTicketController extends Controller
{
    public function index(): View
    {
        $customer = auth('customer')->user();
        $tickets = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->paginate(15);

        return view('portal.tickets.index', [
            'tickets' => $tickets,
        ]);
    }

    public function create(): View
    {
        return view('portal.tickets.create', [
            'departments' => SupportTicket::DEPARTMENTS,
            'priorities' => SupportTicket::PRIORITIES,
            'issueTypes' => SupportTicket::ISSUE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'department' => ['required', Rule::in(array_keys(SupportTicket::DEPARTMENTS))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'issue_type' => ['nullable', 'string', Rule::in(array_keys(SupportTicket::ISSUE_TYPES))],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
        ]);

        $customer = auth('customer')->user();

        $ticket = new SupportTicket([
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

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('ticket-uploads/'.$ticket->tenant_id, 'public');
            SupportTicketUpload::query()->create([
                'support_ticket_id' => $ticket->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }

        return redirect()
            ->route('portal.tickets.show', $ticket)
            ->with('status', 'Ticket submitted. Your tracking ID is '.$ticket->ticket_number.'.');
    }

    public function show(SupportTicket $ticket): View
    {
        $customer = auth('customer')->user();
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);

        $ticket->load(['publicMessagesForCustomer', 'uploads']);

        return view('portal.tickets.show', [
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $customer = auth('customer')->user();
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return back()->withErrors(['body' => 'This ticket is closed; you cannot add replies.']);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'customer_id' => $customer->id,
            'body' => $data['body'],
            'is_internal' => false,
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return back()->with('status', 'Your reply was sent.');
    }

    public function rate(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $customer = auth('customer')->user();
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);

        if (! in_array($ticket->status, ['resolved', 'closed'], true)) {
            return back()->withErrors(['rating' => 'You can rate only after the ticket is resolved.']);
        }

        $data = $request->validate([
            'customer_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'customer_rating_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticket->update([
            'customer_rating' => $data['customer_rating'],
            'customer_rating_comment' => $data['customer_rating_comment'] ?? null,
        ]);

        return back()->with('status', 'Thank you for your feedback.');
    }
}
