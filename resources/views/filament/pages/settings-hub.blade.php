<x-filament-panels::page class="isp-settings-hub-page">
    <div class="ch-pro">
        <header class="ch-hero">
            <div class="ch-hero__grid">
                <span class="ch-hero__badge">
                    <span class="ch-hero__badge-dot" aria-hidden="true"></span>
                    System Configuration
                </span>
                <h1 class="ch-hero__title">Settings Hub</h1>
                <p class="ch-hero__sub">
                    Manage all core system configurations, integrations, billing rules, network parameters, and tenant branding from this centralized command center.
                </p>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-gray-700 transition-colors">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-indigo-500/10 rounded-lg">
                        <x-filament::icon icon="heroicon-o-building-office-2" class="w-6 h-6 text-indigo-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-white">Company & Tenant</h3>
                </div>
                <p class="text-sm text-gray-400 mb-6">Manage business details, logo, branding, address, and localized tenant rules.</p>
                <a href="{{ \App\Filament\Pages\ManageCompanySetup::getUrl() }}" class="text-indigo-400 hover:text-indigo-300 text-sm font-medium flex items-center gap-2">
                    Configure Setup <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4" />
                </a>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-gray-700 transition-colors">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-emerald-500/10 rounded-lg">
                        <x-filament::icon icon="heroicon-o-banknotes" class="w-6 h-6 text-emerald-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-white">Payment Gateways</h3>
                </div>
                <p class="text-sm text-gray-400 mb-6">Configure bKash, Nagad, SSLCommerz, auto-reconciliation, and payment webhooks.</p>
                <a href="{{ \App\Filament\Pages\ManagePaymentSettings::getUrl() }}" class="text-emerald-400 hover:text-emerald-300 text-sm font-medium flex items-center gap-2">
                    Manage Gateways <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4" />
                </a>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-gray-700 transition-colors">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-sky-500/10 rounded-lg">
                        <x-filament::icon icon="heroicon-o-server-stack" class="w-6 h-6 text-sky-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-white">Network Core</h3>
                </div>
                <p class="text-sm text-gray-400 mb-6">Setup RADIUS, MikroTik auto-sync, auto-disconnect logic, and network API parameters.</p>
                <a href="{{ \App\Filament\Pages\ManageNetworkSettings::getUrl() }}" class="text-sky-400 hover:text-sky-300 text-sm font-medium flex items-center gap-2">
                    Network Settings <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4" />
                </a>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-gray-700 transition-colors">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-rose-500/10 rounded-lg">
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="w-6 h-6 text-rose-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-white">SMS & WhatsApp</h3>
                </div>
                <p class="text-sm text-gray-400 mb-6">Configure SMS API keys, WhatsApp Cloud API, automated notification templates.</p>
                <a href="{{ \App\Filament\Pages\ManageMfsSmsSettings::getUrl() }}" class="text-rose-400 hover:text-rose-300 text-sm font-medium flex items-center gap-2">
                    Message Settings <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4" />
                </a>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-gray-700 transition-colors">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-amber-500/10 rounded-lg">
                        <x-filament::icon icon="heroicon-o-window" class="w-6 h-6 text-amber-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-white">Customer Portal</h3>
                </div>
                <p class="text-sm text-gray-400 mb-6">Control portal features, self-care permissions, support ticket rules, and portal UI.</p>
                <a href="{{ \App\Filament\Pages\ManagePortalSettings::getUrl() }}" class="text-amber-400 hover:text-amber-300 text-sm font-medium flex items-center gap-2">
                    Portal Config <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4" />
                </a>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-gray-700 transition-colors">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-violet-500/10 rounded-lg">
                        <x-filament::icon icon="heroicon-o-shield-check" class="w-6 h-6 text-violet-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-white">App & Security</h3>
                </div>
                <p class="text-sm text-gray-400 mb-6">Manage global app behavior, 2FA enforcements, password policies, and API rate limits.</p>
                <a href="{{ \App\Filament\Pages\ManageAppSettings::getUrl() }}" class="text-violet-400 hover:text-violet-300 text-sm font-medium flex items-center gap-2">
                    Security Rules <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4" />
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>