<?php

namespace App\Filament\Widgets\Concerns;

/**
 * Filament widgets default to 5s polling via CanPoll — this forces polling off.
 */
trait DisablesPolling
{
    protected function getPollingInterval(): ?string
    {
        return null;
    }
}
