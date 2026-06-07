<?php

namespace App\Http\Middleware;

use App\Support\LivewireSnapshotHealer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GuardLivewireUpdateRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $isLivewireMutation = $request->is('livewire/update') || $request->is('livewire/upload-file');

        if (! $isLivewireMutation) {
            return $next($request);
        }

        $sameOrigin = $this->requestMatchesAppHost($request, (string) $request->headers->get('origin', ''))
            || $this->requestMatchesAppHost($request, (string) $request->headers->get('referer', ''));

        // Livewire file uploads use signed URLs and do not send X-Livewire.
        if ($request->is('livewire/upload-file')) {
            if (! $sameOrigin) {
                abort(403);
            }
        } elseif (! $sameOrigin || ! $request->headers->has('X-Livewire')) {
            abort(403);
        }

        if ($request->is('livewire/update')) {
            $components = $request->input('components');

            // Ignore malformed/injected update calls instead of surfacing 419 popup.
            if (! is_array($components) || $components === []) {
                return response()->json(['components' => [], 'assets' => []], 200);
            }

            $first = $components[0] ?? null;
            $snapshot = is_array($first)
                ? json_decode((string) ($first['snapshot'] ?? ''), true)
                : null;

            if (! is_array($snapshot) || ! is_array($snapshot['memo'] ?? null) || empty($snapshot['memo']['name'])) {
                return response()->json(['components' => [], 'assets' => []], 200);
            }

            $componentName = (string) $snapshot['memo']['name'];
            $updates = (array) ($first['updates'] ?? []);

            if ($componentName === 'filament.livewire.global-search') {
                $sanitized = $this->sanitizeGlobalSearchUpdates($updates);

                if ($sanitized === null) {
                    Log::warning('livewire.global_search_ignored_updates', [
                        'updates_keys' => array_keys($updates),
                        'referer' => $request->headers->get('referer'),
                        'user_id' => optional(auth()->user())->getAuthIdentifier(),
                    ]);

                    return response()->json(['components' => [], 'assets' => []], 200);
                }

                if ($sanitized !== $updates) {
                    Log::info('livewire.global_search_sanitized_updates', [
                        'updates_keys' => array_keys($updates),
                        'invalid_keys' => array_values(array_diff(array_keys($updates), ['search'])),
                        'referer' => $request->headers->get('referer'),
                        'user_id' => optional(auth()->user())->getAuthIdentifier(),
                    ]);

                    $first['updates'] = $sanitized;
                    $components[0] = $first;
                    $request->merge(['components' => $components]);
                }
            }

            $healResult = LivewireSnapshotHealer::healComponents($components);

            if ($healResult['healed_release'] !== [] || $healResult['healed_snapshot'] !== []) {
                $request->merge(['components' => $healResult['components']]);

                Log::info('livewire.snapshot_healed', [
                    'release' => $healResult['healed_release'],
                    'snapshot' => $healResult['healed_snapshot'],
                    'referer' => $request->headers->get('referer'),
                    'user_id' => optional(auth()->user())->getAuthIdentifier(),
                ]);
            }
        }

        try {
            $response = $next($request);
        } catch (PublicPropertyNotFoundException $e) {
            if ($request->is('livewire/update') && str_contains($e->getMessage(), 'global-search')) {
                Log::warning('livewire.global_search_property_rejected', [
                    'message' => $e->getMessage(),
                    'referer' => $request->headers->get('referer'),
                    'user_id' => optional(auth()->user())->getAuthIdentifier(),
                ]);

                return response()->json(['components' => [], 'assets' => []], 200);
            }

            throw $e;
        } catch (MethodNotFoundException $e) {
            if ($request->is('livewire/update') && str_contains($e->getMessage(), 'updateChartData')) {
                Log::warning('livewire.stray_update_chart_data', [
                    'message' => $e->getMessage(),
                    'referer' => $request->headers->get('referer'),
                    'user_id' => optional(auth()->user())->getAuthIdentifier(),
                ]);

                return response()->json(['components' => [], 'assets' => []], 200);
            }

            throw $e;
        } catch (Throwable $e) {
            if ($request->is('livewire/update') || $request->is('livewire/upload-file')) {
                $components = (array) $request->input('components', []);
                $first = (array) ($components[0] ?? []);
                $snapshot = json_decode((string) ($first['snapshot'] ?? ''), true);

                Log::warning('livewire.update_exception', [
                    'component' => $snapshot['memo']['name'] ?? null,
                    'updates_keys' => array_keys((array) ($first['updates'] ?? [])),
                    'calls' => array_map(
                        static fn (array $call): string => (string) ($call['method'] ?? ''),
                        array_values(array_filter((array) ($first['calls'] ?? []), 'is_array'))
                    ),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'origin' => $request->headers->get('origin'),
                    'referer' => $request->headers->get('referer'),
                    'user_id' => optional(auth()->user())->getAuthIdentifier(),
                ]);

                return response()->json(['components' => [], 'assets' => []], 200);
            }

            throw $e;
        }

        if ($response->getStatusCode() === 419 && $request->is('livewire/update')) {
            $components = (array) $request->input('components', []);
            $first = (array) ($components[0] ?? []);
            $snapshot = json_decode((string) ($first['snapshot'] ?? ''), true);

            Log::warning('livewire.update_419', [
                'component' => $snapshot['memo']['name'] ?? null,
                'snapshot_release' => $snapshot['memo']['release'] ?? null,
                'updates_keys' => array_keys((array) ($first['updates'] ?? [])),
                'calls' => array_map(
                    static fn (array $call): string => (string) ($call['method'] ?? ''),
                    array_values(array_filter((array) ($first['calls'] ?? []), 'is_array'))
                ),
                'origin' => $request->headers->get('origin'),
                'referer' => $request->headers->get('referer'),
                'user_id' => optional(auth()->user())->getAuthIdentifier(),
            ]);
        }

        return $response;
    }

    private function requestMatchesAppHost(Request $request, string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parsed = parse_url($url);
        $urlHost = isset($parsed['host']) ? strtolower((string) $parsed['host']) : '';
        $appHost = strtolower($request->getHost());

        if ($urlHost === '' || $appHost === '') {
            return false;
        }

        // Compare host only — https APP_URL vs http internal scheme must not block Livewire.
        return $urlHost === $appHost;
    }

    /**
     * Stale x-persist snapshots sometimes send stray keys ($, data, "") alongside search.
     * Keep valid search updates; ignore junk-only payloads.
     *
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>|null
     */
    private function sanitizeGlobalSearchUpdates(array $updates): ?array
    {
        if (array_key_exists('search', $updates)) {
            return [
                'search' => $updates['search'],
            ];
        }

        foreach (array_keys($updates) as $key) {
            if ($key === 'data' || str_starts_with((string) $key, 'data.')) {
                return null;
            }
        }

        // Stale x-persist sometimes sends the query under a blank or "$" key instead of "search".
        if (count($updates) === 1) {
            $key = array_key_first($updates);
            $value = $updates[$key];

            if (in_array($key, ['', '$'], true) && is_scalar($value)) {
                return [
                    'search' => $value,
                ];
            }
        }

        return null;
    }
}

