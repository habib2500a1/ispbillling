<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\Portal\CustomerOnuOpticalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnuController extends Controller
{
    public function status(Request $request, CustomerOnuOpticalService $onu): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json(['onu' => $onu->snapshot($customer)]);
    }

    public function reboot(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $snapshot = app(CustomerOnuOpticalService::class)->snapshot($customer);

        if (! ($snapshot['linked'] ?? false)) {
            return response()->json(['message' => 'No ONU linked to your account.'], 422);
        }

        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'channel' => 'app',
            'department' => 'technical_support',
            'priority' => 'medium',
            'issue_type' => 'equipment',
            'subject' => 'ONU reboot requested (mobile app)',
            'description' => 'Customer requested remote ONU reboot from mobile app. Device: '.($snapshot['label'] ?? $snapshot['serial'] ?? 'ONU'),
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Reboot request sent to NOC team. Ticket #'.$ticket->ticket_number,
            'ticket_id' => $ticket->id,
        ], 202);
    }
}
