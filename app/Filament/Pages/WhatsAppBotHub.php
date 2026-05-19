<?php

namespace App\Filament\Pages;
use App\Filament\Pages\Concerns\HidesHubNavigation;

use Filament\Pages\Page;

class WhatsAppBotHub extends Page
{
    use HidesHubNavigation;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string $view = 'filament.pages.whatsapp-bot-hub';

    protected static ?string $navigationLabel = 'WhatsApp bot';

    protected static ?string $title = 'WhatsApp two-way bot';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }

    public function getWebhookUrl(): string
    {
        return url('/api/webhooks/whatsapp');
    }

    public function isBotEnabled(): bool
    {
        return (bool) config('whatsapp_bot.enabled', false);
    }

    public function isWhatsAppConfigured(): bool
    {
        return (bool) config('notifications.whatsapp.enabled', false);
    }
}
