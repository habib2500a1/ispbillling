<?php

namespace App\Filament\Pages;

use App\Support\Rbac\StaffCapability;

use App\Models\SnmpPollLog;
use Filament\Pages\Page;

class SnmpMonitor extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static string $view = 'filament.pages.snmp-monitor';

    protected static ?string $navigationLabel = 'SNMP monitor';

    protected static ?string $title = 'SNMP monitoring';

    protected static bool $shouldRegisterNavigation = false;

    public function getPollLogs()
    {
        return SnmpPollLog::query()
            ->with('device')
            ->orderByDesc('polled_at')
            ->limit(50)
            ->get();
    }

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canMikrotik();
    }
}
