<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Pages\InventoryHub;

/**
 * Premium inventory create/edit chrome (UI only).
 */
trait UsesInventoryFormLayout
{
    public string $inventoryFormTitle = '';

    public string $inventoryFormSubtitle = '';

    public string $inventoryFormBackUrl = '';

    public string $inventoryFormBackLabel = 'Back';

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-inventory-module',
        ];
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function configureInventoryFormShell(
        string $title,
        string $subtitle,
        string $backUrl,
        string $backLabel = 'Asset intelligence',
    ): void {
        $this->inventoryFormTitle = $title;
        $this->inventoryFormSubtitle = $subtitle;
        $this->inventoryFormBackUrl = $backUrl;
        $this->inventoryFormBackLabel = $backLabel;
    }

    protected function inventoryHubAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('inventory_hub')
            ->label('Asset center')
            ->icon('heroicon-o-cube')
            ->color('gray')
            ->url(InventoryHub::getUrl());
    }
}
