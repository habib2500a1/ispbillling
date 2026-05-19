<?php

namespace App\Filament\Pages;
use App\Filament\Pages\Concerns\HidesHubNavigation;

use App\Filament\Resources\CustomerResource;
use Filament\Pages\Page;

class SubscriberListsHub extends Page
{
    use HidesHubNavigation;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static string $view = 'filament.pages.subscriber-lists-hub';

    protected static ?string $navigationLabel = 'Subscriber lists';

    protected static ?string $title = 'Subscriber lists';

    protected static ?string $navigationGroup = 'Subscribers';

    protected static ?int $navigationSort = 5;

    /**
     * @return list<array{label: string, description: string, url: string, color: string, icon: string}>
     */
    public function getLists(): array
    {
        return [
            [
                'label' => 'All active',
                'description' => 'Standard billing subscribers',
                'url' => CustomerResource::getUrl('index'),
                'color' => 'teal',
                'icon' => 'heroicon-o-users',
            ],
            [
                'label' => 'Free clients',
                'description' => 'No invoice generation',
                'url' => CustomerResource::getUrl('free'),
                'color' => 'sky',
                'icon' => 'heroicon-o-gift',
            ],
            [
                'label' => 'VIP clients',
                'description' => 'Priority & exemptions',
                'url' => CustomerResource::getUrl('vip'),
                'color' => 'amber',
                'icon' => 'heroicon-o-star',
            ],
            [
                'label' => 'Expired',
                'description' => 'Service period ended',
                'url' => CustomerResource::getUrl('expired'),
                'color' => 'orange',
                'icon' => 'heroicon-o-clock',
            ],
            [
                'label' => 'Suspended',
                'description' => 'Line off · overdue',
                'url' => CustomerResource::getUrl('suspended'),
                'color' => 'rose',
                'icon' => 'heroicon-o-pause-circle',
            ],
            [
                'label' => 'Left subscribers',
                'description' => 'Terminated / archived',
                'url' => CustomerResource::getUrl('left'),
                'color' => 'slate',
                'icon' => 'heroicon-o-archive-box',
            ],
        ];
    }

    public static function canAccess(): bool
    {
        return CustomerResource::canViewAny();
    }
}
