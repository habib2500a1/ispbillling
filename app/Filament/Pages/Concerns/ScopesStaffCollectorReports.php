<?php

namespace App\Filament\Pages\Concerns;

use App\Services\Collector\CollectorStaffResolver;

trait ScopesStaffCollectorReports
{
    public function mountStaffCollectorReportScope(): void
    {
        $scoped = app(CollectorStaffResolver::class)->scopedCollectorIdForReports();
        if ($scoped !== null) {
            $this->collectorId = $scoped;
        }
    }

    public function isStaffCollectorReportScoped(): bool
    {
        return app(CollectorStaffResolver::class)->scopedCollectorIdForReports() !== null;
    }

    public function scopedCollectorDisplayName(): string
    {
        $id = app(CollectorStaffResolver::class)->scopedCollectorIdForReports();

        return $id !== null
            ? (auth()->user()?->name ?? 'You')
            : '';
    }

    public function updatedCollectorId(mixed $value): void
    {
        $this->enforceStaffCollectorReportScope();
    }

    protected function enforceStaffCollectorReportScope(): void
    {
        $scoped = app(CollectorStaffResolver::class)->scopedCollectorIdForReports();
        if ($scoped !== null) {
            $this->collectorId = $scoped;
        }
    }

    protected function effectiveReportCollectorId(): ?int
    {
        $this->enforceStaffCollectorReportScope();

        return $this->collectorId ?: null;
    }
}
