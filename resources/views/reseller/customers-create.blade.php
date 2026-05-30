@extends('reseller.layout')

@section('title', 'New subscriber')

@section('content')
    <div class="rsl-card p-6 max-w-2xl">
        <h1 class="rsl-title">New subscriber</h1>
        <p class="rsl-subtitle mt-1">Add client with PPPoE login. Prepaid = collect payment now; postpaid = bill due on account.</p>

        <form method="post" action="{{ route('reseller.customers.store') }}" class="mt-6 grid gap-6" id="subscriber-create-form">
            @csrf

            <section class="grid gap-4">
                <h2 class="rsl-heading text-sm uppercase tracking-wide">Customer info</h2>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Name</label><input name="name" value="{{ old('name') }}" required class="rsl-input mt-1"></div>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Phone</label><input name="phone" id="phone-input" value="{{ old('phone') }}" required class="rsl-input mt-1"></div>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Email</label><input name="email" type="email" value="{{ old('email') }}" class="rsl-input mt-1"></div>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Address</label><input name="address" value="{{ old('address') }}" required class="rsl-input mt-1"></div>
                @unless ($options['auto_generate_code'])
                    <div><label class="block text-xs font-bold uppercase rsl-text-muted">Client ID</label><input name="customer_code" value="{{ old('customer_code') }}" class="rsl-input mt-1" placeholder="{{ $options['client_id_prefix'] ?? '' }}"></div>
                @endunless
                @if ($options['areas']->isNotEmpty())
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold uppercase rsl-text-muted">Area</label>
                            <select name="area_id" class="rsl-input mt-1">
                                <option value="">—</option>
                                @foreach ($options['areas'] as $area)
                                    <option value="{{ $area->id }}" @selected(old('area_id') == $area->id)>{{ $area->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase rsl-text-muted">Zone</label>
                            <select name="zone_id" class="rsl-input mt-1">
                                <option value="">—</option>
                                @foreach ($options['zones'] as $zone)
                                    <option value="{{ $zone->id }}" @selected(old('zone_id') == $zone->id)>{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
            </section>

            <section class="grid gap-4 border-t border-slate-200 pt-6">
                <h2 class="rsl-heading text-sm uppercase tracking-wide">Package & billing</h2>
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Package</label>
                    <select name="package_id" id="package-select" required class="rsl-input mt-1">
                        @foreach ($options['packages'] as $pkg)
                            <option value="{{ $pkg['id'] }}" data-price="{{ (float) ($pkg['selling_price'] ?? $pkg['price_monthly']) }}" @selected(old('package_id') == $pkg['id'])>{{ $pkg['name'] }} — {{ number_format((float) ($pkg['selling_price'] ?? $pkg['price_monthly']), 0) }} BDT</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Billing mode</label>
                    <select name="billing_mode" id="billing-mode" class="rsl-input mt-1">
                        @foreach ($options['billing_modes'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('billing_mode', $options['defaults']['billing_mode']) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="generate_bill" value="1" class="rounded border-slate-300" @checked(old('generate_bill', '1') !== '0')>
                    Generate first bill now
                </label>
            </section>

            <section class="grid gap-4 border-t border-slate-200 pt-6">
                <h2 class="rsl-heading text-sm uppercase tracking-wide">PPPoE login</h2>
                <p class="text-sm rsl-text-muted">Username defaults to phone digits if left blank. Password syncs to router.</p>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">PPPoE username</label><input name="mikrotik_secret_name" id="ppp-username" value="{{ old('mikrotik_secret_name') }}" class="rsl-input mt-1 font-mono" placeholder="Auto from phone"></div>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">PPPoE password</label><input type="text" name="mikrotik_ppp_password" value="{{ old('mikrotik_ppp_password') }}" class="rsl-input mt-1 font-mono" placeholder="Leave blank to auto-generate" minlength="4"></div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="provision_mikrotik" value="1" class="rounded border-slate-300" @checked(old('provision_mikrotik', '1') !== '0')>
                    Push to MikroTik router now
                </label>
            </section>

            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
                <section id="prepaid-payment-section" class="grid gap-4 border-t border-slate-200 pt-6">
                    <h2 class="rsl-heading text-sm uppercase tracking-wide">Prepaid payment</h2>
                    <p class="text-sm rsl-text-muted">When billing mode is prepaid, record customer payment on the same step.</p>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="collect_payment" value="1" id="collect-payment" class="rounded border-slate-300" @checked(old('collect_payment', '1') !== '0')>
                        Collect payment now
                    </label>
                    <div id="payment-fields" class="grid gap-4">
                        <div><label class="block text-xs font-bold uppercase rsl-text-muted">Amount (BDT)</label><input type="number" name="payment_amount" id="payment-amount" step="0.01" min="0" value="{{ old('payment_amount') }}" class="rsl-input mt-1"></div>
                        <div>
                            <label class="block text-xs font-bold uppercase rsl-text-muted">Method</label>
                            <select name="payment_method" class="rsl-input mt-1">
                                @foreach ($options['payment_methods'] as $val => $label)
                                    <option value="{{ $val }}" @selected(old('payment_method', 'cash') === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="block text-xs font-bold uppercase rsl-text-muted">Reference / TrxID</label><input name="payment_reference" value="{{ old('payment_reference') }}" class="rsl-input mt-1" placeholder="Optional"></div>
                    </div>
                </section>
            @endif

            <div><label class="block text-xs font-bold uppercase rsl-text-muted">Notes</label><textarea name="notes" rows="2" class="rsl-input mt-1">{{ old('notes') }}</textarea></div>

            <button type="submit" class="rsl-btn w-full sm:w-auto">Create subscriber</button>
        </form>
    </div>

    <script>
        (function () {
            const billingMode = document.getElementById('billing-mode');
            const prepaidSection = document.getElementById('prepaid-payment-section');
            const collectPayment = document.getElementById('collect-payment');
            const paymentFields = document.getElementById('payment-fields');
            const packageSelect = document.getElementById('package-select');
            const paymentAmount = document.getElementById('payment-amount');
            const phoneInput = document.getElementById('phone-input');
            const pppUsername = document.getElementById('ppp-username');

            function syncPrepaidUi() {
                if (!prepaidSection) return;
                prepaidSection.style.display = billingMode.value === 'prepaid' ? '' : 'none';
            }

            function syncPaymentFields() {
                if (!paymentFields || !collectPayment) return;
                paymentFields.style.display = collectPayment.checked ? '' : 'none';
            }

            function syncPaymentAmount() {
                if (!paymentAmount || paymentAmount.value !== '') return;
                const opt = packageSelect.selectedOptions[0];
                if (opt && opt.dataset.price) {
                    paymentAmount.value = parseFloat(opt.dataset.price).toFixed(2);
                }
            }

            function syncPppFromPhone() {
                if (!pppUsername || pppUsername.value.trim() !== '') return;
                const digits = (phoneInput.value || '').replace(/\D+/g, '');
                if (digits) pppUsername.placeholder = digits;
            }

            billingMode?.addEventListener('change', syncPrepaidUi);
            collectPayment?.addEventListener('change', syncPaymentFields);
            packageSelect?.addEventListener('change', syncPaymentAmount);
            phoneInput?.addEventListener('input', syncPppFromPhone);

            syncPrepaidUi();
            syncPaymentFields();
            syncPaymentAmount();
            syncPppFromPhone();
        })();
    </script>
@endsection
