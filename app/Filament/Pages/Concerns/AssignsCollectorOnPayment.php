<?php

namespace App\Filament\Pages\Concerns;

use App\Services\Collector\CollectorStaffResolver;

trait AssignsCollectorOnPayment
{
    public ?int $collectorUserId = null;

    public function mountCollectorAssignment(): void
    {
        $this->collectorUserId = app(CollectorStaffResolver::class)->defaultCollectorId();
    }

    public function canPickCollector(): bool
    {
        return app(CollectorStaffResolver::class)->canPickCollector();
    }

    /**
     * @return array<int, string>
     */
    public function getCollectorStaffOptions(): array
    {
        return app(CollectorStaffResolver::class)->collectableStaffOptions();
    }

    protected function resolveCollectorIdForPayment(): int
    {
        $resolver = app(CollectorStaffResolver::class);
        $collectorId = (int) ($this->collectorUserId ?? 0);

        if ($collectorId < 1) {
            $collectorId = $resolver->defaultCollectorId();
        }

        if (! $resolver->canPickCollector()) {
            return $resolver->defaultCollectorId();
        }

        $options = $resolver->collectableStaffOptions();
        if (! array_key_exists($collectorId, $options)) {
            return $resolver->defaultCollectorId();
        }

        return $collectorId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectorPaymentMeta(int $collectorId): array
    {
        return app(CollectorStaffResolver::class)->paymentMetaForCollector($collectorId, (int) auth()->id());
    }
}
