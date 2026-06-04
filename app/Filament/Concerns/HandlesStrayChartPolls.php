<?php

namespace App\Filament\Concerns;

/**
 * Nested SubscriberLiveTrafficWidget wire:poll can hit the parent page after wire:navigate.
 */
trait HandlesStrayChartPolls
{
    public function updateChartData(): void
    {
        //
    }
}
