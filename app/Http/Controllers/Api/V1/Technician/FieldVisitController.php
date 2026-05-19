<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Models\User;
use App\Services\Mobile\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FieldVisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = FieldVisit::query()
            ->with(['ticket.customer'])
            ->where('assigned_to', $user->id)
            ->orderByDesc('scheduled_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('today_only')) {
            $query->whereDate('scheduled_at', today());
        }

        $visits = $query->paginate(30);

        return response()->json([
            'data' => collect($visits->items())->map(fn (FieldVisit $v) => $this->payload($v)),
            'meta' => [
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'total' => $visits->total(),
            ],
        ]);
    }

    public function show(Request $request, FieldVisit $fieldVisit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((int) $fieldVisit->assigned_to === (int) $user->id, 404);

        $fieldVisit->load(['ticket.customer']);

        return response()->json(['visit' => $this->payload($fieldVisit)]);
    }

    public function update(Request $request, FieldVisit $fieldVisit, PushNotificationService $push): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((int) $fieldVisit->assigned_to === (int) $user->id, 404);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(FieldVisit::STATUSES))],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'location_text' => ['nullable', 'string', 'max:500'],
            'report' => ['nullable', 'string', 'max:10000'],
        ]);

        if (isset($data['status'])) {
            if ($data['status'] === 'in_progress' && $fieldVisit->started_at === null) {
                $fieldVisit->started_at = now();
            }
            if ($data['status'] === 'completed') {
                $fieldVisit->completed_at = now();
            }
        }

        $fieldVisit->fill($data)->save();
        $fieldVisit->load(['ticket.customer']);

        $customer = $fieldVisit->ticket?->customer;
        if ($customer && isset($data['status']) && $data['status'] === 'completed') {
            $push->sendTo(
                $customer,
                'customer',
                'Technician visit completed',
                'Your field visit #'.$fieldVisit->id.' has been marked complete.',
                ['type' => 'field_visit', 'visit_id' => $fieldVisit->id],
            );
        }

        return response()->json([
            'visit' => $this->payload($fieldVisit),
            'message' => 'Visit updated.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FieldVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'status' => $visit->status,
            'scheduled_at' => $visit->scheduled_at?->toIso8601String(),
            'started_at' => $visit->started_at?->toIso8601String(),
            'completed_at' => $visit->completed_at?->toIso8601String(),
            'latitude' => $visit->latitude,
            'longitude' => $visit->longitude,
            'location_text' => $visit->location_text,
            'report' => $visit->report,
            'ticket' => $visit->ticket ? [
                'id' => $visit->ticket->id,
                'ticket_number' => $visit->ticket->ticket_number,
                'subject' => $visit->ticket->subject,
                'customer' => $visit->ticket->customer ? [
                    'name' => $visit->ticket->customer->name,
                    'phone' => $visit->ticket->customer->phone,
                    'address' => $visit->ticket->customer->address,
                ] : null,
            ] : null,
        ];
    }
}
