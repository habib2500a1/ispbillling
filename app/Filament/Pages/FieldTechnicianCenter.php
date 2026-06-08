<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Models\FieldVisit;
use App\Models\SupportTicket;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class FieldTechnicianCenter extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.field-technician-center';

    protected static ?string $navigationLabel = 'Field technicians';

    protected static ?string $title = 'Field technician center';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $slug = 'field-technicians';

    protected static bool $shouldRegisterNavigation = false;

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'isp-os-module'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAssignedVisits(): array
    {
        return FieldVisit::query()
            ->with(['supportTicket:id,subject,priority,status,customer_id', 'supportTicket.customer:id,name,customer_code,phone'])
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('scheduled_at')
            ->limit(25)
            ->get()
            ->map(fn (FieldVisit $v): array => [
                'id' => $v->id,
                'status' => $v->status,
                'scheduled' => $v->scheduled_at?->format('M j, H:i') ?? '—',
                'customer' => $v->supportTicket?->customer?->name ?? '—',
                'code' => $v->supportTicket?->customer?->customer_code ?? '—',
                'ticket' => '#'.($v->support_ticket_id ?? '—'),
                'subject' => $v->supportTicket?->subject ?? '—',
                'priority' => $v->supportTicket?->priority ?? 'normal',
                'url' => $v->support_ticket_id
                    ? \App\Filament\Resources\SupportTicketResource::getUrl('edit', ['record' => $v->support_ticket_id])
                    : SupportHub::getUrl(),
            ])
            ->all();
    }

    /**
     * @return array{open: int, urgent: int, visits_today: int}
     */
    public function getFieldStats(): array
    {
        return [
            'open' => SupportTicket::query()->whereIn('status', ['open', 'in_progress', 'waiting'])->count(),
            'urgent' => SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->where('priority', 'urgent')->count(),
            'visits_today' => FieldVisit::query()->whereDate('scheduled_at', today())->count(),
        ];
    }

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canSupport()
            || StaffCapability::for(auth()->user())->canNetwork();
    }
}
