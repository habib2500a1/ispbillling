<?php

namespace App\Filament\Pages\Concerns;

use App\Services\Collector\CollectorStaffResolver;

trait AssignsCollectorOnPayment
{
    public ?int $collectorUserId = null;

    public function mountCollectorAssignment(): void
    {
        $resolver = app(CollectorStaffResolver::class);

        if ($resolver->canPickCollector()) {
            $options = $resolver->collectableStaffOptions();
            $this->collectorUserId = null;
            foreach (array_keys($options) as $id) {
                if ((int) $id !== (int) auth()->id()) {
                    $this->collectorUserId = (int) $id;
                    break;
                }
            }
            if ($this->collectorUserId === null && $options !== []) {
                $this->collectorUserId = (int) array_key_first($options);
            }
        } else {
            $this->collectorUserId = $resolver->defaultCollectorId();
        }
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
        $requested = $this->collectorUserId !== null && $this->collectorUserId !== ''
            ? (int) $this->collectorUserId
            : null;

        return app(CollectorStaffResolver::class)->requireSelfCollectorId($requested);
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectorPaymentMeta(int $collectorId): array
    {
        return app(CollectorStaffResolver::class)->paymentMetaForCollector($collectorId, (int) auth()->id());
    }
}
