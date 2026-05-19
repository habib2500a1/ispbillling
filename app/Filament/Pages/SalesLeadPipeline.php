<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SalesLeadResource;
use App\Services\Sales\SalesLeadKanbanService;
use Filament\Pages\Page;

class SalesLeadPipeline extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static string $view = 'filament.pages.sales-lead-pipeline';

    protected static ?string $navigationLabel = 'Connection pipeline';

    protected static ?string $title = 'New connection pipeline';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 5;

    public ?int $filterAssignee = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }

    /**
     * @return array<string, array{label: string, color: string, leads: \Illuminate\Support\Collection}>
     */
    public function getColumnsProperty(): array
    {
        return app(SalesLeadKanbanService::class)->board($this->filterAssignee);
    }

    /**
     * @return array<int, string>
     */
    public function getStaffOptionsProperty(): array
    {
        return \App\Models\User::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function moveLead(int $leadId, string $status): void
    {
        app(SalesLeadKanbanService::class)->move($leadId, $status);

        \Filament\Notifications\Notification::make()
            ->title('Lead moved')
            ->success()
            ->send();
    }

}
