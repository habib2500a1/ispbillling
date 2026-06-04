<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\Integrations\WebhookAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketWebhookController extends Controller
{
    public function store(Request $request, WebhookAuthenticator $webhooks): JsonResponse
    {
        $tenantId = (int) ($request->input('tenant_id') ?? 0);

        if ($webhooks->missingSecretInProduction('support.webhook_secret', $tenantId > 0 ? $tenantId : null)) {
            abort(503, 'Webhook secret not configured');
        }

        if (! $webhooks->authorize($request, 'support.webhook_secret', $tenantId > 0 ? $tenantId : null)) {
            abort(403);
        }

        $data = $request->validate([
            'customer_code' => ['required', 'string', 'max:64'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'channel' => ['nullable', Rule::in(array_keys(SupportTicket::CHANNELS))],
            'department' => ['required', Rule::in(array_keys(SupportTicket::DEPARTMENTS))],
            'priority' => ['nullable', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'issue_type' => ['nullable', 'string', 'max:120'],
        ]);

        $customer = Customer::withoutGlobalScopes()
            ->where('customer_code', $data['customer_code'])
            ->firstOrFail();

        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'channel' => $data['channel'] ?? 'whatsapp',
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
