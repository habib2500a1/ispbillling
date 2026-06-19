<?php

namespace App\Services\Field;

use Illuminate\Support\Facades\Http;

final class TechnicianNavigationService
{
    /**
     * @return array<string, mixed>
     */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $baseUrl = rtrim((string) config('gis.routing.osrm_base_url', 'https://router.project-osrm.org'), '/');
        $profile = (string) config('gis.routing.profile', 'driving');

        $url = sprintf(
            '%s/route/v1/%s/%s,%s;%s,%s',
            $baseUrl,
            $profile,
            $fromLng,
            $fromLat,
            $toLng,
            $toLat,
        );

        $response = Http::timeout(12)->acceptJson()->get($url, [
            'overview' => 'full',
            'geometries' => 'geojson',
            'steps' => 'true',
        ]);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Routing service unavailable',
            ];
        }

        $body = $response->json();
        $route = $body['routes'][0] ?? null;

        if (! is_array($route)) {
            return [
                'ok' => false,
                'message' => 'No route found',
            ];
        }

        $legs = collect($route['legs'] ?? [])->flatMap(fn (array $leg) => $leg['steps'] ?? [])->map(fn (array $step): array => [
            'instruction' => $step['maneuver']['instruction'] ?? ($step['name'] ?? 'Continue'),
            'distance_m' => (int) round((float) ($step['distance'] ?? 0)),
            'duration_s' => (int) round((float) ($step['duration'] ?? 0)),
            'location' => $step['maneuver']['location'] ?? null,
        ])->values()->all();

        return [
            'ok' => true,
            'distance_m' => (int) round((float) ($route['distance'] ?? 0)),
            'duration_s' => (int) round((float) ($route['duration'] ?? 0)),
            'geometry' => $route['geometry'] ?? null,
            'steps' => $legs,
            'maps_url' => sprintf(
                'https://www.google.com/maps/dir/?api=1&origin=%s,%s&destination=%s,%s&travelmode=driving',
                $fromLat,
                $fromLng,
                $toLat,
                $toLng,
            ),
        ];
    }
}
