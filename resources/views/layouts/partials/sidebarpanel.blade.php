@if (auth()->user()->roles()->exists() || auth()->user()->permissions()->exists())
    @if (auth()->user()->hasRole('Reseller'))
        <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">

            {{-- ── Dashboard (always visible) ── --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Reseller Panel') }}</div>
                    <div class="col ps-0">
                        <hr class="mb-0 navbar-vertical-divider" />
                    </div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('reseller.dashboard') }}"
                    role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-speedometer2 me-2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Dashboard') }}</span>
                    </div>
                </a>
            </li>

            {{-- ── Customers ── --}}
            @canany(['view-customer', 'create-customer', 'edit-customer', 'delete-customer'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">{{ __('Customers') }}</div>
                        <div class="col ps-0">
                            <hr class="mb-0 navbar-vertical-divider" />
                        </div>
                    </div>
                    <a wire:navigate.hover wire:current.exact="active" class="nav-link"
                        href="{{ route('reseller.customers.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-people-fill"></i></span>
                            <span class="nav-link-text ps-1">{{ __('Customer List') }}</span>
                        </div>
                    </a>
                    @can('create-customer')
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('reseller.customers.create') }}" role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-person-fill-add"></i></span>
                                <span class="nav-link-text ps-1">{{ __('New Customer') }}</span>
                            </div>
                        </a>
                    @endcan
                </li>
            @endcanany

            {{-- ── Billing & Payments ── --}}
            @canany(['payment-collection', 'payment-collection-edit', 'payment-collection-invoice', 'payment-history',
                'payment-collection-report', 'collection-list', 'without-collection-list', 'amount-collection'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">{{ __('Billing') }}</div>
                        <div class="col ps-0">
                            <hr class="mb-0 navbar-vertical-divider" />
                        </div>
                    </div>
                    @can('payment-collection')
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('payment-collection') }}"
                            role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-cash-coin"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Payment Collection') }}</span>
                            </div>
                        </a>
                    @endcan
                    @can('payment-collection-edit')
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('collection-edit') }}"
                            role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-pencil-square"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Collection Edit') }}</span>
                            </div>
                        </a>
                    @endcan
                    @canany(['payment-collection-invoice', 'payment-collection'])
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('payment-invoice') }}"
                            role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-receipt"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Payment Invoice') }}</span>
                            </div>
                        </a>
                    @endcanany
                    @canany(['payment-collection-report', 'payment-collection', 'collection-list', 'without-collection-list',
                        'amount-collection'])
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('collection-report.index') }}" role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-bar-chart-line"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Collection Report') }}</span>
                            </div>
                        </a>
                    @endcanany
                    @canany(['payment-collection', 'payment-collection-report', 'amount-collection'])
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('billing.discounts') }}"
                            role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-percent"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Discount') }}</span>
                            </div>
                        </a>
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('billing.advances') }}"
                            role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-wallet2"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Advance') }}</span>
                            </div>
                        </a>
                    @endcanany
                </li>
            @endcanany

            {{-- ── Wallet & Vouchers (always visible to all resellers) ── --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('My Account') }}</div>
                    <div class="col ps-0">
                        <hr class="mb-0 navbar-vertical-divider" />
                    </div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('reseller.wallet.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-wallet2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Wallet & Earnings') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('reseller.vouchers.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-ticket-perforated-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Vouchers') }}</span>
                    </div>
                </a>
            </li>

            {{-- ── Setup & Access ── --}}
            @canany(['package-setup'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">{{ __('Setup') }}</div>
                        <div class="col ps-0">
                            <hr class="mb-0 navbar-vertical-divider" />
                        </div>
                    </div>
                    @can('package-setup')
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('reseller.packages.index') }}" role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-box2"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Packages') }}</span>
                            </div>
                        </a>
                    @endcan
                </li>
            @endcanany

        </ul>
    @else
        @php
            $navIs = fn (...$names) => request()->routeIs(...$names);
            $openClients = $navIs('customers.*', 'new-customer', 'customers.excel-upload', 'online-clients', 'package-list-setup', 'address-setup');
            $openBilling = $navIs('payment-collection', 'collection-edit', 'payment-invoice', 'billing-notices', 'sms-notices', 'admin.staff-cash', 'billing.discounts', 'billing.advances');
            $openOptical = $navIs('olt-management', 'onu-management');
            $openMtSetup = $navIs('mikrotik-ip-setup', 'mikrotik-pppoe-setup', 'mikrotik-radius-setup', 'mikrotik-firewall-setup', 'mikrotik-walled-garden', 'mikrotik-queue-setup', 'mikrotik-vpn-setup', 'mikrotik-interface-setup', 'mikrotik-traffic-monitor', 'mikrotik-backup-setup');
            $openNetwork = $openMtSetup || $navIs('mikrotik-sync', 'bandwidth-hub', 'mikrotik-hotspot-manager');
            $openSupport = $navIs('admin-tickets');
            $openReports = $navIs('collection-report.*', 'customer-summary', 'dis-report');
            $openFinance = $navIs('admin.expenses', 'admin.profit-summary', 'accounts-hub', 'inventory-hub');
            $openSystem = $navIs('admin.purchase-requests', 'admin.saas-operators', 'admin.resellers.*', 'admin.activity-logs', 'admin.login-logs', 'admin.system-logs', 'mikrotik-log-viewer', 'admin.vouchers', 'admin.reviews', 'sms-setup', 'automatic-processes', 'sms-bridge.*', 'hr-hub');
        @endphp
        <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Command') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                <a class="nav-link {{ $navIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-speedometer2 me-2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Dashboard') }}</span>
                    </div>
                </a>
                <a class="nav-link {{ $navIs('isp-os') ? 'active' : '' }}" href="{{ route('isp-os') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-command me-2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('ISP OS') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover class="nav-link {{ $navIs('admin-center') ? 'active' : '' }}" href="{{ route('admin-center') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-sliders2 me-2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Admin Center') }}</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $openClients ? '' : 'collapsed' }} {{ $openClients ? 'active' : '' }}"
                    href="#navClientMgmt" role="button" data-bs-toggle="collapse"
                    aria-expanded="{{ $openClients ? 'true' : 'false' }}" aria-controls="navClientMgmt">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-people-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Client Management') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $openClients ? 'show' : '' }}" id="navClientMgmt">
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('customers.index', 'customers.show', 'customers.edit') ? 'active' : '' }}"
                            href="{{ route('customers.index') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('All Clients') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('new-customer') ? 'active' : '' }}" href="{{ route('new-customer') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('New Client') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('customers.excel-upload') ? 'active' : '' }}" href="{{ route('customers.excel-upload') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Excel upload') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('online-clients') ? 'active' : '' }}" href="{{ route('online-clients') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Online Clients') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('package-list-setup') ? 'active' : '' }}" href="{{ route('package-list-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Packages') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('address-setup') ? 'active' : '' }}" href="{{ route('address-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Zones & Areas') }}</span></div>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $openBilling ? '' : 'collapsed' }} {{ $openBilling ? 'active' : '' }}"
                    href="#navBilling" role="button" data-bs-toggle="collapse"
                    aria-expanded="{{ $openBilling ? 'true' : 'false' }}" aria-controls="navBilling">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-cash-coin"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Billing') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $openBilling ? 'show' : '' }}" id="navBilling">
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('payment-collection') ? 'active' : '' }}" href="{{ route('payment-collection') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Collect Payment') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('collection-edit') ? 'active' : '' }}" href="{{ route('collection-edit') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Collection Edit') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('payment-invoice') ? 'active' : '' }}" href="{{ route('payment-invoice') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Invoices') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('billing.discounts') ? 'active' : '' }}" href="{{ route('billing.discounts') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Discount') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('billing.advances') ? 'active' : '' }}" href="{{ route('billing.advances') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Advance') }}</span></div>
                        </a>
                    </li>
                    @if(hasAccess(['Super Admin'], ['billing-notices', 'payment-collection', 'sms-setup']))
                        <li class="nav-item">
                            <a wire:navigate.hover class="nav-link {{ $navIs('billing-notices') ? 'active' : '' }}" href="{{ route('billing-notices') }}">
                                <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Billing Notices') }}</span></div>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a wire:navigate.hover class="nav-link {{ $navIs('sms-notices') ? 'active' : '' }}" href="{{ route('sms-notices') }}">
                                <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('SMS Notices') }}</span></div>
                            </a>
                        </li>
                    @endif
                    @if (hasAccess(['Super Admin'], ['payment-collection', 'amount-collection', 'staff-cash']))
                        <li class="nav-item">
                            <a wire:navigate.hover class="nav-link {{ $navIs('admin.staff-cash') ? 'active' : '' }}" href="{{ route('admin.staff-cash') }}">
                                <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Staff Cash') }}</span></div>
                            </a>
                        </li>
                    @endif
                </ul>
            </li>

            @if(hasAccess(['Super Admin'], ['olt-management', 'onu-management', 'mikrotik-setup']))
            <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $openOptical ? '' : 'collapsed' }} {{ $openOptical ? 'active' : '' }}"
                    href="#navOptical" role="button" data-bs-toggle="collapse"
                    aria-expanded="{{ $openOptical ? 'true' : 'false' }}" aria-controls="navOptical">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-broadcast-pin"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Optical') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $openOptical ? 'show' : '' }}" id="navOptical">
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('olt-management') ? 'active' : '' }}" href="{{ route('olt-management') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('OLT Management') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('onu-management') ? 'active' : '' }}" href="{{ route('onu-management') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Optical / ONU') }}</span></div>
                        </a>
                    </li>
                </ul>
            </li>
            @endif

            <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $openNetwork ? '' : 'collapsed' }} {{ $openNetwork ? 'active' : '' }}"
                    href="#navNetwork" role="button" data-bs-toggle="collapse"
                    aria-expanded="{{ $openNetwork ? 'true' : 'false' }}" aria-controls="navNetwork">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-router-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Network') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $openNetwork ? 'show' : '' }}" id="navNetwork">
                <li class="nav-item">
                <a wire:navigate.hover class="nav-link {{ $navIs('mikrotik-sync') ? 'active' : '' }}" href="{{ route('mikrotik-sync') }}">
                    <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('MikroTik Sync') }}</span></div>
                </a>
                </li>
                @if(hasAccess(['Super Admin'], ['olt-management', 'onu-management', 'mikrotik-setup']))
                <li class="nav-item">
                    <a wire:navigate.hover class="nav-link {{ $navIs('bandwidth-hub') ? 'active' : '' }}" href="{{ route('bandwidth-hub') }}">
                        <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Bandwidth') }}</span></div>
                    </a>
                </li>
                @endif
                <li class="nav-item">
                <a wire:navigate.hover class="nav-link {{ $navIs('mikrotik-hotspot-manager') ? 'active' : '' }}" href="{{ route('mikrotik-hotspot-manager') }}">
                    <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Hotspot') }}</span></div>
                </a>
                </li>

                <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $openMtSetup ? '' : 'collapsed' }}" href="#mikrotikSetup" role="button"
                    data-bs-toggle="collapse" aria-expanded="{{ $openMtSetup ? 'true' : 'false' }}" aria-controls="mikrotikSetup">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-text ps-1">{{ __('Mikrotik Setup') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $openMtSetup ? 'show' : '' }}" id="mikrotikSetup">
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-ip-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('IP & Pool') }}</span>
                            </div>
                        </a></li>
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-pppoe-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('PPPoE Server') }}</span></div>
                        </a></li>
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-radius-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('RADIUS') }}</span>
                            </div>
                        </a></li>
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-firewall-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Firewall') }}</span>
                            </div>
                        </a></li>
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-walled-garden') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Walled Garden') }}</span><span class="badge rounded-pill ms-2 badge-subtle-info">{{ __('New') }}</span>
                            </div>
                        </a></li>
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-queue-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Queues') }}</span>
                            </div>
                        </a></li>
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-vpn-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('VPN Server') }}</span>
                            </div>
                        </a></li>
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-interface-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Interfaces & VLAN') }}</span></div>
                        </a></li>
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-traffic-monitor') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Live Traffic') }}</span><span class="badge rounded-pill ms-2 badge-subtle-success">{{ __('New') }}</span>
                            </div>
                        </a></li>
                    <li class="nav-item"><a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-backup-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Backup & Restore') }}</span><span
                                    class="badge rounded-pill ms-2 badge-subtle-primary">{{ __('Admin') }}</span></div>
                        </a></li>
                </ul>
                </li>
                </ul>
            </li>

            <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $openSupport ? '' : 'collapsed' }} {{ $openSupport ? 'active' : '' }}"
                    href="#navSupport" role="button" data-bs-toggle="collapse"
                    aria-expanded="{{ $openSupport ? 'true' : 'false' }}" aria-controls="navSupport">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-headset"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Support') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $openSupport ? 'show' : '' }}" id="navSupport">
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('admin-tickets') ? 'active' : '' }}" href="{{ route('admin-tickets') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Tickets') }}</span></div>
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $openReports ? '' : 'collapsed' }} {{ $openReports ? 'active' : '' }}"
                    href="#navReports" role="button" data-bs-toggle="collapse"
                    aria-expanded="{{ $openReports ? 'true' : 'false' }}" aria-controls="navReports">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-bar-chart-line"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Reports') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $openReports ? 'show' : '' }}" id="navReports">
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('collection-report.*') ? 'active' : '' }}" href="{{ route('collection-report.index') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Collections') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('customer-summary') ? 'active' : '' }}" href="{{ route('customer-summary') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Client Summary') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('dis-report') ? 'active' : '' }}" href="{{ route('dis-report') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('BTRC DIS') }}</span></div>
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $openFinance ? '' : 'collapsed' }} {{ $openFinance ? 'active' : '' }}"
                    href="#navFinance" role="button" data-bs-toggle="collapse"
                    aria-expanded="{{ $openFinance ? 'true' : 'false' }}" aria-controls="navFinance">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-wallet2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Finance') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $openFinance ? 'show' : '' }}" id="navFinance">
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('admin.expenses') ? 'active' : '' }}" href="{{ route('admin.expenses') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Expense Management') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('admin.profit-summary') ? 'active' : '' }}" href="{{ route('admin.profit-summary') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Profit & Loss') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('accounts-hub') ? 'active' : '' }}" href="{{ route('accounts-hub') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Accounts Hub') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover class="nav-link {{ $navIs('inventory-hub') ? 'active' : '' }}" href="{{ route('inventory-hub') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Inventory') }}</span></div>
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $openSystem ? '' : 'collapsed' }} {{ $openSystem ? 'active' : '' }}"
                    href="#navSystem" role="button" data-bs-toggle="collapse"
                    aria-expanded="{{ $openSystem ? 'true' : 'false' }}" aria-controls="navSystem">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-gear"></i></span>
                        <span class="nav-link-text ps-1">{{ __('System') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $openSystem ? 'show' : '' }}" id="navSystem">
                <li class="nav-item">
                <a wire:navigate.hover class="nav-link {{ $navIs('hr-hub') ? 'active' : '' }}" href="{{ route('hr-hub') }}">
                    <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('HR Hub') }}</span></div>
                </a>
                </li>
                <li class="nav-item">
                <a wire:navigate.hover class="nav-link {{ $navIs('admin.purchase-requests') ? 'active' : '' }}"
                    href="{{ route('admin.purchase-requests') }}">
                    <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Purchase Requests') }}</span></div>
                </a>
                </li>
                @if (canManageMasterSetup())
                <li class="nav-item">
                <a wire:navigate.hover class="nav-link {{ $navIs('site-settings') ? 'active' : '' }}"
                    href="{{ route('site-settings') }}">
                    <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Master Setup') }}</span></div>
                </a>
                </li>
                @endif
                @if (canSellSaas())
                <li class="nav-item">
                <a wire:navigate.hover class="nav-link {{ $navIs('admin.saas-operators') ? 'active' : '' }}"
                    href="{{ route('admin.saas-operators') }}">
                    <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Sell ISP Admin') }}</span></div>
                </a>
                </li>
                @endif
                <li class="nav-item">
                <a wire:navigate.hover class="nav-link {{ $navIs('admin.resellers.*') ? 'active' : '' }}"
                    href="{{ route('admin.resellers.index') }}">
                    <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Reseller Setup') }}</span></div>
                </a>
                </li>
                <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $navIs('admin.activity-logs', 'admin.login-logs', 'admin.system-logs', 'mikrotik-log-viewer', 'admin.vouchers') ? '' : 'collapsed' }}" href="#logsSetupDropdown" role="button"
                    data-bs-toggle="collapse" aria-expanded="{{ $navIs('admin.activity-logs', 'admin.login-logs', 'admin.system-logs', 'mikrotik-log-viewer', 'admin.vouchers') ? 'true' : 'false' }}" aria-controls="logsSetupDropdown">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-text ps-1">{{ __('Logs & Audits') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $navIs('admin.activity-logs', 'admin.login-logs', 'admin.system-logs', 'mikrotik-log-viewer', 'admin.vouchers') ? 'show' : '' }}" id="logsSetupDropdown">
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('admin.activity-logs') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Activity Logs') }}</span></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('admin.login-logs') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Login Logs') }}</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('admin.system-logs') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('System Logs') }}</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('mikrotik-log-viewer') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Router Logs') }}</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('admin.vouchers') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Reseller Vouchers') }}</span></div>
                        </a>
                    </li>
                </ul>
                </li>

                <li class="nav-item">
                <a wire:navigate.hover class="nav-link {{ $navIs('admin.reviews') ? 'active' : '' }}" href="{{ route('admin.reviews') }}">
                    <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Customer Reviews') }}</span></div>
                </a>
                </li>
                <li class="nav-item">
                <a class="nav-link dropdown-indicator {{ $navIs('sms-setup', 'automatic-processes', 'sms-bridge.*') ? '' : 'collapsed' }}" href="#smsSetupDropdown" role="button"
                    data-bs-toggle="collapse" aria-expanded="{{ $navIs('sms-setup', 'automatic-processes', 'sms-bridge.*') ? 'true' : 'false' }}" aria-controls="smsSetupDropdown">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-text ps-1">{{ __('SMS Management') }}</span>
                    </div>
                </a>
                <ul class="nav collapse {{ $navIs('sms-setup', 'automatic-processes', 'sms-bridge.*') ? 'show' : '' }}" id="smsSetupDropdown">
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('sms-setup') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('SMS Setup') }}</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('automatic-processes') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('Automatic Processes') }}</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('sms-bridge.index') }}">
                            <div class="d-flex align-items-center"><span class="nav-link-text ps-1">{{ __('SMS Bridge') }}</span>
                            </div>
                        </a>
                    </li>
                </ul>
                </li>
                </ul>
            </li>
        </ul>
    @endif
@else
    <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
        <li class="nav-item">
            <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                <div class="col-auto navbar-vertical-label">{{ __('Account') }}</div>
                <div class="col ps-0">
                    <hr class="mb-0 navbar-vertical-divider" />
                </div>
            </div>
            <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('profile.show') }}"
                role="button">
                <div class="d-flex align-items-center">
                    <span class="nav-link-icon"><i class="bi bi-person-fill"></i></span>
                    <span class="nav-link-text ps-1">{{ __('Profile') }}</span>
                </div>
            </a>
        </li>
    </ul>
@endif
