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

        $origin = (string) $request->headers->get('origin', '');
        $referer = (string) $request->headers->get('referer', '');
        $host = $request->getSchemeAndHttpHost();

        $sameOrigin = ($origin !== '' && str_starts_with($origin, $host))
            || ($referer !== '' && str_starts_with($referer, $host));

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

            if (
                $componentName === 'filament.livewire.global-search'
                && (array_key_exists('data', $updates) || $this->updatesTouchData($updates))
            ) {
                Log::warning('livewire.global_search_stray_form_update', [
                    'updates_keys' => array_keys($updates),
                    'referer' => $request->headers->get('referer'),
                    'user_id' => optional(auth()->user())->getAuthIdentifier(),
                ]);

                return response()->json(['components' => [], 'assets' => []], 200);
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
            if ($request->is('livewire/update')) {
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

    /**
     * @param  array<string, mixed>  $updates
     */
    private function updatesTouchData(array $updates): bool
    {
        foreach (array_keys($updates) as $key) {
            if ($key === 'data' || str_starts_with((string) $key, 'data.')) {
                return true;
            }
        }

        return false;
    }
}

