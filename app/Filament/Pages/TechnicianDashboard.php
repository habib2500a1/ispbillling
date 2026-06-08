<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Legacy route — unified into Field Technician Center.
 */
class TechnicianDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench';

    protected static string $view = 'filament.pages.technician-dashboard';

    protected static ?string $title = 'Technician dashboard';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->redirect(FieldTechnicianCenter::getUrl(), navigate: true);
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
