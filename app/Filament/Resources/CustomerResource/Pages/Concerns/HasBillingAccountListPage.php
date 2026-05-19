<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

trait HasBillingAccountListPage
{
    public function getHeading(): string
    {
        return static::getNavigationLabel() ?? parent::getHeading();
    }

    public function getSubheading(): ?string
    {
        return 'Tap a row for profile, billing, SMS, and network tools. Use filters on the table for more detail.';
    }
}
