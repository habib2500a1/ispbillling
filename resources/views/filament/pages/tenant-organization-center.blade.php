@php
    $data = $this->getViewData();
    $org = $data['org'];
    $tenant = $data['tenant'];
    $kpis = $data['kpis'];
    $kpiCards = $data['kpiCards'];
    $quickActions = $data['quickActions'];
    $navLinks = $data['navLinks'];
    $access = $data['access'];
@endphp

<x-filament-panels::page class="torg-page isp-hub-page">
    <link rel="stylesheet" href="{{ asset('css/tenant-org-hub.css') }}?v={{ @filemtime(public_path('css/tenant-org-hub.css')) ?: 1 }}">
    <script src="{{ asset('js/tenant-org-hub.js') }}?v={{ @filemtime(public_path('js/tenant-org-hub.js')) ?: 1 }}" defer data-cfasync="false"></script>

    <div class="space-y-5">
        <section class="torg-hero torg-glass">
            <p class="text-xs uppercase tracking-wider opacity-80 mb-1">ISP Tenant & Organization</p>
            <h1 class="torg-hero__title">{{ $tenant['name'] ?? 'Organization' }}</h1>
            <p class="torg-hero__sub">
                {{ $tenant['organization_type_label'] ?? 'Single ISP' }}
                · {{ $tenant['domain'] ?? config('app.url') }}
                · {{ $kpis['branches'] ?? 0 }} branches · {{ $kpis['resellers'] ?? 0 }} resellers
            </p>
            <div class="torg-search" wire:ignore.self>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Search staff, role, branch, reseller, permission, tenant…"
                    autocomplete="off"
                >
                @if (strlen($searchQuery) >= 2)
                    <div class="torg-search-results">
                        @forelse ($searchResults as $row)
                            <a href="{{ $row['url'] ?? '#' }}">
                                <strong>{{ $row['label'] }}</strong>
                                <span>{{ ucfirst($row['type']) }} · {{ $row['meta'] ?? '' }}</span>
                            </a>
                        @empty
                            <p class="p-3 text-sm opacity-70">No matches</p>
                        @endforelse
                    </div>
                @endif
            </div>
            <div class="torg-pills">
                <span class="torg-pill">{{ $tenant['contact_phone'] ?: 'No phone' }}</span>
                <span class="torg-pill">{{ $tenant['contact_email'] ?: 'No email' }}</span>
                @if ($tenant['logo_url'] ?? null)
                    <img src="{{ $tenant['logo_url'] }}" alt="" class="torg-logo" loading="lazy">
                @endif
            </div>
        </section>

        <div class="torg-kpi-grid">
            @foreach ($kpiCards as $card)
                <div class="torg-kpi torg-glass {{ $card['class'] }}">
                    <span>{{ $card['label'] }}</span>
                    <strong>{{ number_format($kpis[$card['key']] ?? 0, ($card['key'] === 'total_revenue' ? 0 : 0)) }}{{ $card['suffix'] }}</strong>
                </div>
            @endforeach
        </div>

        <div class="torg-tabs">
            @foreach ([
                'dashboard' => 'Dashboard',
                'staff' => 'Staff',
                'roles' => 'Roles',
                'branches' => 'Branches',
                'resellers' => 'Resellers',
                'security' => 'Security',
                'activity' => 'Activity',
                'branding' => 'White label',
            ] as $tab => $label)
                <button type="button" wire:click="setTab('{{ $tab }}')" @class(['torg-tab', 'torg-tab--active' => $activeTab === $tab])>{{ $label }}</button>
            @endforeach
            <button type="button" wire:click="refreshOrg" class="torg-tab ml-auto" wire:loading.attr="disabled">↻ Refresh</button>
        </div>

        <div class="torg-layout">
            <nav class="torg-nav torg-glass">
                <p class="text-xs uppercase opacity-60 mb-2 px-1">Modules</p>
                @foreach ($navLinks as $link)
                    @if (! ($link['super'] ?? false) || $access['tenants'])
                        <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                    @endif
                @endforeach
            </nav>

            <main class="space-y-4">
                @if (in_array($activeTab, ['dashboard', 'staff', 'roles', 'branches', 'security'], true))
                    <section class="torg-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Quick actions</h2>
                        <div class="torg-quick-grid">
                            @foreach ($quickActions as $action)
                                <a href="{{ $action['url'] }}" class="torg-quick torg-quick--{{ $action['tone'] }}">
                                    <x-filament::icon :icon="'heroicon-o-'.$action['icon']" class="h-5 w-5" />
                                    <strong>{{ $action['label'] }}</strong>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($activeTab === 'dashboard')
                    @php $sub = $org['subscription'] ?? []; @endphp
                    <section class="torg-glass p-4 mb-4">
                        <h2 class="text-sm font-semibold mb-3">SaaS package</h2>
                        <dl class="torg-dl">
                            <div><dt>Plan</dt><dd>{{ $sub['plan_name'] ?? '—' }}</dd></div>
                            <div><dt>Customers</dt><dd>
                                {{ $sub['customers_used'] ?? 0 }}
                                / {{ ($sub['max_customers'] ?? null) === null ? '∞' : $sub['max_customers'] }}
                            </dd></div>
                            <div><dt>Platform fee</dt><dd>{{ number_format($sub['monthly_fee_bdt'] ?? 0, 0) }} BDT / month</dd></div>
                            <div><dt>Bill day</dt><dd>{{ $sub['billing_day'] ?? 1 }} of month</dd></div>
                            <div><dt>Status</dt><dd>{{ ucfirst($sub['status'] ?? 'active') }}</dd></div>
                        </dl>
                        @php $pi = $org['platform_invoice'] ?? null; @endphp
                        @if ($pi)
                            <p class="text-xs opacity-75 mt-3">
                                Latest bill: <strong>{{ $pi['invoice_number'] }}</strong>
                                · {{ number_format($pi['amount'] ?? 0, 0) }} BDT
                                · {{ ucfirst($pi['status'] ?? 'issued') }}
                                @if (! empty($pi['due_date'])) · due {{ $pi['due_date'] }} @endif
                            </p>
                        @else
                            <p class="text-xs opacity-60 mt-3">No platform invoice yet — auto-generated on bill day.</p>
                        @endif
                        <div class="flex flex-wrap gap-2 mt-3">
                            @if ($pi && ($pi['status'] ?? '') !== 'paid' && ! empty($pi['payment_url']))
                                <a href="{{ $pi['payment_url'] }}" target="_blank" rel="noopener noreferrer" class="torg-link-btn">Pay now</a>
                            @endif
                            @if ($access['tenants'] ?? false)
                                <a href="{{ \App\Filament\Resources\TenantResource::getUrl('edit', ['record' => $tenant['id'] ?? 1]) }}" class="torg-link-btn">Edit package</a>
                                <a href="{{ \App\Filament\Resources\PlatformInvoiceResource::getUrl('index') }}" class="torg-link-btn">All platform bills</a>
                            @endif
                        </div>
                    </section>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section class="torg-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">Organization profile</h2>
                            <dl class="torg-dl">
                                <div><dt>Type</dt><dd>{{ $tenant['organization_type_label'] ?? '—' }}</dd></div>
                                <div><dt>Address</dt><dd>{{ $tenant['address'] ?: '—' }}</dd></div>
                                <div><dt>Domain</dt><dd>{{ $tenant['domain'] ?: '—' }}</dd></div>
                                <div><dt>Roles / permissions</dt><dd>{{ $kpis['roles'] ?? 0 }} / {{ $kpis['permissions'] ?? 0 }}</dd></div>
                            </dl>
                        </section>
                        <section class="torg-glass p-4">
                            <h2 class="text-sm font-semibold mb-3">Security snapshot</h2>
                            <dl class="torg-dl">
                                <div><dt>Logins today</dt><dd>{{ $org['security']['logins_today'] ?? 0 }}</dd></div>
                                <div><dt>Failed logins</dt><dd>{{ $org['security']['failed_today'] ?? 0 }}</dd></div>
                                <div><dt>2FA enabled</dt><dd>{{ $org['security']['with_2fa'] ?? 0 }}</dd></div>
                                <div><dt>Inactive staff</dt><dd>{{ $org['security']['inactive_staff'] ?? 0 }}</dd></div>
                            </dl>
                        </section>
                    </div>
                @endif

                @if ($activeTab === 'staff')
                    <section class="torg-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Staff profiles</h2>
                        <div class="torg-staff-grid">
                            @forelse ($org['staff_breakdown'] ?? [] as $staff)
                                <a href="{{ $staff['url'] }}" class="torg-staff-card">
                                    <strong>{{ $staff['name'] }}</strong>
                                    <span>{{ implode(', ', $staff['roles'] ?? []) ?: 'No role' }}</span>
                                    <span class="torg-staff-card__meta">
                                        {{ $staff['is_active'] ? 'Active' : 'Inactive' }}
                                        · {{ $staff['has_2fa'] ? '2FA' : 'No 2FA' }}
                                        · {{ $staff['tickets_assigned'] }} tickets
                                    </span>
                                </a>
                            @empty
                                <p class="text-sm opacity-70">No staff found.</p>
                            @endforelse
                        </div>
                    </section>
                @endif

                @if ($activeTab === 'roles')
                    <section class="torg-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Role management</h2>
                        <div class="torg-table-wrap">
                            <table class="torg-table">
                                <thead><tr><th>Role</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($org['roles'] ?? [] as $role)
                                        <tr>
                                            <td>{{ $role['name'] }}</td>
                                            <td>{{ $role['permissions'] }}</td>
                                            <td>{{ $role['users'] }}</td>
                                            <td><a href="{{ $role['url'] }}">Edit →</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs opacity-60 mt-3">Clone, create, and delete roles from Role management.</p>
                    </section>
                @endif

                @if ($activeTab === 'branches')
                    <section class="torg-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Branch management</h2>
                        <div class="torg-branch-grid">
                            @forelse ($org['branches'] ?? [] as $branch)
                                <a href="{{ $branch['url'] }}" class="torg-branch-card">
                                    <strong>{{ $branch['name'] }}</strong>
                                    <span>{{ $branch['code'] ?? '—' }} · {{ $branch['manager'] ?? 'No manager' }}</span>
                                    <span class="torg-branch-card__meta">
                                        {{ $branch['staff'] }} staff · {{ number_format($branch['revenue_mtd'] ?? 0, 0) }} BDT MTD
                                    </span>
                                </a>
                            @empty
                                <p class="text-sm opacity-70">No branches configured.</p>
                            @endforelse
                        </div>
                    </section>
                @endif

                @if ($activeTab === 'resellers' && $access['resellers'])
                    <section class="torg-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Reseller management</h2>
                        <dl class="torg-dl mb-4">
                            <div><dt>Total resellers</dt><dd>{{ $org['resellers']['total'] ?? 0 }}</dd></div>
                            <div><dt>Active</dt><dd>{{ $org['resellers']['active'] ?? 0 }}</dd></div>
                            <div><dt>Reseller customers</dt><dd>{{ $org['resellers']['customers'] ?? 0 }}</dd></div>
                            <div><dt>White-label enabled</dt><dd>{{ $org['resellers']['white_label_enabled'] ?? 0 }}</dd></div>
                        </dl>
                        <a href="{{ \App\Filament\Pages\ResellersHub::getUrl() }}" class="torg-link-btn">Open Reseller hub →</a>
                    </section>
                @endif

                @if ($activeTab === 'security')
                    <section class="torg-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Security center</h2>
                        <div class="grid gap-3 sm:grid-cols-2 mb-4">
                            <a href="{{ \App\Filament\Pages\SecurityDashboard::getUrl() }}" class="torg-quick torg-quick--indigo">Security dashboard</a>
                            <a href="{{ \App\Filament\Pages\ManageStaffSecurity::getUrl() }}" class="torg-quick torg-quick--amber">IP & 2FA policy</a>
                            <a href="{{ \App\Filament\Pages\TwoFactorSetup::getUrl() }}" class="torg-quick torg-quick--violet">Two-factor setup</a>
                            <a href="{{ \App\Filament\Resources\ActivityLogResource::getUrl('index') }}" class="torg-quick torg-quick--rose">Login history</a>
                        </div>
                        <h3 class="text-xs uppercase opacity-60 mb-2">Recent logins</h3>
                        <ul class="torg-activity-list">
                            @foreach ($org['security']['recent_logins'] ?? [] as $row)
                                <li>
                                    <span>{{ $row['description'] ?? 'Login' }}</span>
                                    <span>{{ $row['ip_address'] ?? '' }} · {{ \Illuminate\Support\Carbon::parse($row['created_at'])->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($activeTab === 'activity')
                    <section class="torg-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Activity log center</h2>
                        <ul class="torg-activity-list">
                            @forelse ($org['activity'] ?? [] as $row)
                                <li>
                                    <span><strong>{{ $row['event'] }}</strong> — {{ $row['description'] }}</span>
                                    <span>{{ $row['log_name'] }} · {{ $row['ip_address'] ?? '' }} · {{ $row['at'] }}</span>
                                </li>
                            @empty
                                <li class="opacity-70">No recent activity.</li>
                            @endforelse
                        </ul>
                        <a href="{{ \App\Filament\Resources\ActivityLogResource::getUrl('index') }}" class="torg-link-btn mt-3 inline-block">Full audit log →</a>
                    </section>
                @endif

                @if ($activeTab === 'branding')
                    <section class="torg-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">White label & branding</h2>
                        <dl class="torg-dl mb-4">
                            <div><dt>App name</dt><dd>{{ $org['white_label']['app_name'] ?? '—' }}</dd></div>
                            <div><dt>Primary color</dt><dd><span class="torg-swatch" style="background:{{ $org['white_label']['primary_color'] ?? '#4f46e5' }}"></span></dd></div>
                            <div><dt>Accent color</dt><dd><span class="torg-swatch" style="background:{{ $org['white_label']['accent_color'] ?? '#0ea5e9' }}"></span></dd></div>
                            <div><dt>Theme</dt><dd>{{ $org['white_label']['theme'] ?? 'default' }}</dd></div>
                        </dl>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ $org['white_label']['company_setup_url'] ?? '#' }}" class="torg-link-btn">Company setup</a>
                            <a href="{{ $org['white_label']['portal_settings_url'] ?? '#' }}" class="torg-link-btn">Portal settings</a>
                            <a href="{{ $org['white_label']['mobile_app_url'] ?? '#' }}" class="torg-link-btn">Mobile app branding</a>
                        </div>
                    </section>
                @endif
            </main>
        </div>
    </div>
</x-filament-panels::page>
