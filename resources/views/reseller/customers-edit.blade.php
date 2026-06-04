@extends('reseller.layout')

@section('title', 'Edit subscriber')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Edit — '.$customer->name,
        'subtitle' => $customer->customer_code.' · PPP: '.($customer->mikrotik_secret_name ?: '—'),
        'backUrl' => route('reseller.customers.show', $customer),
        'backLabel' => '← Profile',
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:40rem">
        <form method="post" action="{{ route('reseller.customers.update', $customer) }}" class="rsl-form-grid">
            @csrf
            @method('PUT')

            <section class="grid gap-4">
                <h2 class="rsl-heading text-sm uppercase tracking-wide">Customer info</h2>
                <div><label class="rsl-field-label">Name</label><input name="name" value="{{ old('name', $customer->name) }}" required class="rsl-input"></div>
                <div><label class="rsl-field-label">Phone</label><input name="phone" value="{{ old('phone', $customer->phone) }}" required class="rsl-input"></div>
                <div><label class="rsl-field-label">Email</label><input name="email" type="email" value="{{ old('email', $customer->email) }}" class="rsl-input"></div>
                <div>
                    <label class="rsl-field-label">Telegram chat ID</label>
                    <input name="telegram_chat_id" value="{{ old('telegram_chat_id', $customer->telegram_chat_id) }}" class="rsl-input mt-1 font-mono" placeholder="e.g. 123456789" pattern="-?[0-9]+">
                    <p class="mt-1 text-xs rsl-text-muted">Optional — bill reminders via Telegram (platform bot must be configured).</p>
                </div>
                <div><label class="rsl-field-label">Address</label><input name="address" value="{{ old('address', $customer->address) }}" class="rsl-input"></div>
                <div>
                    <label class="rsl-field-label">Package</label>
                    <select name="package_id" class="rsl-input">
                        @foreach ($options['packages'] as $pkg)
                            <option value="{{ $pkg['id'] }}" @selected(old('package_id', $customer->package_id) == $pkg['id'])>{{ $pkg['name'] }} — {{ number_format($pkg['customer_price'] ?? $pkg['price_monthly'], 0) }} BDT</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="rsl-field-label">Billing mode</label>
                    <select name="billing_mode" class="rsl-input">
                        @foreach ($options['billing_modes'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('billing_mode', $customer->billing_mode) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="rsl-field-label">Grace period (days)</label>
                    <input type="number" name="grace_period_days" min="0" max="90" value="{{ old('grace_period_days', $customer->grace_period_days) }}" class="rsl-input">
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="allow_active_when_due" value="1" class="rounded border-slate-300" @checked(old('allow_active_when_due', data_get($customer->meta, 'allow_active_when_due')))>
                    Keep online when bill is due
                </label>
                @if ($options['areas']->isNotEmpty())
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="rsl-field-label">Area</label>
                            <select name="area_id" class="rsl-input">
                                <option value="">—</option>
                                @foreach ($options['areas'] as $area)
                                    <option value="{{ $area->id }}" @selected(old('area_id', $customer->area_id) == $area->id)>{{ $area->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="rsl-field-label">Zone</label>
                            <select name="zone_id" class="rsl-input">
                                <option value="">—</option>
                                @foreach ($options['zones'] as $zone)
                                    <option value="{{ $zone->id }}" @selected(old('zone_id', $customer->zone_id) == $zone->id)>{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
                <div>
                    <label class="rsl-field-label">Status</label>
                    <select name="status" id="customer-status" class="rsl-input">
                        @foreach ($options['status_options'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('status', $customer->status) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($customer->status !== 'active' && $portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE))
                    <label class="flex items-center gap-2 text-sm" id="generate-bill-on-activate-wrap">
                        <input type="checkbox" name="generate_bill_on_activate" value="1" class="rounded border-slate-300" @checked(old('generate_bill_on_activate', true))>
                        Generate this month's bill on reconnect (330 HQ + retail bill)
                    </label>
                @endif
            </section>

            <section class="grid gap-4 border-t border-slate-200 pt-6">
                <h2 class="rsl-heading text-sm uppercase tracking-wide">PPPoE login</h2>
                <div><label class="rsl-field-label">PPPoE username</label><input name="mikrotik_secret_name" value="{{ old('mikrotik_secret_name', $customer->mikrotik_secret_name) }}" class="rsl-input mt-1 font-mono"></div>
                <div><label class="rsl-field-label">PPPoE password</label><input type="text" name="mikrotik_ppp_password" class="rsl-input mt-1 font-mono" placeholder="Leave blank to keep current" minlength="4"></div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="provision_mikrotik" value="1" class="rounded border-slate-300" checked>
                    Sync changes to MikroTik router
                </label>
            </section>

            <button type="submit" class="rsl-btn">Save changes</button>
        </form>
    </div>
    @if ($customer->status !== 'active')
        <script>
            (function () {
                const status = document.getElementById('customer-status');
                const wrap = document.getElementById('generate-bill-on-activate-wrap');
                if (!status || !wrap) return;
                const sync = () => {
                    wrap.style.display = status.value === 'active' ? '' : 'none';
                };
                status.addEventListener('change', sync);
                sync();
            })();
        </script>
    @endif
@endsection
