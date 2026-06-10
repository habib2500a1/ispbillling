@php
    $collectionJsVersion = file_exists(public_path('js/billing-collection-v3.js'))
        ? (int) filemtime(public_path('js/billing-collection-v3.js'))
        : 1;
@endphp

{!! \App\Support\BillingStyles::navigatedScript() !!}
<script src="{{ asset('js/billing-collection-v3.js') }}?v={{ $collectionJsVersion }}" defer></script>

<x-filament-panels::page>
    <div class="isp-collection-desk space-y-6" wire:loading.class="isp-collection-desk--loading">
        <header class="isp-collection-hero">
            <div>
                <p class="isp-collection-hero__eyebrow">Billing operations</p>
                <h2 class="isp-collection-hero__title">Bill collection desk</h2>
                <p class="isp-collection-hero__sub">Search subscriber → check current due → collect payment in one flow.</p>
            </div>
            <div class="isp-collection-hero__stats">
                <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('due') }}" class="isp-collection-stat isp-collection-stat--due">
                    <span class="isp-collection-stat__label">Current due</span>
                    <strong>{{ number_format($deskStats['due_clients']) }}</strong>
                    <span class="isp-collection-stat__hint">BDT {{ number_format($deskStats['total_due'], 0) }}</span>
                </a>
                <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('paid') }}" class="isp-collection-stat isp-collection-stat--paid">
                    <span class="isp-collection-stat__label">Paid up</span>
                    <strong>{{ number_format($deskStats['paid_clients']) }}</strong>
                </a>
            </div>
        </header>

        @php
            $collectionDeskUrl = \App\Filament\Pages\BillCollectionDesk::getUrl();
            $collectionSearchQuery = static function (string $term, string $filter = 'all', ?int $customerId = null) use ($collectionDeskUrl): string {
                $params = [];

                if (trim($term) !== '') {
                    $params['q'] = trim($term);
                }

                if ($filter !== 'all') {
                    $params['filter'] = $filter;
                }

                if ($customerId !== null && $customerId > 0) {
                    $params['customer'] = $customerId;
                }

                return $params === []
                    ? $collectionDeskUrl
                    : $collectionDeskUrl.'?'.http_build_query($params);
            };
        @endphp

        <div class="isp-collection-search-wrap">
            <label for="collection-search" class="isp-collection-search-label">Find subscriber</label>
            <form
                method="GET"
                action="{{ $collectionDeskUrl }}"
                id="collection-search-form"
                class="isp-collection-search-row"
                data-navigate="false"
            >
                <input type="hidden" name="filter" id="collection-search-filter" value="{{ $searchFilter }}" />
                <div class="isp-collection-search-field">
                    <svg class="isp-collection-search-field__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                    </svg>
                    <input
                        id="collection-search"
                        name="q"
                        type="search"
                        value="{{ $search }}"
                        placeholder="ID, phone, name, PPP user, invoice #, zone…"
                        class="isp-collection-search-input"
                        autocomplete="off"
                        autofocus
                        maxlength="200"
                    />
                </div>
                <button type="submit" class="isp-collection-search-btn">Search</button>
                @if ($search !== '')
                    <a href="{{ $collectionDeskUrl }}" class="isp-collection-search-clear" data-navigate="false">Clear</a>
                @endif
            </form>

            @if ($search !== '')
                <p class="isp-collection-search-active" role="status">
                    Showing results for “{{ $search }}”
                    @if ($searchFilter === 'due')
                        · current due only
                    @elseif ($searchFilter === 'paid')
                        · paid only
                    @endif
                </p>
            @endif

            <div class="isp-collection-filter-nav" role="group" aria-label="Bill filter">
                @foreach (['all' => 'All', 'due' => 'Current due', 'paid' => 'Paid'] as $key => $label)
                    <a
                        href="{{ $collectionSearchQuery($search, $key) }}"
                        data-navigate="false"
                        @class(['isp-collection-filter-btn', 'isp-collection-filter-btn--active' => $searchFilter === $key])
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="isp-collection-recent" data-collection-recent hidden>
                <p class="isp-collection-recent__label">Recent searches</p>
                <div class="isp-collection-recent__chips" data-collection-recent-chips></div>
            </div>

            <p class="isp-collection-search-hint">
                Search by code, mobile, name, PPP/RADIUS user, NID, invoice #, zone, or address.
                <span class="isp-collection-search-hint__links">
                    <a href="{{ \App\Filament\Pages\CollectorCashHub::getUrl() }}">Collector settlement</a> ·
                    <a href="{{ \App\Filament\Pages\CollectionBalanceStatement::getUrl() }}">Balance statement</a> ·
                    <a href="{{ \App\Filament\Pages\CollectionDeskReport::getUrl() }}">Collection report</a>
                </span>
            </p>
        </div>

        @if ($search !== '' && $results->isEmpty())
            <div class="isp-collection-empty rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-gray-600">
                <p class="font-medium text-gray-700 dark:text-gray-300">No subscriber found</p>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($searchFilter === 'due')
                        No match with current due. Try filter <a href="{{ $collectionSearchQuery($search, 'all') }}" class="font-semibold text-primary-600 underline" data-navigate="false">All</a>.
                    @elseif ($searchFilter === 'paid')
                        No paid subscriber matched. Try filter <a href="{{ $collectionSearchQuery($search, 'all') }}" class="font-semibold text-primary-600 underline" data-navigate="false">All</a>.
                    @else
                        Try phone number, customer ID, or PPP username.
                    @endif
                </p>
            </div>
        @endif

        @if ($results->isNotEmpty())
            <div class="isp-collection-results {{ $selectedCustomer ? 'isp-collection-results--with-panel' : '' }}">
                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                    {{ $results->count() }} result(s)
                    @if ($searchFilter === 'due')
                        · current due only
                    @elseif ($searchFilter === 'paid')
                        · paid only
                    @else
                        · due first · tap to collect or recharge
                    @endif
                </p>
                <ul class="space-y-2 {{ $selectedCustomer ? 'max-h-48 overflow-y-auto' : '' }}">
                    @foreach ($results as $row)
                        @php
                            $hasDue = ($row['balance_due'] ?? 0) > 0.009;
                            $billStatus = match ($row['billing_payment_state'] ?? 'unpaid') {
                                'paid' => 'Paid',
                                'partial' => 'Partial',
                                default => 'Due',
                            };
                        @endphp
                        <li>
                            <a
                                href="{{ $collectionSearchQuery($search, $searchFilter, (int) $row['id']) }}#isp-collection-panel"
                                data-navigate="false"
                                class="isp-collection-result-card block w-full text-left no-underline {{ (int) $selectedCustomerId === (int) $row['id'] ? 'ring-2 ring-primary-500 dark:ring-primary-400' : '' }}"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $row['name'] }}</p>
                                            <span class="font-mono text-xs text-violet-600 dark:text-violet-400">#{{ $row['customer_code'] }}</span>
                                            <span @class([
                                                'isp-collection-status-pill',
                                                'isp-collection-status-pill--paid' => ! $hasDue,
                                                'isp-collection-status-pill--partial' => $hasDue && $billStatus === 'Partial',
                                                'isp-collection-status-pill--due' => $hasDue && $billStatus !== 'Partial',
                                            ])>{{ $billStatus }}</span>
                                        </div>
                                        @if (! empty($row['same_name_hint']))
                                            <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">{{ $row['same_name_hint'] }}</p>
                                        @endif
                                        <div class="mt-1 grid gap-0.5 text-xs text-gray-600 dark:text-gray-400 sm:grid-cols-2">
                                            <span><strong class="text-gray-500">Phone:</strong> {{ $row['phone'] ?: '—' }}</span>
                                            <span><strong class="text-gray-500">PPP:</strong> <span class="font-mono">{{ $row['username'] }}</span></span>
                                            @if ($row['zone'])
                                                <span><strong class="text-gray-500">Zone:</strong> {{ $row['zone'] }}</span>
                                            @endif
                                            @if ($row['package'])
                                                <span><strong class="text-gray-500">Package:</strong> {{ $row['package'] }}</span>
                                            @endif
                                            <span class="sm:col-span-2"><strong class="text-gray-500">Address:</strong> {{ $row['address'] }}</span>
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right" wire:key="search-due-{{ $row['id'] }}-{{ $row['balance_due'] }}">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current due</p>
                                        <p class="text-lg font-bold {{ $hasDue ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                            {{ number_format((float) ($row['balance_due'] ?? 0), 0) }}
                                            <span class="text-xs font-semibold">BDT</span>
                                        </p>
                                        @if ($row['open_invoices'] > 0)
                                            <p class="text-xs text-gray-500">{{ $row['open_invoices'] }} open bill(s)</p>
                                        @elseif (! $hasDue)
                                            <p class="text-xs font-semibold text-sky-600 dark:text-sky-400">Tap to recharge</p>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($selectedCustomer)
            @php
                $conn = $selectedCustomer['connection'] ?? [];
                $payable = $this->payableAmount();
                $received = $this->receivedAmountNumeric();
                $discountPreview = $this->previewCollectionDiscountBdt();
                $balanceAfter = $this->balanceDueAfterCollection();
            @endphp
            <div class="isp-collection-panel" id="isp-collection-panel">
                <nav class="isp-collection-tabs" aria-label="Subscriber tabs">
                    <button type="button" wire:click="setTab('collect')" @class(['isp-collection-tabs__btn', 'isp-collection-tabs__btn--active' => $activeTab === 'collect'])>Collect payment</button>
                    <button type="button" wire:click="setTab('bills')" @class(['isp-collection-tabs__btn', 'isp-collection-tabs__btn--active' => $activeTab === 'bills'])>Bills ({{ count($selectedCustomer['bill_history'] ?? []) }})</button>
                    <button type="button" wire:click="setTab('history')" @class(['isp-collection-tabs__btn', 'isp-collection-tabs__btn--active' => $activeTab === 'history'])>{{ $this->collectionHistoryTabLabel() }}</button>
                </nav>

                @if ($activeTab === 'collect')
                    <form wire:submit="collectPayment" class="isp-collection-sheet">
                        <header class="isp-collection-sheet__header">
                            <div>
                                <p class="isp-collection-sheet__eyebrow">{{ $this->isRechargeMode() ? 'Recharge / advance' : 'Bill collection' }}</p>
                                <h3 class="isp-collection-sheet__title">{{ $selectedCustomer['name'] }}</h3>
                                <p class="isp-collection-sheet__meta">
                                    <span class="font-mono">#{{ $selectedCustomer['customer_code'] }}</span>
                                    · PPP <span class="font-mono">{{ $selectedCustomer['username'] }}</span>
                                    · <span @class(['font-semibold', 'text-emerald-600' => ($conn['online'] ?? false), 'text-gray-500' => ! ($conn['online'] ?? false)])>{{ ($conn['online'] ?? false) ? 'Online' : 'Offline' }}</span>
                                </p>
                            </div>
                            <a href="{{ $collectionSearchQuery($search, $searchFilter) }}" data-navigate="false" class="isp-collection-sheet__change">Change subscriber</a>
                        </header>

                        <div class="isp-collection-mode-nav" role="group" aria-label="Collection mode">
                            <button
                                type="button"
                                wire:click="setCollectionMode('bill')"
                                @class(['isp-collection-mode-btn', 'isp-collection-mode-btn--active' => ! $this->isRechargeMode()])
                            >
                                Collect due
                            </button>
                            <button
                                type="button"
                                wire:click="setCollectionMode('advance')"
                                @class(['isp-collection-mode-btn', 'isp-collection-mode-btn--active' => $this->isRechargeMode()])
                            >
                                Recharge (advance)
                            </button>
                        </div>

                        @if ($this->isRechargeMode())
                            <div class="isp-collection-recharge">
                                <p class="isp-collection-recharge__label">Quick recharge</p>
                                <div class="isp-collection-recharge__chips">
                                    @forelse ($this->getPrepayQuickOptions() as $option)
                                        <button
                                            type="button"
                                            wire:click="applyRechargeMonths({{ (int) $option['months'] }})"
                                            @class([
                                                'isp-collection-recharge__chip',
                                                'isp-collection-recharge__chip--active' => (int) $advancePrepayMonths === (int) $option['months'],
                                            ])
                                        >
                                            {{ (int) $option['months'] }} mo · {{ number_format((float) $option['prepay_amount'], 0) }} BDT
                                        </button>
                                    @empty
                                        <button
                                            type="button"
                                            class="isp-collection-recharge__chip isp-collection-recharge__chip--active"
                                            disabled
                                        >
                                            Enter amount below
                                        </button>
                                    @endforelse
                                </div>
                                <p class="isp-collection-recharge__hint">
                                    Paid up client — advance goes to wallet &amp; extends service (prepaid clients get forward bills).
                                    Wallet: {{ number_format((float) ($selectedCustomer['account_balance'] ?? 0), 2) }} BDT
                                </p>
                            </div>
                        @endif

                        <div class="isp-collection-sheet__grid">
                            <label class="isp-collection-field">
                                <span class="isp-collection-field__label">Received date</span>
                                <input type="text" class="isp-collection-field__input isp-collection-field__input--readonly" value="{{ now()->format('d-m-Y') }}" readonly />
                            </label>
                            <label class="isp-collection-field">
                                <span class="isp-collection-field__label">User name</span>
                                <input type="text" class="isp-collection-field__input isp-collection-field__input--readonly font-mono" value="{{ $selectedCustomer['username'] }}" readonly />
                            </label>
                            <label class="isp-collection-field">
                                <span class="isp-collection-field__label">Client code</span>
                                <input type="text" class="isp-collection-field__input isp-collection-field__input--readonly font-mono" value="{{ $selectedCustomer['customer_code'] }}" readonly />
                            </label>
                            <label class="isp-collection-field">
                                <span class="isp-collection-field__label">Mobile no.</span>
                                <input type="text" class="isp-collection-field__input isp-collection-field__input--readonly" value="{{ $selectedCustomer['phone'] ?: '—' }}" readonly />
                            </label>
                            <label class="isp-collection-field">
                                <span class="isp-collection-field__label">Package</span>
                                <input type="text" class="isp-collection-field__input isp-collection-field__input--readonly" value="{{ $selectedCustomer['package'] ?: '—' }}" readonly />
                            </label>
                            <label class="isp-collection-field">
                                <span class="isp-collection-field__label">Receive from</span>
                                <input type="text" wire:model="receiveFrom" class="isp-collection-field__input" placeholder="Who paid?" />
                            </label>
                            <label class="isp-collection-field">
                                <span class="isp-collection-field__label">Monthly bill</span>
                                <input type="text" class="isp-collection-field__input isp-collection-field__input--readonly" value="{{ $selectedCustomer['monthly_bill'] !== null ? number_format((float) $selectedCustomer['monthly_bill'], 2) : '—' }}" readonly />
                            </label>
                            <label class="isp-collection-field" wire:key="due-field-{{ $selectedCustomerId }}-{{ $selectedCustomer['balance_due'] }}-{{ $collectionMode }}">
                                <span class="isp-collection-field__label">{{ $this->isRechargeMode() ? 'Current due' : 'Due amount' }}</span>
                                <input
                                    type="text"
                                    class="isp-collection-field__input isp-collection-field__input--readonly @if(! $this->isRechargeMode() && $payable > 0.009) isp-collection-field__input--due @endif"
                                    value="{{ number_format($this->isRechargeMode() ? (float) ($selectedCustomer['balance_due'] ?? 0) : $payable, 2) }}"
                                    readonly
                                />
                            </label>
                            <label class="isp-collection-field isp-collection-field--span">
                                <span class="isp-collection-field__label">Received by *</span>
                                @if ($this->canPickCollector() && count($this->getCollectorStaffOptions()) > 0)
                                    <select wire:model.live="collectorUserId" class="isp-collection-field__input" required>
                                        @foreach ($this->getCollectorStaffOptions() as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" class="isp-collection-field__input isp-collection-field__input--readonly" value="{{ auth()->user()?->name }}" readonly />
                                @endif
                                @error('collectorUserId')
                                    <span class="isp-collection-field__error">{{ $message }}</span>
                                @enderror
                            </label>
                            <label class="isp-collection-field">
                                <span class="isp-collection-field__label">Payment method</span>
                                <select wire:model="method" class="isp-collection-field__input">
                                    @foreach ($this->getMethodOptions() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        @if (! $this->isRechargeMode() && ! empty($selectedCustomer['invoices']))
                            <div class="isp-collection-sheet__invoice">
                                <label class="isp-collection-field isp-collection-field--full">
                                    <span class="isp-collection-field__label">Apply to invoice</span>
                                    <select wire:model.live="invoiceId" class="isp-collection-field__input">
                                        <option value="">— General payment (wallet) —</option>
                                        @foreach ($selectedCustomer['invoices'] as $inv)
                                            <option value="{{ $inv['id'] }}">
                                                {{ $inv['invoice_number'] }} · due {{ $inv['due_date'] }} · {{ number_format($inv['balance_due'], 2) }} BDT
                                                @if ($inv['is_overdue']) (overdue) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        @endif

                        @if (($selectedCustomer['account_balance'] ?? 0) > 0 && $invoiceId && $this->selectedInvoiceBalanceDue() !== null)
                            <label class="isp-collection-sheet__wallet">
                                <input type="checkbox" wire:model.live="useCustomerWallet" />
                                <span>Apply wallet first ({{ number_format($selectedCustomer['account_balance'], 2) }} BDT available)</span>
                            </label>
                        @endif

                        <div class="isp-collection-summary">
                            <table class="isp-collection-summary__table">
                                <thead>
                                    <tr>
                                        <th>Details</th>
                                        <th>Amount info</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ $this->isRechargeMode() ? 'Recharge amount' : 'Payable amount' }}</td>
                                        <td class="isp-collection-summary__amount">{{ number_format($this->isRechargeMode() ? $received : $payable, 2) }}</td>
                                    </tr>
                                    <tr @if($this->isRechargeMode()) hidden @endif>
                                        <td>Discount</td>
                                        <td>
                                            @if ($this->canApplyCollectionDiscount() && count($this->getCollectionDiscountPresetOptions()) > 0)
                                                <select wire:model.live="collectionDiscountPreset" class="isp-collection-field__input isp-collection-field__input--compact">
                                                    @foreach ($this->getCollectionDiscountPresetOptions() as $id => $label)
                                                        <option value="{{ $id }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @if ($this->collectionDiscountAllowsCustom() && $collectionDiscountPreset === 'none')
                                                    <input type="number" step="0.01" min="0" wire:model.live="collectionDiscountCustom" class="isp-collection-field__input isp-collection-field__input--compact mt-1" placeholder="Custom BDT" />
                                                @endif
                                            @else
                                                <span class="isp-collection-summary__muted">0.00</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>{{ $this->isRechargeMode() ? 'Advance received' : 'Received amount' }}</td>
                                        <td>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                @if(! $this->isRechargeMode() && $this->selectedInvoiceBalanceDue()) max="{{ $this->selectedInvoiceBalanceDue() }}" @endif
                                                wire:model.live="amount"
                                                class="isp-collection-field__input isp-collection-field__input--amount"
                                                required
                                            />
                                            @error('amount')
                                                <span class="isp-collection-field__error">{{ $message }}</span>
                                            @enderror
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Total received amount</td>
                                        <td class="isp-collection-summary__amount isp-collection-summary__amount--strong" wire:key="total-received-{{ $amount }}-{{ $discountPreview }}">
                                            {{ number_format($received, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Receipt / transaction no.</td>
                                        <td>
                                            <input type="text" wire:model="reference" class="isp-collection-field__input isp-collection-field__input--compact" placeholder="Optional TRX / receipt #" />
                                        </td>
                                    </tr>
                                    @if (! $this->isRechargeMode())
                                        <tr>
                                            <td>Balance due</td>
                                            <td class="isp-collection-summary__amount @if($balanceAfter > 0.009) isp-collection-summary__amount--due @else isp-collection-summary__amount--paid @endif" wire:key="balance-after-{{ $amount }}-{{ $discountPreview }}-{{ $payable }}">
                                                {{ number_format($balanceAfter, 2) }}
                                            </td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td>
                                            Remarks / note
                                            @if ($this->notesRequiredForCollection())
                                                <span class="text-rose-600">*</span>
                                            @endif
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                wire:model="notes"
                                                @class(['isp-collection-field__input isp-collection-field__input--compact', 'ring-2 ring-amber-400' => $this->notesRequiredForCollection()])
                                                placeholder="Partial pay reason, next due date…"
                                                @if($this->notesRequiredForCollection()) required @endif
                                            />
                                            @error('notes')
                                                <span class="isp-collection-field__error">{{ $message }}</span>
                                            @enderror
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if ($setNextBillingDate)
                            <div class="isp-collection-sheet__renewal">
                                <label class="isp-collection-field isp-collection-field--full">
                                    <span class="isp-collection-field__label">Renew / valid until (on full pay)</span>
                                    <select wire:model.live="renewalPolicy" class="isp-collection-field__input">
                                        @foreach ($this->getRenewalPolicyOptions() as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                @if ($selectedCustomer['service_expires_at'] ?? null)
                                    <p class="isp-collection-sheet__renewal-hint">Current valid until: <strong>{{ $selectedCustomer['service_expires_at'] }}</strong></p>
                                @endif
                                <p class="isp-collection-sheet__renewal-hint">{{ $this->renewalPolicyHint() }}</p>
                            </div>
                        @endif

                        <footer class="isp-collection-sheet__footer">
                            <div class="isp-collection-sheet__options">
                                <label class="isp-collection-sheet__check">
                                    <input type="checkbox" wire:model.live="setNextBillingDate" />
                                    <span>Set next billing date?</span>
                                </label>
                                <label class="isp-collection-sheet__check">
                                    <input type="checkbox" wire:model="sendSms" />
                                    <span>Send SMS</span>
                                </label>
                                <button type="button" onclick="captureDeskGps()" class="isp-collection-sheet__gps">
                                    GPS {{ $latitude !== null ? '✓' : 'capture' }}
                                </button>
                            </div>
                            <div class="isp-collection-sheet__actions">
                                <a href="{{ $collectionSearchQuery($search, $searchFilter) }}" data-navigate="false" class="isp-collection-sheet__cancel">Cancel</a>
                                <button type="submit" class="isp-collection-sheet__submit" wire:loading.attr="disabled" wire:target="collectPayment">
                                    <span wire:loading.remove wire:target="collectPayment">{{ $this->isRechargeMode() ? 'Submit recharge' : 'Submit collection' }}</span>
                                    <span wire:loading wire:target="collectPayment">Saving…</span>
                                </button>
                            </div>
                        </footer>
                    </form>
                @endif

                @if ($activeTab === 'bills')
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 text-left">Invoice</th>
                                    <th class="px-3 py-2 text-left">Month</th>
                                    <th class="px-3 py-2 text-left">Due</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th class="px-3 py-2 text-right">Paid</th>
                                    <th class="px-3 py-2 text-right">Balance</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse ($selectedCustomer['bill_history'] ?? [] as $bill)
                                    <tr wire:key="bill-{{ $bill['id'] }}">
                                        <td class="px-3 py-2 font-mono font-semibold">{{ $bill['invoice_number'] }}</td>
                                        <td class="px-3 py-2 text-xs">{{ $bill['period_label'] ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $bill['due_date'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($bill['total'], 2) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($bill['amount_paid'], 2) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold {{ $bill['balance_due'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                            {{ number_format($bill['balance_due'], 2) }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="rounded px-1.5 py-0.5 text-xs font-semibold uppercase {{ $bill['is_overdue'] ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-600' }}">
                                                {{ $bill['status'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <a href="{{ $bill['edit_url'] }}" target="_blank" class="text-xs font-semibold text-violet-600 hover:underline">Edit</a>
                                            <span class="text-gray-300">·</span>
                                            <a href="{{ $bill['pdf_url'] }}" target="_blank" class="text-xs font-semibold text-gray-600 hover:underline">PDF</a>
                                            <span class="text-gray-300">·</span>
                                            <button type="button" wire:click="recalculateInvoice({{ $bill['id'] }})" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Recalc</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-3 py-8 text-center text-gray-500">No invoices for this subscriber.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500">Wrong bill amount? Use <strong>Edit</strong> to change line items, dates, or status. Use <strong>Recalc</strong> to refresh totals after edits.</p>
                @endif

                @if ($activeTab === 'history')
                    @php
                        $sync = $selectedCustomer['collection_sync'] ?? [];
                        $localOnly = (int) ($sync['local_only_count'] ?? 0);
                    @endphp
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase text-gray-500">Show</span>
                        <button type="button" wire:click="$set('collectionHistoryFilter', 'legacy_portal')" @class([
                            'rounded-lg px-3 py-1.5 text-xs font-semibold',
                            'bg-primary-600 text-white' => $collectionHistoryFilter === 'legacy_portal',
                            'bg-gray-100 text-gray-700 dark:bg-gray-800' => $collectionHistoryFilter !== 'legacy_portal',
                        ])>{{ \App\Support\BillingPortalLabel::collectionFilter() }}</button>
                        <button type="button" wire:click="$set('collectionHistoryFilter', 'all')" @class([
                            'rounded-lg px-3 py-1.5 text-xs font-semibold',
                            'bg-primary-600 text-white' => $collectionHistoryFilter === 'all',
                            'bg-gray-100 text-gray-700 dark:bg-gray-800' => $collectionHistoryFilter !== 'all',
                        ])>All in this system</button>
                    </div>
                    @if (($sync['show_legacy_portal_hint'] ?? false) && $collectionHistoryFilter === 'all' && $localOnly > 0)
                        <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                            <strong>{{ $localOnly }}</strong> collection(s) were entered on this desk only — not in the online billing portal.
                            Use <strong>{{ \App\Support\BillingPortalLabel::collectionFilter() }}</strong> to match the old portal.
                        </p>
                    @endif
                    @if ($editingPaymentId)
                        <form wire:submit="savePaymentCorrection" class="mb-4 max-w-2xl space-y-3 rounded-xl border border-amber-300 bg-amber-50/80 p-4 dark:border-amber-700 dark:bg-amber-950/30">
                            <p class="text-sm font-bold text-amber-900 dark:text-amber-200">Correct wrong collection</p>
                            <p class="text-xs text-amber-800 dark:text-amber-300">Reverses the old allocation and applies again. Logged under your user account.</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase text-gray-600">Amount (BDT)</label>
                                    <input type="number" step="0.01" min="0.01" wire:model="editPaymentAmount" class="isp-collection-input w-full" required />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase text-gray-600">Apply to invoice</label>
                                    <select wire:model="editPaymentInvoiceId" class="isp-collection-select w-full">
                                        <option value="">— Wallet / unallocated —</option>
                                        @foreach ($selectedCustomer['bill_history'] ?? [] as $bill)
                                            @if (in_array($bill['status'], ['open', 'partial', 'draft'], true))
                                                <option value="{{ $bill['id'] }}">
                                                    {{ $bill['invoice_number'] }} · {{ number_format($bill['balance_due'], 2) }} due
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase text-gray-600">Reference</label>
                                    <input type="text" wire:model="editPaymentReference" class="isp-collection-input w-full" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase text-gray-600">Notes</label>
                                    <input type="text" wire:model="editPaymentNotes" class="isp-collection-input w-full" />
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="isp-collection-submit">Save correction</button>
                                <button type="button" wire:click="cancelEditPayment" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            </div>
                        </form>
                    @endif

                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Receipt</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2 text-left">Method</th>
                                    <th class="px-3 py-2 text-left">Source</th>
                                    <th class="px-3 py-2 text-left">Invoice</th>
                                    <th class="px-3 py-2 text-left">Collected by</th>
                                    <th class="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse ($this->filteredCollectionHistory() as $pay)
                                    <tr wire:key="pay-{{ $pay['id'] }}" @class(['bg-amber-50/50 dark:bg-amber-950/20' => $editingPaymentId === $pay['id']])>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $pay['paid_at'] }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $pay['receipt_number'] }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($pay['amount'], 2) }}</td>
                                        <td class="px-3 py-2">{{ $pay['method'] }}</td>
                                        <td class="px-3 py-2 text-xs">
                                            <span @class([
                                                'rounded px-1.5 py-0.5 font-semibold',
                                                'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200' => $pay['is_legacy_portal_import'] ?? false,
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' => ! ($pay['is_legacy_portal_import'] ?? false),
                                            ])>{{ $pay['source_label'] ?? '—' }}</span>
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $pay['invoice_number'] ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $pay['recorded_by'] }}</td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            @if (!empty($pay['is_void']))
                                                <span class="text-xs font-semibold text-gray-400 line-through">Voided</span>
                                            @else
                                                <a href="{{ $pay['receipt_url'] }}" target="_blank" class="text-xs font-semibold text-gray-600 hover:underline">Receipt</a>
                                                @if ($pay['can_correct'])
                                                    <span class="text-gray-300">·</span>
                                                    <button type="button" wire:click="startEditPayment({{ $pay['id'] }})" class="text-xs font-semibold text-amber-700 hover:underline">Fix</button>
                                                @endif
                                                @if ($pay['can_void'] ?? false)
                                                    <span class="text-gray-300">·</span>
                                                    <button
                                                        type="button"
                                                        wire:click="voidPayment({{ $pay['id'] }})"
                                                        wire:confirm="Delete this wrong collection? Invoice and wallet balance will be adjusted back."
                                                        class="text-xs font-semibold text-red-600 hover:underline"
                                                    >Delete</button>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-3 py-8 text-center text-gray-500">
                                            @if ($collectionHistoryFilter === 'legacy_portal')
                                                No online-portal collections imported yet for this subscriber.
                                            @else
                                                No collections yet for this subscriber.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500">Wrong entry? <strong>Delete</strong> voids it and restores bill/wallet balance. <strong>Fix</strong> moves payment to another invoice. <strong>Collected by</strong> shows staff who recorded it.</p>
                @endif
            </div>
        @endif
    </div>

    @script
    <script>
        window.captureDeskGps = function () {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition((pos) => {
                $wire.setGps(pos.coords.latitude, pos.coords.longitude, Math.round(pos.coords.accuracy));
            }, () => {}, { enableHighAccuracy: true, timeout: 12000 });
        };
    </script>
    @endscript
</x-filament-panels::page>
