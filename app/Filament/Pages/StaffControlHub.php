<?php

namespace App\Filament\Pages;
use App\Filament\Pages\Concerns\HidesHubNavigation;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use Filament\Pages\Page;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffControlHub extends Page
{
    use HidesHubNavigation;
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static string $view = 'filament.pages.staff-control-hub';

    protected static ?string $navigationLabel = 'Admin & staff';

    protected static ?string $title = 'Admin & staff control';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $users = User::query();
        $branches = Branch::query();

        return [
            'staff' => (clone $users)->count(),
            'active_staff' => (clone $users)->where('is_active', true)->count(),
            'with_2fa' => (clone $users)->whereNotNull('two_factor_confirmed_at')->count(),
            'branches' => $branches->where('is_active', true)->count(),
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
            'activity_today' => ActivityLog::query()->whereDate('created_at', today())->count(),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'admin'])
                || $user->can('staff.view')
                || $user->can('security.roles'));
    }
}
