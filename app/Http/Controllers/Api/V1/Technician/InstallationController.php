<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InstallationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'location_text' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'image', 'max:8192'],
        ]);

        $ticket = SupportTicket::query()->create([
            'tenant_id' => $user->tenant_id,
            'customer_id' => (int) $data['customer_id'],
            'channel' => 'app',
            'department' => 'field_engineer',
            'priority' => 'medium',
            'issue_type' => 'installation',
            'subject' => $data['subject'],
            'description' => $data['description'],
            'status' => 'open',
        ]);

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store("mobile/installations/{$user->tenant_id}", 'public');
            $photoUrl = Storage::disk('public')->url($path);
        }

        $visit = FieldVisit::query()->create([
            'tenant_id' => $user->tenant_id,
            'support_ticket_id' => $ticket->id,
            'assigned_to' => $user->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_text' => $data['location_text'] ?? null,
            'report' => $photoUrl ? "Photo: {$photoUrl}" : null,
        ]);

        return response()->json([
            'message' => 'Installation job created.',
            'ticket_id' => $ticket->id,
            'visit_id' => $visit->id,
            'photo_url' => $photoUrl,
        ], 201);
    }
}
