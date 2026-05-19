<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PortalLiveChatController extends Controller
{
    public function index(): View
    {
        $customer = auth('customer')->user();
        $open = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->where('channel', 'live_chat')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderByDesc('id')
            ->first();

        return view('portal.live-chat', [
            'openTicket' => $open,
        ]);
    }

    public function start(): RedirectResponse
    {
        $customer = auth('customer')->user();

        $existing = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->where('channel', 'live_chat')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            return redirect()->route('portal.tickets.show', $existing);
        }

        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'channel' => 'live_chat',
            'department' => 'technical_support',
            'priority' => 'medium',
            'subject' => 'Live chat',
            'description' => 'Customer started a live chat session from the portal.',
            'status' => 'open',
        ]);

        return redirect()->route('portal.tickets.show', $ticket)
            ->with('status', 'Chat session opened. You can send messages below.');
    }
}
