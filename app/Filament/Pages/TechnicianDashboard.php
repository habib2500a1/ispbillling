<?php

namespace App\Filament\Pages;

use App\Models\SupportTicket;
use App\Support\TenantResolver;
use Filament\Pages\Page;

class TechnicianDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench';

    protected static string $view = 'filament.pages.technician-dashboard';

    protected static ?string $title = 'Technician dashboard';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $userId = auth()->id();
        $tenantId = TenantResolver::requiredTenantId();
        $base = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('assigned_to', $userId);

        return [
            'assigned_open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'due_today' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])
                ->whereDate('sla_resolve_due_at', today())->count(),
            'resolved_month' => SupportTicket::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('assigned_to', $userId)
                ->where('status', 'resolved')
                ->whereMonth('resolved_at', now()->month)
                ->count(),
            'tickets' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])
                ->latest()->limit(15)->get(['id', 'ticket_number', 'subject', 'priority', 'status', 'sla_resolve_due_at']),
        ];
    }
}
