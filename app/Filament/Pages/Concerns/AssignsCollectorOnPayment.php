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
            $selfId = (int) auth()->id();
            if ($selfId > 0 && array_key_exists($selfId, $options)) {
                $this->collectorUserId = $selfId;
            } elseif ($options !== []) {
                $this->collectorUserId = (int) array_key_first($options);
            } else {
                $this->collectorUserId = null;
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
        $this->ensureCollectorSelected();

        $requested = $this->collectorUserId !== null && $this->collectorUserId !== ''
            ? (int) $this->collectorUserId
            : null;

        return app(CollectorStaffResolver::class)->requireSelfCollectorId($requested);
    }

    protected function ensureCollectorSelected(): void
    {
        if ($this->collectorUserId !== null && (int) $this->collectorUserId > 0) {
            return;
        }

        $resolver = app(CollectorStaffResolver::class);

        if ($resolver->canPickCollector()) {
            $options = $resolver->collectableStaffOptions();
            if ($options === []) {
                $this->collectorUserId = $resolver->defaultCollectorId() ?: null;

                return;
            }

            $selfId = (int) auth()->id();
            if ($selfId > 0 && array_key_exists($selfId, $options)) {
                $this->collectorUserId = $selfId;

                return;
            }

            $this->collectorUserId = (int) array_key_first($options);

            return;
        }

        $this->collectorUserId = $resolver->defaultCollectorId() ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectorPaymentMeta(int $collectorId): array
    {
        return app(CollectorStaffResolver::class)->paymentMetaForCollector($collectorId, (int) auth()->id());
    }
}
