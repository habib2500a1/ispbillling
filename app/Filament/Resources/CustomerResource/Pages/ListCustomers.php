<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Pages\SubscriberListsHub;
use App\Filament\Resources\CustomerResource;
use App\Filament\Widgets\SubscriberLifecycleWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    public function getHeading(): string
    {
        return 'Subscribers';
    }

    public function getSubheading(): ?string
    {
        return 'Search by name, code, or phone. Tap a row for full profile, billing, and network tools.';
    }

    /**
     * @return array<class-string<\Filament\Widgets\Widget>|\Filament\Widgets\WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            SubscriberLifecycleWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('smart_lists')
                ->label('Smart lists')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->url(SubscriberListsHub::getUrl()),
            Actions\CreateAction::make()
                ->label('New subscriber')
                ->icon('heroicon-o-user-plus'),
        ];
    }
}
