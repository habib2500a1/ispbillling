<?php

namespace App\Filament\Pages;
use App\Filament\Pages\Concerns\HidesHubNavigation;

use App\Models\DeviceToken;
use App\Models\PushNotificationLog;
use Filament\Pages\Page;

class MobileAppsHub extends Page
{
    use HidesHubNavigation;
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string $view = 'filament.pages.mobile-apps-hub';

    protected static ?string $navigationLabel = 'Mobile apps';

    protected static ?string $title = 'Mobile app features';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'customer_devices' => DeviceToken::query()->where('app', 'customer')->count(),
            'technician_devices' => DeviceToken::query()->where('app', 'technician')->count(),
            'pushes_sent' => PushNotificationLog::query()->where('status', 'sent')->count(),
            'fcm_enabled' => (bool) config('mobile.fcm_enabled'),
            'bkash_enabled' => (bool) config('bkash.enabled'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }
}
