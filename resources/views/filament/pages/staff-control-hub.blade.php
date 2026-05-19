@php
    $stats = $this->getStats();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-indigo-50/40 p-6 shadow-sm dark:border-violet-900/40 dark:from-violet-950/50 dark:via-gray-900 dark:to-gray-900">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Admin & staff control</h2>
            <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                16 role templates · {{ $stats['permissions'] }} permissions · matrix · clone · audit · 2FA · branches.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Staff users</p>
                <p class="mt-1 text-2xl font-bold">{{ $stats['active_staff'] }} / {{ $stats['staff'] }}</p>
                <p class="text-xs text-gray-500">active / total</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">2FA enabled</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $stats['with_2fa'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Branches</p>
                <p class="mt-1 text-2xl font-bold">{{ $stats['branches'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Roles / permissions</p>
                <p class="mt-1 text-2xl font-bold">{{ $stats['roles'] }} / {{ $stats['permissions'] }}</p>
                <p class="text-xs text-gray-500">{{ $stats['activity_today'] }} events today</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Auth\EditAdminProfile::getUrl() }}" class="group rounded-xl border border-teal-200 bg-teal-50/50 p-5 shadow-sm transition hover:border-teal-400 dark:border-teal-800 dark:bg-teal-950/20">
                <p class="font-semibold text-teal-900 dark:text-teal-100">My account & password</p>
                <p class="mt-1 text-sm text-teal-800/80 dark:text-teal-300">Change your login email or password</p>
            </a>
            <a href="{{ \App\Filament\Resources\UserResource::getUrl('index') }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold group-hover:text-violet-600 dark:text-white">Staff users</p>
                <p class="mt-1 text-sm text-gray-500">Create accounts · assign roles & branches</p>
            </a>
            <a href="{{ \App\Filament\Resources\RoleResource::getUrl('index') }}" class="group rounded-xl border border-violet-200 bg-violet-50/50 p-5 shadow-sm transition hover:border-violet-400 dark:border-violet-800 dark:bg-violet-950/20">
                <p class="font-semibold text-violet-900 dark:text-violet-100">Role management</p>
                <p class="mt-1 text-sm text-violet-800/80 dark:text-violet-300">Templates · matrix · clone · audit</p>
            </a>
            <a href="{{ \App\Filament\Resources\PermissionResource::getUrl('index') }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Permissions catalog</p>
                <p class="mt-1 text-sm text-gray-500">All RBAC permission keys</p>
            </a>
            <a href="{{ \App\Filament\Resources\BranchResource::getUrl('index') }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Branch management</p>
                <p class="mt-1 text-sm text-gray-500">Offices · contact · branch IP rules</p>
            </a>
            <a href="{{ \App\Filament\Resources\ActivityLogResource::getUrl('index') }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Activity logs</p>
                <p class="mt-1 text-sm text-gray-500">Staff actions & sign-ins</p>
            </a>
            <a href="{{ \App\Filament\Resources\IntegrationSettingsAuditResource::getUrl('index') }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Audit trail</p>
                <p class="mt-1 text-sm text-gray-500">Billing & integration change history</p>
            </a>
            <a href="{{ \App\Filament\Pages\ManageStaffSecurity::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">IP restrictions</p>
                <p class="mt-1 text-sm text-gray-500">Tenant allowlist for admin login</p>
            </a>
            <a href="{{ \App\Filament\Pages\TwoFactorSetup::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Two-factor (2FA)</p>
                <p class="mt-1 text-sm text-gray-500">Set up authenticator for your account</p>
            </a>
            <a href="{{ \App\Filament\Pages\ManagePlatformBackups::getUrl() }}" class="group rounded-xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm transition hover:border-emerald-400 dark:border-emerald-900 dark:bg-emerald-950/20">
                <p class="font-semibold text-emerald-900 dark:text-emerald-100">Backup & restore</p>
                <p class="mt-1 text-sm text-emerald-800/80 dark:text-emerald-300">Download ZIP · upload after crash</p>
            </a>
        </div>
    </div>
</x-filament-panels::page>
