<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;

class SystemErrorLogsPage extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string $view = 'filament.pages.system-error-logs';

    protected static ?string $navigationLabel = 'Error logs';

    protected static ?string $title = 'System error logs';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $slug = 'system-error-logs';

    protected static bool $shouldRegisterNavigation = false;

    public int $tailLines = 200;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }

    /**
     * @return list<string>
     */
    public function getLogLines(): array
    {
        $path = storage_path('logs/laravel.log');
        if (! File::isFile($path)) {
            return ['Log file not found: storage/logs/laravel.log'];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines)) {
            return ['Unable to read log file.'];
        }

        return array_slice($lines, -max(50, min(1000, $this->tailLines)));
    }
}
