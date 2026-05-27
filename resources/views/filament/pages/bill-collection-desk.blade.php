<x-filament-panels::page>
    <div class="isp-collection-desk space-y-6">
        <div class="isp-collection-search-wrap">
            <label for="collection-search" class="isp-collection-search-label">Find subscriber</label>
            <div class="isp-collection-search-row">
                <input
                    id="collection-search"
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="ID, phone, name, PPP username, invoice #, address…"
                    class="isp-collection-search-input"
                    autocomplete="off"
                    autofocus
                />
                <button type="button" wire:click="runSearch" class="isp-collection-search-btn">
                    Search
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Search by customer code, mobile, name, MikroTik/RADIUS username, NID, invoice number, or address.
                Cash collections are tracked in
                <a href="{{ \App\Filament\Pages\CollectorCashHub::getUrl() }}" class="font-semibold text-teal-600 hover:underline">Collector settlement</a>
                until deposited to admin ·
                <a href="{{ \App\Filament\Pages\CollectionDeskReport::getUrl() }}" class="font-semibold text-teal-600 hover:underline">Collection report (date · user · customer)</a>
                ·
                <a href="{{ \App\Filament\Pages\BillingFundFlowReport::getUrl() }}" class="font-semibold text-violet-600 hover:underline">Bill money trail (কোথায় গেল টাকা)</a>
                ·
                <a href="{{ \App\Filament\Pages\ManagePaymentRenewalSettings::getUrl() }}" class="font-semibold text-sky-600 hover:underline">Payment renew rules</a>
            </p>
        </div>

        @if ($search !== '' && $results->isEmpty())
            <div class="isp-collection-empty rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-gray-600">
                <p class="font-medium text-gray-700 dark:text-gray-300">No subscriber found</p>
                <p class="mt-1 text-sm text-gray-500">Try phone number, customer ID, or PPP username.</p>
            </div>
        @endif

        @if ($results->isNotEmpty())
            <div class="isp-collection-results {{ $selectedCustomer ? 'isp-collection-results--with-panel' : '' }}">
                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                    {{ $results->count() }} result(s) — due shown before select · tap to collect
                </p>
                <ul class="space-y-2 {{ $selectedCustomer ? 'max-h-48 overflow-y-auto' : '' }}">
                    @foreach ($results as $row)
                        <li>
                            <button
                                type="button"
                                wire:click="selectCustomer({{ $row['id'] }})"
                                class="isp-collection-result-card w-full text-left {{ (int) $selectedCustomerId === (int) $row['id'] ? 'ring-2 ring-teal-500 dark:ring-teal-400' : '' }}"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-gray-900 dark:text-white">
                                            {{ $row['name'] }}
                                            <span class="font-mono text-sm font-normal text-violet-600 dark:text-violet-400">#{{ $row['customer_code'] }}</span>
                                        </p>
                                        <div class="mt-1 grid gap-0.5 text-xs text-gray-600 dark:text-gray-400 sm:grid-cols-2">
                                            <span><strong class="text-gray-500">Phone:</strong> {{ $row['phone'] ?: '—' }}</span>
                                            <span><strong class="text-gray-500">Username:</strong> <span class="font-mono">{{ $row['username'] }}</span></span>
                                            <span class="sm:col-span-2"><strong class="text-gray-500">Address:</strong> {{ $row['address'] }}</span>
                                            @if ($row['package'])
                                                <span><strong class="text-gray-500">Package:</strong> {{ $row['package'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right" wire:key="search-due-{{ $row['id'] }}-{{ $row['balance_due'] }}">
                                        <p class="text-lg font-bold {{ ($row['balance_due'] ?? 0) > 0.009 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                            {{ number_format((float) ($row['balance_due'] ?? 0), 0) }}
                                            <span class="text-xs font-semibold">{{ ($row['balance_due'] ?? 0) > 0.009 ? 'BDT due' : 'Paid' }}</span>
                                        </p>
                                        @if ($row['open_invoices'] > 0)
                                            <p class="text-xs text-gray-500">{{ $row['open_invoices'] }} open bill(s)</p>
                                        @endif
                                    </div>
                                </div>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($selectedCustomer)
            <div class="isp-collection-panel space-y-4">
                <div class="isp-collection-customer-card">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $selectedCustomer['name'] }}</h2>
                            <p class="font-mono text-sm text-violet-600 dark:text-violet-400">ID: {{ $selectedCustomer['customer_code'] }}</p>
                        </div>
                        <button type="button" wire:click="clearSelection" class="text-xs font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                            Change subscriber
                        </button>
                    </div>
                    <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="font-semibold text-gray-500">Phone</dt><dd>{{ $selectedCustomer['phone'] ?: '—' }}</dd></div>
                        <div><dt class="font-semibold text-gray-500">Username</dt><dd class="font-mono">{{ $selectedCustomer['username'] }}</dd></div>
                        @php $conn = $selectedCustomer['connection'] ?? []; @endphp
                        <div><dt class="font-semibold text-gray-500">PPP</dt>
                            <dd class="{{ ($conn['online'] ?? false) ? 'text-emerald-600 font-semibold' : 'text-gray-500' }}">
                                {{ ($conn['online'] ?? false) ? 'Online' : 'Offline' }}
                                @if (! empty($conn['connection_duration']))
                                    · {{ $conn['connection_duration'] }}
                                @endif
                            </dd>
                        </div>
                        <div><dt class="font-semibold text-gray-500">Last disconnect</dt>
                            <dd>{{ $conn['last_disconnect_formatted'] ?? '—' }}</dd>
                        </div>
                        <div wire:key="customer-due-{{ $selectedCustomerId }}-{{ $selectedCustomer['balance_due'] }}">
                            <dt class="font-semibold text-gray-500">Total due</dt>
                            <dd class="font-bold {{ ($selectedCustomer['balance_due'] ?? 0) > 0.009 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ number_format((float) ($selectedCustomer['balance_due'] ?? 0), 2) }} BDT
                                @if (($selectedCustomer['balance_due'] ?? 0) <= 0.009)
                                    <span class="text-xs font-semibold">· Paid</span>
                                @endif
                            </dd>
                        </div>
                        <div><dt class="font-semibold text-gray-500">Wallet</dt><dd>{{ number_format($selectedCustomer['account_balance'], 2) }} BDT</dd></div>
                    </dl>
                </div>

                <nav class="flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
                    <button type="button" wire:click="setTab('collect')" @class([
                        'rounded-lg px-4 py-2 text-sm font-semibold transition',
                        'bg-teal-600 text-white' => $activeTab === 'collect',
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $activeTab !== 'collect',
                    ])>Collect payment</button>
                    <button type="button" wire:click="setTab('bills')" @class([
                        'rounded-lg px-4 py-2 text-sm font-semibold transition',
                        'bg-teal-600 text-white' => $activeTab === 'bills',
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $activeTab !== 'bills',
                    ])>Bills ({{ count($selectedCustomer['bill_history'] ?? []) }})</button>
                    <button type="button" wire:click="setTab('history')" @class([
                        'rounded-lg px-4 py-2 text-sm font-semibold transition',
                        'bg-teal-600 text-white' => $activeTab === 'history',
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $activeTab !== 'history',
                    ])>Collection history ({{ count($selectedCustomer['collection_history'] ?? []) }})</button>
                </nav>

                @if ($activeTab === 'collect')
                    <form wire:submit="collectPayment" class="isp-collection-form max-w-3xl space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                        @if ($this->canPickCollector() && count($this->getCollectorStaffOptions()) > 0)
                            <div class="rounded-lg border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-900/40 dark:bg-violet-950/20">
                                <label class="mb-1 block text-xs font-bold uppercase text-violet-800 dark:text-violet-200">Collection credited to (staff) *</label>
                                <select wire:model.live="collectorUserId" class="isp-collection-select w-full" required>
                                    @foreach ($this->getCollectorStaffOptions() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-violet-900/70 dark:text-violet-200/70">
                                    Due/settlement এই staff-এর ওপর। Enter করছেন: <strong>{{ auth()->user()?->name }}</strong>
                                </p>
                                @error('collectorUserId')
                                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @elseif (! $this->canPickCollector())
                            <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                Collector: <strong>{{ auth()->user()?->name }}</strong>
                            </p>
                        @endif

                        <div class="rounded-lg border-2 border-sky-400 bg-sky-50 p-4 dark:border-sky-500 dark:bg-sky-950/30">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <label class="block text-sm font-bold text-sky-900 dark:text-sky-100">Renew / valid until (on full pay)</label>
                                <a href="{{ \App\Filament\Pages\ManagePaymentRenewalSettings::getUrl() }}" class="text-xs font-semibold text-sky-700 underline dark:text-sky-300">Global rules</a>
                            </div>
                            <select wire:model.live="renewalPolicy" class="isp-collection-select mt-2 w-full">
                                @foreach ($this->getRenewalPolicyOptions() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @if ($selectedCustomer['service_expires_at'] ?? null)
                                <p class="mt-2 text-xs text-sky-900/90 dark:text-sky-100/90">
                                    Current valid until: <strong>{{ $selectedCustomer['service_expires_at'] }}</strong>
                                </p>
                            @endif
                            <p class="mt-1 text-xs font-medium text-sky-800 dark:text-sky-200">{{ $this->renewalPolicyHint() }}</p>
                        </div>

                        @if (($selectedCustomer['account_balance'] ?? 0) > 0)
                            <div class="rounded-lg border border-fuchsia-200 bg-fuchsia-50/60 px-4 py-3 text-sm dark:border-fuchsia-900/40 dark:bg-fuchsia-950/30">
                                <p class="font-semibold text-fuchsia-900 dark:text-fuchsia-100">
                                    Customer wallet: {{ number_format($selectedCustomer['account_balance'], 2) }} BDT
                                </p>
                                @if ($invoiceId && $this->selectedInvoiceBalanceDue() !== null)
                                    <label class="mt-2 flex items-center gap-2 text-xs text-fuchsia-900 dark:text-fuchsia-100">
                                        <input type="checkbox" wire:model.live="useCustomerWallet" class="rounded border-fuchsia-400" />
                                        Apply wallet to this bill first (then cash for remainder)
                                    </label>
                                @endif
                            </div>
                        @endif
                        @if (! empty($selectedCustomer['invoices']))
                            <div>
                                <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Apply to invoice</label>
                                <select wire:model.live="invoiceId" class="isp-collection-select w-full">
                                    <option value="">— General payment (wallet) —</option>
                                    @foreach ($selectedCustomer['invoices'] as $inv)
                                        <option value="{{ $inv['id'] }}">
                                            {{ $inv['invoice_number'] }} · due {{ $inv['due_date'] }} · {{ number_format($inv['balance_due'], 2) }} BDT
                                            @if ($inv['is_overdue']) (overdue) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($this->selectedInvoiceBalanceDue() !== null)
                            <div class="rounded-lg border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm dark:border-amber-800 dark:bg-amber-950/30">
                                <p class="font-semibold text-amber-900 dark:text-amber-100">Partial payment & notes</p>
                                <p class="mt-1 text-amber-800 dark:text-amber-200">
                                    Bill due: <strong>{{ number_format($this->selectedInvoiceBalanceDue(), 2) }} BDT</strong>.
                                    কম টাকা নিলে বাকি due থাকবে — অবশ্যই <strong>Notes</strong> এ কারণ লিখুন।
                                </p>
                                @if ($this->previewCollectionDiscountBdt() > 0)
                                    <p class="mt-2 text-amber-900 dark:text-amber-100">
                                        Collection discount: <strong>{{ number_format($this->previewCollectionDiscountBdt(), 2) }} BDT</strong>
                                    </p>
                                @endif
                                @if (is_numeric($amount) && (float) $amount > 0 && $this->partialPaymentRemaining() !== null && $this->partialPaymentRemaining() < $this->selectedInvoiceBalanceDue())
                                    <p class="mt-2 font-bold text-rose-700 dark:text-rose-300">
                                        After cash + discount: {{ number_format($this->partialPaymentRemaining(), 2) }} BDT will remain due
                                    </p>
                                @endif
                            </div>
                        @endif

                        @if ($this->canApplyCollectionDiscount() && count($this->getCollectionDiscountPresetOptions()) > 0)
                            <div class="rounded-lg border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-900/40 dark:bg-violet-950/20">
                                <label class="mb-1 block text-xs font-bold uppercase text-violet-800 dark:text-violet-200">Collection discount (optional)</label>
                                <select wire:model.live="collectionDiscountPreset" class="isp-collection-select w-full">
                                    @foreach ($this->getCollectionDiscountPresetOptions() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($this->collectionDiscountAllowsCustom() && $collectionDiscountPreset === 'none')
                                    <div class="mt-2">
                                        <label class="mb-1 block text-xs font-semibold text-violet-900/80 dark:text-violet-200/80">Custom discount (BDT)</label>
                                        <input type="number" step="0.01" min="0" wire:model.live="collectionDiscountCustom" class="isp-collection-input w-full" placeholder="Max per admin settings" />
                                    </div>
                                @endif
                                <p class="mt-1 text-xs text-violet-900/70 dark:text-violet-200/70">
                                    Discount বিলে যোগ হবে।
                                    <a href="{{ \App\Filament\Pages\ManageCollectionDiscountSettings::getUrl() }}" class="font-semibold underline">Discount presets (admin)</a>
                                </p>
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Amount (BDT)</label>
                                <input type="number" step="0.01" min="0.01" @if($this->selectedInvoiceBalanceDue()) max="{{ $this->selectedInvoiceBalanceDue() }}" @endif wire:model.live="amount" class="isp-collection-input w-full text-lg font-bold" required />
                                @if ($this->selectedInvoiceBalanceDue() !== null)
                                    <p class="mt-1 text-xs text-gray-500">Invoice due auto-filled — collector can edit (partial pay allowed).</p>
                                @endif
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Method</label>
                                <select wire:model="method" class="isp-collection-select w-full">
                                    @foreach ($this->getMethodOptions() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Reference / TRX</label>
                                <input type="text" wire:model="reference" class="isp-collection-input w-full" placeholder="Optional" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold uppercase text-gray-500">
                                    Notes
                                    @if ($this->notesRequiredForCollection())
                                        <span class="text-rose-600">*</span>
                                    @endif
                                </label>
                                <input type="text" wire:model="notes" @class(['isp-collection-input w-full', 'ring-2 ring-amber-400' => $this->notesRequiredForCollection()]) placeholder="যেমন: ৫০০ এর মধ্যে ২০০ নিলাম, বাকি ১৫ তারিখে" @if($this->notesRequiredForCollection()) required @endif />
                            </div>
                        </div>

                        <p class="text-xs text-gray-500">
                            GPS:
                            @if ($latitude !== null)
                                {{ number_format($latitude, 5) }}, {{ number_format($longitude, 5) }}
                                @if ($accuracyMeters)(±{{ $accuracyMeters }}m)@endif
                            @else
                                not captured
                            @endif
                            <button type="button" onclick="captureDeskGps()" class="ml-1 font-semibold text-teal-600 hover:underline">Capture location</button>
                        </p>

                        <button type="submit" class="isp-collection-submit w-full sm:w-auto">
                            Collect payment
                        </button>
                    </form>
                @endif

                @if ($activeTab === 'bills')
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 text-left">Invoice</th>
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
                                            <button type="button" wire:click="recalculateInvoice({{ $bill['id'] }})" class="text-xs font-semibold text-teal-600 hover:underline">Recalc</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3 py-8 text-center text-gray-500">No invoices for this subscriber.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500">Wrong bill amount? Use <strong>Edit</strong> to change line items, dates, or status. Use <strong>Recalc</strong> to refresh totals after edits.</p>
                @endif

                @if ($activeTab === 'history')
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
                                    <th class="px-3 py-2 text-left">Invoice</th>
                                    <th class="px-3 py-2 text-left">Collected by</th>
                                    <th class="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse ($selectedCustomer['collection_history'] ?? [] as $pay)
                                    <tr wire:key="pay-{{ $pay['id'] }}" @class(['bg-amber-50/50 dark:bg-amber-950/20' => $editingPaymentId === $pay['id']])>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $pay['paid_at'] }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $pay['receipt_number'] }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($pay['amount'], 2) }}</td>
                                        <td class="px-3 py-2">{{ $pay['method'] }}</td>
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
                                        <td colspan="7" class="px-3 py-8 text-center text-gray-500">No collections yet for this subscriber.</td>
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
