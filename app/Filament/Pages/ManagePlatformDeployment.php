<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Models\Tenant;
use App\Services\Platform\PlatformLicenseService;
use Filament\Pages\Page;

class ManagePlatformDeployment extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.manage-platform-deployment';

    protected static ?string $navigationLabel = 'Deployment & license';

    protected static ?string $title = 'Deployment & license';

    protected static ?string $slug = 'platform-deployment';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDeploymentInfo(): array
    {
        $license = app(PlatformLicenseService::class);
        $check = $license->validate();

        return [
            'mode' => $license->deploymentMode(),
            'mode_label' => $license->isSaasDeployment() ? 'SaaS (rent — you host)' : 'On-premise (sell)',
            'enforce' => $license->isEnforced(),
            'license_valid' => $check['valid'],
            'license_message' => $check['message'],
            'payload' => $check['payload'],
            'tenant_count' => Tenant::query()->count(),
            'max_tenants' => $license->maxTenants(),
            'app_url' => (string) config('app.url'),
        ];
    }
}
