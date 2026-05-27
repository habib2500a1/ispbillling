<?php

namespace App\Filament\Pages\Concerns;

/**
 * Hub pages call getStats() from blade and card builders — cache once per Livewire request.
 */
trait CachesHubStats
{
    /** @var array<string, mixed>|null */
    protected ?array $hubStatsCache = null;

    /**
     * @param  callable(): array<string, mixed>  $loader
     * @return array<string, mixed>
     */
    protected function cachedHubStats(callable $loader): array
    {
        if ($this->hubStatsCache === null) {
            $this->hubStatsCache = $loader();
        }

        return $this->hubStatsCache;
    }
}
