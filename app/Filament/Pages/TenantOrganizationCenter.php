<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\Tenant\TenantOrganizationIntelligenceService;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class TenantOrganizationCenter extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static string $view = 'filament.pages.tenant-organization-center';

    protected static ?string $navigationLabel = 'Organization';

    protected static ?string $title = 'Tenant & Organization Center';

    protected static ?string $slug = 'tenant-organization';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $org = [];

    public string $searchQuery = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public string $activeTab = 'dashboard';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->refreshOrg();
    }

    public function refreshOrg(): void
    {
        $this->org = app(TenantOrganizationIntelligenceService::class)->snapshot();
    }

    public function updatedSearchQuery(): void
    {
        $this->searchResults = app(TenantOrganizationIntelligenceService::class)->search($this->searchQuery);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'tenant-org-module'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $o = $this->org;
        $kpis = $o['kpis'] ?? [];

        return [
            'org' => $o,
            'tenant' => $o['tenant'] ?? [],
            'kpis' => $kpis,
            'kpiCards' => [
                ['key' => 'total_customers', 'label' => 'Customers', 'suffix' => '', 'class' => 'torg-kpi--cyan'],
                ['key' => 'total_staff', 'label' => 'Staff', 'suffix' => '', 'class' => 'torg-kpi--violet'],
                ['key' => 'total_revenue', 'label' => 'Revenue (MTD)', 'suffix' => ' BDT', 'class' => 'torg-kpi--emerald'],
                ['key' => 'total_routers', 'label' => 'Routers', 'suffix' => '', 'class' => 'torg-kpi--sky'],
                ['key' => 'total_olts', 'label' => 'OLTs', 'suffix' => '', 'class' => 'torg-kpi--indigo'],
                ['key' => 'total_onus', 'label' => 'ONUs', 'suffix' => '', 'class' => 'torg-kpi--amber'],
                ['key' => 'active_tickets', 'label' => 'Open tickets', 'suffix' => '', 'class' => 'torg-kpi--rose'],
            ],
            'quickActions' => $this->quickActions(),
            'navLinks' => $this->navLinks(),
            'access' => $this->accessFlags(),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function quickActions(): array
    {
        return [
            ['label' => 'Add staff', 'url' => \App\Filament\Resources\UserResource::getUrl('create'), 'icon' => 'user-plus', 'tone' => 'violet'],
            ['label' => 'Roles', 'url' => \App\Filament\Resources\RoleResource::getUrl('index'), 'icon' => 'shield-check', 'tone' => 'indigo'],
            ['label' => 'Permission matrix', 'url' => PermissionMatrix::getUrl(), 'icon' => 'table-cells', 'tone' => 'sky'],
            ['label' => 'Branches', 'url' => \App\Filament\Resources\BranchResource::getUrl('index'), 'icon' => 'building-office-2', 'tone' => 'cyan'],
            ['label' => 'Activity log', 'url' => \App\Filament\Resources\ActivityLogResource::getUrl('index'), 'icon' => 'clipboard-document-list', 'tone' => 'rose'],
            ['label' => 'Security', 'url' => ManageStaffSecurity::getUrl(), 'icon' => 'lock-closed', 'tone' => 'amber'],
            ['label' => 'Resellers', 'url' => ResellersHub::getUrl(), 'icon' => 'building-storefront', 'tone' => 'emerald'],
            ['label' => 'Branding', 'url' => ManageCompanySetup::getUrl(), 'icon' => 'paint-brush', 'tone' => 'slate'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function navLinks(): array
    {
        return [
            ['label' => 'Staff users', 'url' => \App\Filament\Resources\UserResource::getUrl('index')],
            ['label' => 'Roles', 'url' => \App\Filament\Resources\RoleResource::getUrl('index')],
            ['label' => 'Permissions', 'url' => \App\Filament\Resources\PermissionResource::getUrl('index')],
            ['label' => 'Branches', 'url' => \App\Filament\Resources\BranchResource::getUrl('index')],
            ['label' => 'Security dashboard', 'url' => SecurityDashboard::getUrl()],
            ['label' => 'Two-factor setup', 'url' => TwoFactorSetup::getUrl()],
            ['label' => 'Tenants (super-admin)', 'url' => \App\Filament\Resources\TenantResource::getUrl('index'), 'super' => true],
            ['label' => 'Platform invoices', 'url' => \App\Filament\Resources\PlatformInvoiceResource::getUrl('index'), 'super' => true],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function accessFlags(): array
    {
        $cap = StaffCapability::for(auth()->user());

        return [
            'staff' => $cap->canStaffModule(),
            'security' => $cap->canAny(['security.manage', 'audit.view']),
            'resellers' => $cap->canResellers(),
            'tenants' => auth()->user()?->hasRole('super-admin') ?? false,
        ];
    }

    public static function canAccess(): bool
    {
        $cap = StaffCapability::for(auth()->user());

        return $cap->canStaffModule()
            || $cap->canAny(['security.manage', 'security.roles', 'audit.view', 'branches.view', 'branches.manage'])
            || auth()->user()?->hasRole('super-admin') === true;
    }
}
