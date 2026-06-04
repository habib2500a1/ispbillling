<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerInternalTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerInternalTicketController extends Controller
{
    public function index(): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        return view('reseller.enterprise.internal-tickets', [
            'reseller' => $reseller,
            'tickets' => $reseller->internalTickets()->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:48'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        ResellerInternalTicket::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'category' => $validated['category'] ?? 'general',
            'priority' => $validated['priority'] ?? 'normal',
            'status' => ResellerInternalTicket::STATUS_OPEN,
        ]);

        return back()->with('status', 'Support ticket submitted.');
    }
}
