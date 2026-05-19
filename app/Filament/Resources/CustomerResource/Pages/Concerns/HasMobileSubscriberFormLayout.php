<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

trait HasMobileSubscriberFormLayout
{
    /**
     * @return array<string, string>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-subscriber-record-page',
        ];
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }
}
