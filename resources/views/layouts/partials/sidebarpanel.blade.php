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
                    @can('payment-collection-invoice')
                        <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('payment-invoice') }}"
                            role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-receipt"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Payment Invoice') }}</span>
                            </div>
                        </a>
                    @endcan
                    @canany(['payment-collection-report', 'collection-list', 'without-collection-list',
                        'amount-collection'])
                        <a wire:navigate.hover wire:current="active" class="nav-link"
                            href="{{ route('collection-report.index') }}" role="button">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><i class="bi bi-bar-chart-line"></i></span>
                                <span class="nav-link-text ps-1">{{ __('Collection Report') }}</span>
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
        <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Command') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                <a class="nav-link" href="{{ route('dashboard') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-speedometer2 me-2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Dashboard') }}</span>
                    </div>
                </a>
                <a class="nav-link" href="{{ route('isp-os') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-command me-2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('ISP OS') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin-center') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-sliders2 me-2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Admin Center') }}</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Client Management') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('customers.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-people-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('All Clients') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('new-customer') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-person-fill-add"></i></span>
                        <span class="nav-link-text ps-1">{{ __('New Client') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('online-clients') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-broadcast"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Online Clients') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('package-list-setup') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-box2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Packages') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('address-setup') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-geo-alt"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Zones & Areas') }}</span>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Billing') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('payment-collection') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-cash-coin"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Collect Payment') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('collection-edit') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-pencil-square"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Collection Edit') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('payment-invoice') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-receipt"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Invoices') }}</span>
                    </div>
                </a>
                @if(hasAccess(['Super Admin'], ['billing-notices', 'payment-collection', 'sms-setup']))
                    <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('billing-notices') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-bell"></i></span>
                            <span class="nav-link-text ps-1">{{ __('Billing Notices') }}</span>
                        </div>
                    </a>
                    <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('sms-notices') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-chat-dots"></i></span>
                            <span class="nav-link-text ps-1">{{ __('SMS Notices') }}</span>
                        </div>
                    </a>
                @endif
                @if (hasAccess(['Super Admin'], ['payment-collection', 'amount-collection', 'staff-cash']))
                    <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.staff-cash') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-cash-stack"></i></span>
                            <span class="nav-link-text ps-1">{{ __('Staff Cash') }}</span>
                        </div>
                    </a>
                @endif
            </li>

            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Optical') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                @if(hasAccess(['Super Admin'], ['olt-management', 'onu-management', 'mikrotik-setup']))
                    <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('olt-management') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-hdd-network"></i></span>
                            <span class="nav-link-text ps-1">{{ __('OLT Management') }}</span>
                        </div>
                    </a>
                    <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('onu-management') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-broadcast-pin"></i></span>
                            <span class="nav-link-text ps-1">{{ __('Optical / ONU') }}</span>
                        </div>
                    </a>
                    <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('noc-overview') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-display"></i></span>
                            <span class="nav-link-text ps-1">{{ __('NOC Overview') }}</span>
                        </div>
                    </a>
                @endif
            </li>

            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Network') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-sync') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-router-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('MikroTik Sync') }}</span>
                    </div>
                </a>
                @if(hasAccess(['Super Admin'], ['olt-management', 'onu-management', 'mikrotik-setup']))
                    <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('bandwidth-hub') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><i class="bi bi-speedometer2"></i></span>
                            <span class="nav-link-text ps-1">{{ __('Bandwidth') }}</span>
                        </div>
                    </a>
                @endif
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('mikrotik-hotspot-manager') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-wifi"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Hotspot') }}</span>
                    </div>
                </a>

                <!-- Mikrotik Setup Dropdown -->
                <a class="nav-link dropdown-indicator collapsed" href="#mikrotikSetup" role="button"
                    data-bs-toggle="collapse" aria-expanded="false" aria-controls="mikrotikSetup">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon">
                            <i class="bi bi-tools"></i>
                        </span>
                        <span class="nav-link-text ps-1">{{ __('Mikrotik Setup') }}</span>
                    </div>
                </a>
                <ul class="nav collapse" id="mikrotikSetup" style="">
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

            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Support') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin-tickets') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-chat-left-text-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Tickets') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('call-desk') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-telephone"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Call Desk') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('noc-outage') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-megaphone"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Outage Broadcast') }}</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Reports') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('collection-report.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-bar-chart-line"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Collections') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('customer-summary') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-person-lines-fill"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Client Summary') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('dis-report') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-file-earmark-spreadsheet"></i></span>
                        <span class="nav-link-text ps-1">{{ __('BTRC DIS') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('ops-insights') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-lightbulb"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Ops Insights') }}</span>
                    </div>
                </a>
            </li>

            {{-- ── Finance ── --}}
            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Finance') }}</div>
                    <div class="col ps-0">
                        <hr class="mb-0 navbar-vertical-divider" />
                    </div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.expenses') }}"
                    role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-wallet2"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Expense Management') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('admin.profit-summary') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Profit & Loss') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('accounts-hub') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-journal-bookmark"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Accounts Hub') }}</span>
                    </div>
                </a>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('inventory-hub') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-box-seam"></i></span>
                        <span class="nav-link-text ps-1">{{ __('Inventory') }}</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('Workforce') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('hr-hub') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon"><i class="bi bi-people"></i></span>
                        <span class="nav-link-text ps-1">{{ __('HR Hub') }}</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <!-- label-->
                <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                    <div class="col-auto navbar-vertical-label">{{ __('System') }}</div>
                    <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                </div>
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('admin.purchase-requests') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon">
                            <i class="bi bi-cart-check-fill"></i>
                        </span>
                        <span class="nav-link-text ps-1">{{ __('Purchase Requests') }}</span>
                    </div>
                </a>
                @if (canSellSaas())
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('admin.saas-operators') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon">
                            <i class="bi bi-bag-check"></i>
                        </span>
                        <span class="nav-link-text ps-1">{{ __('Sell ISP Admin') }}</span>
                    </div>
                </a>
                @endif
                <!-- reseller setup page-->
                <a wire:navigate.hover wire:current="active" class="nav-link"
                    href="{{ route('admin.resellers.index') }}" role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon">
                            <i class="bi bi-person-badge-fill"></i>
                        </span>
                        <span class="nav-link-text ps-1">{{ __('Reseller Setup') }}</span>
                    </div>
                </a>
                <!-- Logs Management Dropdown -->
                <a class="nav-link dropdown-indicator collapsed" href="#logsSetupDropdown" role="button"
                    data-bs-toggle="collapse" aria-expanded="false" aria-controls="logsSetupDropdown">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon">
                            <i class="bi bi-journal-text"></i>
                        </span>
                        <span class="nav-link-text ps-1">{{ __('Logs & Audits') }}</span>
                    </div>
                </a>
                <ul class="nav collapse" id="logsSetupDropdown">
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

                <!-- Customer Reviews page-->
                <a wire:navigate.hover wire:current="active" class="nav-link" href="{{ route('admin.reviews') }}"
                    role="button">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon">
                            <i class="bi bi-chat-heart"></i>
                        </span>
                        <span class="nav-link-text ps-1">{{ __('Customer Reviews') }}</span>
                    </div>
                </a>
                <!-- parent pages-->
                <!-- SMS Management Dropdown -->
                <a class="nav-link dropdown-indicator collapsed" href="#smsSetupDropdown" role="button"
                    data-bs-toggle="collapse" aria-expanded="false" aria-controls="smsSetupDropdown">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon">
                            <i class="bi bi-envelope-check"></i>
                        </span>
                        <span class="nav-link-text ps-1">{{ __('SMS Management') }}</span>
                    </div>
                </a>
                <ul class="nav collapse" id="smsSetupDropdown">
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
