<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FieldStaffLocation;
use App\Models\FieldVisit;
use App\Models\User;
use App\Services\Field\TechnicianNavigationService;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicianNavigationController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'heading_deg' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $location = FieldStaffLocation::query()->create([
            'tenant_id' => TenantResolver::requiredTenantId(),
            'user_id' => $user->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy_meters' => $data['accuracy_meters'] ?? null,
            'heading_deg' => $data['heading_deg'] ?? null,
            'speed_kmh' => $data['speed_kmh'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        return response()->json([
            'ok' => true,
            'location_id' => $location->id,
            'recorded_at' => $location->recorded_at?->toIso8601String(),
        ]);
    }

    public function navigate(Request $request, TechnicianNavigationService $routing): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'destination_lat' => ['required_without:visit_id', 'nullable', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required_without:visit_id', 'nullable', 'numeric', 'between:-180,180'],
            'visit_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'from_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'from_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        [$toLat, $toLng, $label] = $this->resolveDestination($data, $user);

        if ($toLat === null || $toLng === null) {
            return response()->json(['ok' => false, 'message' => 'Destination coordinates required'], 422);
        }

        [$fromLat, $fromLng] = $this->resolveOrigin($data, $user);

        if ($fromLat === null || $fromLng === null) {
            return response()->json(['ok' => false, 'message' => 'Technician location required — send GPS ping first'], 422);
        }

        $route = $routing->route($fromLat, $fromLng, $toLat, $toLng);

        return response()->json([
            ...$route,
            'destination' => [
                'lat' => $toLat,
                'lng' => $toLng,
                'label' => $label,
            ],
            'origin' => [
                'lat' => $fromLat,
                'lng' => $fromLng,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?float, 1: ?float, 2: ?string}
     */
    private function resolveDestination(array $data, User $user): array
    {
        if (! empty($data['visit_id'])) {
            $visit = FieldVisit::query()
                ->where('assigned_to', $user->id)
                ->with('ticket.customer')
                ->find((int) $data['visit_id']);

            if ($visit) {
                if ($visit->latitude !== null && $visit->longitude !== null) {
                    return [(float) $visit->latitude, (float) $visit->longitude, $visit->ticket?->subject];
                }

                $customer = $visit->ticket?->customer;
                $lat = data_get($customer?->meta, 'gps_lat');
                $lng = data_get($customer?->meta, 'gps_lng');
                if (is_numeric($lat) && is_numeric($lng)) {
                    return [(float) $lat, (float) $lng, $customer?->name];
                }
            }
        }

        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->find((int) $data['customer_id']);
            $lat = data_get($customer?->meta, 'gps_lat');
            $lng = data_get($customer?->meta, 'gps_lng');
            if (is_numeric($lat) && is_numeric($lng)) {
                return [(float) $lat, (float) $lng, $customer?->name];
            }
        }

        if (isset($data['destination_lat'], $data['destination_lng'])) {
            return [(float) $data['destination_lat'], (float) $data['destination_lng'], null];
        }

        return [null, null, null];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?float, 1: ?float}
     */
    private function resolveOrigin(array $data, User $user): array
    {
        if (isset($data['from_lat'], $data['from_lng'])) {
            return [(float) $data['from_lat'], (float) $data['from_lng']];
        }

        $latest = FieldStaffLocation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('recorded_at')
            ->first();

        if ($latest) {
            return [(float) $latest->latitude, (float) $latest->longitude];
        }

        return [null, null];
    }
}
