@php
    $stats = $this->getStats();
    $statCards = [
        ['label' => 'Staff users', 'value' => $stats['active_staff'].' / '.$stats['staff'], 'hint' => 'active / total', 'class' => 'isp-hub-stat--teal'],
        ['label' => '2FA enabled', 'value' => (string) $stats['with_2fa'], 'hint' => 'Protected staff accounts', 'class' => 'isp-hub-stat--emerald'],
        ['label' => 'Branches', 'value' => (string) $stats['branches'], 'hint' => 'Operational offices', 'class' => 'isp-hub-stat--sky'],
        ['label' => 'Roles / permissions', 'value' => $stats['roles'].' / '.$stats['permissions'], 'hint' => $stats['activity_today'].' events today', 'class' => 'isp-hub-stat--violet'],
    ];
    $links = [
        ['eyebrow' => 'Profile', 'label' => 'My account & password', 'hint' => 'Change your login email or password', 'url' => \App\Filament\Auth\EditAdminProfile::getUrl(), 'icon' => 'heroicon-o-user-circle', 'accent' => 'text-teal-600'],
        ['eyebrow' => 'Staff', 'label' => 'Staff users', 'hint' => 'Create accounts and assign roles & branches', 'url' => \App\Filament\Resources\UserResource::getUrl('index'), 'icon' => 'heroicon-o-users', 'accent' => 'text-violet-600'],
        ['eyebrow' => 'RBAC', 'label' => 'Permission matrix', 'hint' => 'Roles x permissions with grouped audit view', 'url' => \App\Filament\Pages\PermissionMatrix::getUrl(), 'icon' => 'heroicon-o-squares-2x2', 'accent' => 'text-indigo-600'],
        ['eyebrow' => 'Roles', 'label' => 'Role management', 'hint' => 'Templates, clone, and audit', 'url' => \App\Filament\Resources\RoleResource::getUrl('index'), 'icon' => 'heroicon-o-identification', 'accent' => 'text-slate-600'],
        ['eyebrow' => 'Catalog', 'label' => 'Permissions catalog', 'hint' => 'All RBAC permission keys', 'url' => \App\Filament\Resources\PermissionResource::getUrl('index'), 'icon' => 'heroicon-o-key', 'accent' => 'text-amber-600'],
        ['eyebrow' => 'Branches', 'label' => 'Branch management', 'hint' => 'Offices, contact, and branch IP rules', 'url' => \App\Filament\Resources\BranchResource::getUrl('index'), 'icon' => 'heroicon-o-building-office-2', 'accent' => 'text-cyan-600'],
        ['eyebrow' => 'Logs', 'label' => 'Activity logs', 'hint' => 'Staff actions and sign-ins', 'url' => \App\Filament\Resources\ActivityLogResource::getUrl('index'), 'icon' => 'heroicon-o-clipboard-document-list', 'accent' => 'text-rose-600'],
        ['eyebrow' => 'Audit', 'label' => 'Audit trail', 'hint' => 'Billing and integration change history', 'url' => \App\Filament\Resources\IntegrationSettingsAuditResource::getUrl('index'), 'icon' => 'heroicon-o-shield-check', 'accent' => 'text-emerald-600'],
        ['eyebrow' => 'Security', 'label' => 'IP restrictions', 'hint' => 'Tenant allowlist for admin login', 'url' => \App\Filament\Pages\ManageStaffSecurity::getUrl(), 'icon' => 'heroicon-o-lock-closed', 'accent' => 'text-amber-600'],
        ['eyebrow' => '2FA', 'label' => 'Two-factor setup', 'hint' => 'Authenticator for your account', 'url' => \App\Filament\Pages\TwoFactorSetup::getUrl(), 'icon' => 'heroicon-o-device-phone-mobile', 'accent' => 'text-indigo-600'],
        ['eyebrow' => 'Recovery', 'label' => 'Backup & restore', 'hint' => 'Download ZIP and restore after crash', 'url' => \App\Filament\Pages\ManagePlatformBackups::getUrl(), 'icon' => 'heroicon-o-cloud-arrow-down', 'accent' => 'text-emerald-600'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Identity & control"
            title="Admin & staff control"
            description="Role templates, permissions, matrix, audit trail, 2FA, and branch-aware staff control."
            class="isp-hub-hero--violet"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ $stats['permissions'] }} permissions indexed</span>
                    <span class="isp-hub-section__meta">{{ $stats['activity_today'] }} events today</span>
                </div>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Control shortcuts</h2>
                    <p class="isp-hub-section__desc">Jump into staff, RBAC, branch control, audit, security, and recovery actions from one page.</p>
                </div>
                <span class="isp-hub-section__meta">{{ count($links) }} controls</span>
            </div>
            <div class="isp-hub-link-grid isp-hub-link-grid--2 isp-hub-link-grid--3">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="isp-module-card group">
                        <div class="flex items-start gap-3">
                            <span class="isp-module-icon {{ $link['accent'] }}">
                                <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="isp-module-card__eyebrow">{{ $link['eyebrow'] }}</p>
                                <p class="isp-module-card__title">{{ $link['label'] }}</p>
                                <p class="isp-module-card__desc">{{ $link['hint'] }}</p>
                            </div>
                            <span class="isp-module-card__arrow" aria-hidden="true">→</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
