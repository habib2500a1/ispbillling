<?php

namespace App\Filament\Widgets\Concerns;

trait EnablesLivePolling
{
    protected function getPollingInterval(): ?string
    {
        $seconds = (int) config('bandwidth.live_page_poll_seconds', 60);

        return $seconds > 0 ? "{$seconds}s" : null;
    }
}
