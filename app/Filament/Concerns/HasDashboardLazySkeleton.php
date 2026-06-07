<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\View\View;

/**
 * Custom skeleton placeholder for lazy-loaded dashboard widgets.
 */
trait HasDashboardLazySkeleton
{
    public function placeholder(): View
    {
        return view('filament.partials.dashboard-widget-skeleton', [
            'variant' => $this->dashboardSkeletonVariant(),
            'height' => $this->dashboardSkeletonHeight(),
            ...$this->getPlaceholderData(),
        ]);
    }

    protected function dashboardSkeletonVariant(): string
    {
        return 'default';
    }

    protected function dashboardSkeletonHeight(): ?string
    {
        return '12rem';
    }
}
