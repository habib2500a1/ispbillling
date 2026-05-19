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
        $resolver = app(CollectorStaffResolver::class);

        if (! $resolver->canPickCollector()) {
            return $resolver->defaultCollectorId();
        }

        $collectorId = (int) ($this->collectorUserId ?? 0);
        $options = $resolver->collectableStaffOptions();

        if ($collectorId < 1 || ! array_key_exists($collectorId, $options)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'collectorUserId' => 'Select which staff member receives credit for this collection.',
            ]);
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
