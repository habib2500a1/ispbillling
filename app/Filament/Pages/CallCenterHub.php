<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Resources\CallFollowUpResource;
use App\Filament\Resources\CallLogResource;
use App\Filament\Resources\VoiceSmsCampaignResource;
use App\Filament\Resources\VoiceTemplateResource;
use App\Models\CallFollowUp;
use App\Models\CallLog;
use App\Support\SupportPanelAccess;
use Filament\Pages\Page;

class CallCenterHub extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-phone';

    protected static string $view = 'filament.pages.call-center-hub';

    protected static ?string $navigationLabel = 'Call center';

    protected static ?string $title = 'Call center';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'call-center-hub';

    public static function canAccess(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        $today = now()->startOfDay();

        return [
            'calls_today' => CallLog::query()->where('started_at', '>=', $today)->count(),
            'pending_followups' => CallFollowUp::query()
                ->where('status', CallFollowUp::STATUS_PENDING)
                ->where('scheduled_at', '<=', now()->addDay())
                ->count(),
            'missed_today' => CallLog::query()
                ->where('started_at', '>=', $today)
                ->whereIn('status', ['missed', 'no_answer', 'busy'])
                ->count(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, url: string, icon: string}>
     */
    public function getModules(): array
    {
        return [
            [
                'label' => 'Call logs',
                'description' => 'Inbound / outbound history',
                'url' => CallLogResource::getUrl('index'),
                'icon' => 'heroicon-o-phone-arrow-up-right',
            ],
            [
                'label' => 'Follow-ups',
                'description' => 'Scheduled callbacks',
                'url' => CallFollowUpResource::getUrl('index'),
                'icon' => 'heroicon-o-calendar-days',
            ],
            [
                'label' => 'SIP / WebSIP settings',
                'description' => 'WSS URI, extensions',
                'url' => ManageCallCenterSettings::getUrl(),
                'icon' => 'heroicon-o-cog-6-tooth',
            ],
            [
                'label' => 'Voice templates',
                'description' => 'Announcements & IVR scripts',
                'url' => VoiceTemplateResource::getUrl('index'),
                'icon' => 'heroicon-o-speaker-wave',
            ],
            [
                'label' => 'Voice SMS campaigns',
                'description' => 'Scheduled voice blasts',
                'url' => VoiceSmsCampaignResource::getUrl('index'),
                'icon' => 'heroicon-o-megaphone',
            ],
            [
                'label' => 'Call reports',
                'description' => 'Staff performance summary',
                'url' => CallCenterReports::getUrl(),
                'icon' => 'heroicon-o-chart-bar-square',
            ],
        ];
    }
}
