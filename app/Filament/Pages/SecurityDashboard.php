<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRoleDashboard;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\TenantResolver;
use Filament\Pages\Page;

class SecurityDashboard extends Page
{
    use HasRoleDashboard;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.security-dashboard';

    protected static ?string $title = 'Security dashboard';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return static::staff()->isTenantAdmin() || static::staff()->can('audit.view');
    }

    /**
     * @return array<string, int|list<array<string, mixed>>>
     */
    public function getSecurityStats(): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $base = ActivityLog::withoutGlobalScopes()->where('tenant_id', $tenantId);

        return [
            'logins_today' => (clone $base)->where('event', 'login')->whereDate('created_at', today())->count(),
            'failed_today' => (clone $base)->where('event', 'login.failed')->whereDate('created_at', today())->count(),
            'activity_today' => (clone $base)->whereDate('created_at', today())->count(),
            'staff_total' => User::withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
            'inactive_staff' => User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', false)->count(),
            'recent_failed' => (clone $base)->where('event', 'login.failed')->latest()->limit(10)->get(['event', 'description', 'ip_address', 'created_at', 'properties'])->toArray(),
            'recent_logins' => (clone $base)->where('event', 'login')->latest()->limit(10)->get(['event', 'description', 'ip_address', 'created_at'])->toArray(),
        ];
    }
}
