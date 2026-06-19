<?php

namespace App\Filament\Pages;

use App\Services\Support\SupportNocWallService;
use App\Support\SupportPanelAccess;
use Filament\Pages\Page;

class SupportNocTicketWall extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static string $view = 'filament.pages.support-noc-ticket-wall';

    protected static ?string $navigationLabel = 'Ticket NOC wall';

    protected static ?string $title = 'Support NOC wall';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $snapshot = [];

    public static function canAccess(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-support-module isp-support-noc-wall',
        ];
    }

    public function mount(): void
    {
        $this->refreshSnapshot();
    }

    public function refreshSnapshot(): void
    {
        $this->snapshot = app(SupportNocWallService::class)->snapshot();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }
}
