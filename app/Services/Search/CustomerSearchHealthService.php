<?php

namespace App\Services\Search;

use App\Support\CustomerSearchSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class CustomerSearchHealthService
{
    /**
     * @return array{
     *   healthy: bool,
     *   message: string,
     *   host: string,
     *   indexed_documents: ?int,
     *   index_exists: bool,
     *   latency_ms: ?int
     * }
     */
    public function status(): array
    {
        if (! CustomerSearchSettings::enabled()) {
            return [
                'healthy' => false,
                'message' => 'Customer search disabled in dashboard.',
                'host' => CustomerSearchSettings::host(),
                'indexed_documents' => null,
                'index_exists' => false,
                'latency_ms' => null,
            ];
        }

        $host = CustomerSearchSettings::host();
        $key = CustomerSearchSettings::masterKey();
        $started = microtime(true);

        try {
            $health = Http::timeout(3)
                ->withToken($key)
                ->get(rtrim($host, '/').'/health');

            if (! $health->successful()) {
                return [
                    'healthy' => false,
                    'message' => 'Meilisearch not reachable (HTTP '.$health->status().'). Using SQL fallback.',
                    'host' => $host,
                    'indexed_documents' => null,
                    'index_exists' => false,
                    'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                ];
            }

            $indexName = (string) config('scout.prefix', '').'customers';
            $stats = Http::timeout(3)
                ->withToken($key)
                ->get(rtrim($host, '/').'/indexes/'.$indexName.'/stats');

            $docs = null;
            $indexExists = $stats->successful();
            if ($indexExists) {
                $docs = (int) ($stats->json('numberOfDocuments') ?? 0);
            }

            return [
                'healthy' => true,
                'message' => $indexExists
                    ? "Meilisearch OK · {$docs} subscriber(s) indexed"
                    : 'Meilisearch OK · index not built yet — click Re-index',
                'host' => $host,
                'indexed_documents' => $docs,
                'index_exists' => $indexExists,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } catch (\Throwable $e) {
            Log::channel('single')->info('customer_search.health_failed', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return [
                'healthy' => false,
                'message' => 'Meilisearch offline — SQL fallback active.',
                'host' => $host,
                'indexed_documents' => null,
                'index_exists' => false,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
    }

    public function isHealthy(): bool
    {
        return $this->status()['healthy'];
    }
}
