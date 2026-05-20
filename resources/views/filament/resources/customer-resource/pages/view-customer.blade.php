<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-subscribers',
        'fi-resource-record-' . $record->getKey(),
        'isp-client-details-page',
    ])
>
    @php
        $details = $this->getClientDetails();
        $h = $details['header'];
        $optical = $details['optical'];
        $sections = $details['sections'];
    @endphp

    <div class="isp-client-details" wire:key="client-details-{{ $record->getKey() }}">
        <div class="isp-client-details__store-note">
            সব তথ্য <strong>database-এ সংরক্ষিত</strong> — Edit client থেকে যেকোনো ফিল্ড আপডেট করুন। পুরনো সিস্টেমের extra ফিল্ড <code>meta</code> JSON-এ রাখা আছে।
        </div>

        <div class="isp-client-details__titlebar">
            <div>
                <h1 class="isp-client-details__title">Client Details</h1>
                <p class="isp-client-details__subtitle">
                    <span class="font-mono font-semibold">{{ $h['client_code'] }}</span>
                    · {{ $h['client_name'] }}
                    · <span class="font-mono text-sm opacity-90">UserName: {{ $h['username'] }}</span>
                </p>
            </div>
            <div class="isp-client-details__titlebar-actions">
                <button type="button" onclick="window.print()" class="isp-cd-btn isp-cd-btn--ghost">Print / PDF</button>
                <a href="{{ $details['urls']['collect'] }}" class="isp-cd-btn isp-cd-btn--primary">Collect payment</a>
                <a href="{{ $details['urls']['edit'] }}" class="isp-cd-btn isp-cd-btn--secondary">Edit client</a>
                <a href="{{ $details['urls']['invoices'] }}" class="isp-cd-btn isp-cd-btn--ghost">Invoices</a>
            </div>
        </div>

        <div class="isp-client-details__summary">
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Status</span>
                <span class="isp-cd-badge isp-cd-badge--{{ $h['status_color'] }}">{{ $h['status'] }}</span>
            </div>
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Type</span>
                <span class="isp-cd-badge isp-cd-badge--{{ $h['subscriber_type_color'] }}">{{ $h['subscriber_type'] }}</span>
            </div>
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">PPP</span>
                <span class="isp-cd-stat__value {{ $h['online'] ? 'text-emerald-600' : 'text-gray-500' }}">
                    {{ $h['online'] ? '● Online' : '○ Offline' }}
                </span>
                @if ($h['online'] && ! empty($h['connection_duration']))
                    <span class="text-xs text-gray-500">{{ $h['connection_duration'] }}</span>
                @endif
            </div>
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Last disconnect</span>
                <span class="isp-cd-stat__value text-sm">{{ $h['last_disconnect'] ?? '—' }}</span>
            </div>
            @if (! empty($h['portal_last_logout']) && $h['portal_last_logout'] !== '—')
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Portal logout</span>
                <span class="isp-cd-stat__value text-sm">{{ $h['portal_last_logout'] }}</span>
            </div>
            @endif
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Network</span>
                <span class="isp-cd-stat__value capitalize {{ $h['network'] === 'suspended' ? 'text-rose-600' : 'text-emerald-600' }}">{{ $h['network'] }}</span>
            </div>
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Package</span>
                <span class="isp-cd-stat__value">{{ $h['package'] }}</span>
                <span class="text-xs text-gray-500">{{ $h['speed'] }} · {{ $h['monthly_bill'] }}/mo</span>
            </div>
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Activation</span>
                <span class="isp-cd-stat__value">{{ $h['activation_date'] }}</span>
            </div>
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Expire</span>
                <span class="isp-cd-stat__value {{ $h['expired'] ? 'text-rose-600 font-bold' : '' }}">{{ $h['valid_until'] }}</span>
            </div>
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Open due</span>
                <span class="isp-cd-stat__value {{ $h['open_balance'] > 0 ? 'text-amber-700 font-bold' : '' }}">{{ number_format($h['open_balance'], 2) }} BDT</span>
            </div>
            <div class="isp-cd-stat">
                <span class="isp-cd-stat__label">Wallet</span>
                <span class="isp-cd-stat__value">{{ number_format($h['balance'], 2) }} BDT</span>
            </div>
        </div>

        <div x-data="{ tab: 'all' }" class="isp-client-details__tabs" id="onu-tab">
            @php
                $onuLease = $sections['onu_billing'] ?? [];
            @endphp
            <div class="mb-4 rounded-xl border border-violet-200 bg-violet-50/60 px-4 py-3 dark:border-violet-900/40 dark:bg-violet-950/20">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase text-violet-700 dark:text-violet-300">ONU / Optical summary</p>
                        <p class="mt-1 text-sm text-violet-950 dark:text-violet-100">
                            OLT RX/TX: <strong>{{ ($optical['linked'] ?? false) ? 'Linked — ONU / Optical tab' : 'Not linked yet' }}</strong>
                            · ISP Digital optical = OLT SNMP sync (এই panel-এ একই পদ্ধতি)
                        </p>
                    </div>
                    <button type="button" @click="tab = 'onu'" class="isp-cd-btn isp-cd-btn--secondary text-xs">Open ONU tab</button>
                </div>
                <div class="mt-2 flex flex-wrap gap-4 text-xs font-mono">
                    @foreach (array_slice($onuLease, 0, 4) as $label => $val)
                        <span><span class="text-violet-600 dark:text-violet-400">{{ $label }}:</span> {{ $val }}</span>
                    @endforeach
                </div>
            </div>
            <div class="isp-client-details__tablist" role="tablist">
                <button type="button" @click="tab = 'all'" :class="tab === 'all' && 'isp-cd-tab--active'" class="isp-cd-tab">All details</button>
                <button type="button" @click="tab = 'onu'" :class="tab === 'onu' && 'isp-cd-tab--active'" class="isp-cd-tab">ONU / Optical</button>
                <button type="button" @click="tab = 'payments'" :class="tab === 'payments' && 'isp-cd-tab--active'" class="isp-cd-tab">Payments</button>
                <button type="button" @click="tab = 'invoices'" :class="tab === 'invoices' && 'isp-cd-tab--active'" class="isp-cd-tab">Invoices</button>
                <button type="button" @click="tab = 'contacts'" :class="tab === 'contacts' && 'isp-cd-tab--active'" class="isp-cd-tab">Contacts</button>
                <button type="button" @click="tab = 'sms'" :class="tab === 'sms' && 'isp-cd-tab--active'" class="isp-cd-tab">SMS log</button>
                <button type="button" @click="tab = 'traffic'" :class="tab === 'traffic' && 'isp-cd-tab--active'" class="isp-cd-tab">Live traffic</button>
            </div>

            <div x-show="tab === 'all'" x-cloak>
                @include('filament.resources.customer-resource.partials.client-details-sections', [
                    'sections' => $sections,
                    'keys' => ['identity', 'location', 'connection', 'billing', 'fees', 'installation', 'staff', 'onu_billing', 'notifications', 'automation', 'tags', 'kyc', 'system'],
                ])
                @if (($sections['legacy_meta'] ?? []) !== [])
                    @include('filament.resources.customer-resource.partials.client-details-sections', [
                        'sections' => $sections,
                        'keys' => ['legacy_meta'],
                    ])
                @endif
            </div>

            <div x-show="tab === 'onu'" x-cloak class="isp-cd-panel isp-cd-panel--full" wire:poll.60s>
                <h2 class="isp-cd-panel__heading">ONU optical power (ISP Digital table)</h2>
                @include('filament.resources.customer-resource.partials.client-details-onu-table', [
                    'snapshot' => $optical,
                    'optical_noc_url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl(),
                    'laser_settings_url' => \App\Filament\Pages\ManageOpticalLaserSettings::canAccess()
                        ? \App\Filament\Pages\ManageOpticalLaserSettings::getUrl()
                        : null,
                ])
            </div>

            <div x-show="tab === 'payments'" x-cloak class="isp-cd-panel isp-cd-panel--full">
                <h2 class="isp-cd-panel__heading">Payment history</h2>
                @if ($details['recent_payments']->isEmpty())
                    <p class="text-sm text-gray-500">No payments recorded.</p>
                @else
                    <div class="isp-optical-power-wrap overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="isp-optical-power-table min-w-full">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Collected by</th>
                                    <th>Method</th>
                                    <th>Amount (BDT)</th>
                                    <th>Receipt</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($details['recent_payments'] as $pay)
                                    <tr>
                                        <td>{{ $pay->paid_at?->format('d-M-Y H:i') }}</td>
                                        <td>{{ $pay->recorder?->name ?? 'Online' }}</td>
                                        <td>{{ ucfirst((string) $pay->method) }}</td>
                                        <td class="font-mono text-right font-semibold">{{ number_format((float) $pay->amount, 2) }}</td>
                                        <td class="font-mono text-xs">{{ $pay->receipt_number ?? '—' }}</td>
                                        <td class="font-mono text-xs">{{ $pay->reference ?? $pay->gateway_transaction_id ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div x-show="tab === 'invoices'" x-cloak class="isp-cd-panel isp-cd-panel--full">
                <h2 class="isp-cd-panel__heading">Invoice history</h2>
                @if ($details['recent_invoices']->isEmpty())
                    <p class="text-sm text-gray-500">No invoices.</p>
                @else
                    <div class="isp-optical-power-wrap overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="isp-optical-power-table min-w-full">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Issue</th>
                                    <th>Due</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($details['recent_invoices'] as $inv)
                                    <tr>
                                        <td class="font-mono text-xs">#{{ $inv->id }}</td>
                                        <td>{{ $inv->issue_date?->format('d-M-Y') }}</td>
                                        <td>{{ $inv->due_date?->format('d-M-Y') }}</td>
                                        <td class="font-mono text-right">{{ number_format((float) $inv->total, 2) }}</td>
                                        <td class="font-mono text-right">{{ number_format((float) $inv->amount_paid, 2) }}</td>
                                        <td>{{ ucfirst((string) $inv->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div x-show="tab === 'contacts'" x-cloak class="isp-cd-panel isp-cd-panel--full">
                <h2 class="isp-cd-panel__heading">Contact numbers</h2>
                <p class="mb-2 text-sm text-gray-500">Primary phone: <strong>{{ $details['customer']->phone }}</strong></p>
                @if (count($details['contacts']) === 0)
                    <p class="text-sm text-gray-500">No extra contacts — add from Edit → Contacts tab below.</p>
                @else
                    <table class="isp-optical-power-table min-w-full">
                        <thead>
                            <tr>
                                <th>Label</th>
                                <th>Phone</th>
                                <th>Primary</th>
                                <th>WhatsApp</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($details['contacts'] as $c)
                                <tr>
                                    <td>{{ $c['label'] }}</td>
                                    <td class="font-mono">{{ $c['phone'] }}</td>
                                    <td>{{ $c['primary'] ? 'Yes' : '—' }}</td>
                                    <td>{{ $c['whatsapp'] ? 'Yes' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div x-show="tab === 'sms'" x-cloak class="isp-cd-panel isp-cd-panel--full">
                <h2 class="isp-cd-panel__heading">SMS details log</h2>
                @include('filament.resources.customer-resource.partials.client-details-sms-log', [
                    'logs' => $details['recent_sms_logs'],
                    'stats' => $details['sms_stats'],
                    'eventLabels' => $details['sms_event_labels'],
                    'fullLogUrl' => $details['urls']['sms_log'] ?? null,
                ])
            </div>

            <div x-show="tab === 'traffic'" x-cloak class="isp-cd-panel isp-cd-panel--full">
                @livewire(\App\Filament\Resources\CustomerResource\Widgets\SubscriberLiveTrafficWidget::class, ['record' => $record], key('traffic-'.$record->getKey()))
            </div>
        </div>
    </div>

    @php $relationManagers = $this->getRelationManagers(); @endphp
    @if (count($relationManagers))
        <div class="mt-6 no-print">
            <x-filament-panels::resources.relation-managers
                :active-locale="isset($activeLocale) ? $activeLocale : null"
                :active-manager="$this->activeRelationManager ?? array_key_first($relationManagers)"
                :content-tab-label="$this->getContentTabLabel()"
                :content-tab-icon="$this->getContentTabIcon()"
                :content-tab-position="$this->getContentTabPosition()"
                :managers="$relationManagers"
                :owner-record="$record"
                :page-class="static::class"
            />
        </div>
    @endif
</x-filament-panels::page>
