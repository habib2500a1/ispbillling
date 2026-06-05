@extends('reseller.layout')

@section('title', 'Edit subscriber')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Edit — '.$customer->name,
        'subtitle' => $customer->customer_code.' · Sell '.number_format($pricing['retail_monthly'] ?? 0, 0).' BDT/mo',
        'backUrl' => route('reseller.customers.show', $customer),
        'backLabel' => '← Profile',
    ])

    <div class="rsl-panel rsl-panel-pad rsl-panel-narrow" id="pricing">
        <form method="post" action="{{ route('reseller.customers.update', $customer) }}" class="rsl-stack">
            @csrf
            @method('PUT')

            <section class="rsl-form-section">
                <h2 class="rsl-form-section-title">Customer info</h2>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name', $customer->name) }}" required class="rsl-input">
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="phone">Phone</label>
                    <input id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" required class="rsl-input">
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" class="rsl-input">
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="telegram_chat_id">Telegram chat ID</label>
                    <input id="telegram_chat_id" name="telegram_chat_id" value="{{ old('telegram_chat_id', $customer->telegram_chat_id) }}" class="rsl-input font-mono" placeholder="e.g. 123456789" pattern="-?[0-9]+">
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="address">Address</label>
                    <input id="address" name="address" value="{{ old('address', $customer->address) }}" class="rsl-input">
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="package_id">Package</label>
                    <select id="package_id" name="package_id" class="rsl-input">
                        @foreach ($options['packages'] as $pkg)
                            <option value="{{ $pkg['id'] }}" @selected(old('package_id', $customer->package_id) == $pkg['id'])>
                                {{ $pkg['name'] }} — sell {{ number_format($pkg['customer_price'] ?? $pkg['price_monthly'], 0) }} BDT
                                @if (! empty($pkg['wholesale_price']))
                                    · buy {{ number_format($pkg['wholesale_price'], 0) }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($options['areas']->isNotEmpty())
                    <div class="rsl-form-grid rsl-form-grid--2">
                        <div class="rsl-field">
                            <label class="rsl-field-label" for="area_id">Area</label>
                            <select id="area_id" name="area_id" class="rsl-input">
                                <option value="">—</option>
                                @foreach ($options['areas'] as $area)
                                    <option value="{{ $area->id }}" @selected(old('area_id', $customer->area_id) == $area->id)>{{ $area->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rsl-field">
                            <label class="rsl-field-label" for="zone_id">Zone</label>
                            <select id="zone_id" name="zone_id" class="rsl-input">
                                <option value="">—</option>
                                @foreach ($options['zones'] as $zone)
                                    <option value="{{ $zone->id }}" @selected(old('zone_id', $customer->zone_id) == $zone->id)>{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
            </section>

            @include('reseller.partials.customer-pricing-fields', ['customer' => $customer, 'pricing' => $pricing])
            @include('reseller.partials.customer-extended-fields', ['customer' => $customer])

            <section class="rsl-form-section">
                <h2 class="rsl-form-section-title">Billing & status</h2>
                <div class="rsl-form-grid rsl-form-grid--2">
                    <div class="rsl-field">
                        <label class="rsl-field-label" for="billing_mode">Billing mode</label>
                        <select id="billing_mode" name="billing_mode" class="rsl-input">
                            @foreach ($options['billing_modes'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('billing_mode', $customer->billing_mode) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rsl-field">
                        <label class="rsl-field-label" for="grace_period_days">Grace (days)</label>
                        <input type="number" id="grace_period_days" name="grace_period_days" min="0" max="90" value="{{ old('grace_period_days', $customer->grace_period_days) }}" class="rsl-input">
                    </div>
                    <div class="rsl-field">
                        <label class="rsl-field-label" for="billing_day">Billing day</label>
                        <input type="number" id="billing_day" name="billing_day" min="1" max="28" value="{{ old('billing_day', $customer->billing_day) }}" class="rsl-input">
                    </div>
                    <div class="rsl-field">
                        <label class="rsl-field-label" for="joined_at">Join date</label>
                        <input type="date" id="joined_at" name="joined_at" value="{{ old('joined_at', $customer->joined_at?->format('Y-m-d')) }}" class="rsl-input">
                    </div>
                    <div class="rsl-field">
                        <label class="rsl-field-label" for="service_expires_at">Service expires</label>
                        <input type="date" id="service_expires_at" name="service_expires_at" value="{{ old('service_expires_at', $customer->service_expires_at?->format('Y-m-d')) }}" class="rsl-input">
                    </div>
                    <div class="rsl-field">
                        <label class="rsl-field-label" for="status">Status</label>
                        <select id="customer-status" name="status" class="rsl-input">
                            @foreach ($options['status_options'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('status', $customer->status) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if (! empty($options['can_override_charge_mode']))
                    <div class="rsl-field">
                        <label class="rsl-field-label" for="new_customer_charge_mode">First month charge</label>
                        <select id="new_customer_charge_mode" name="new_customer_charge_mode" class="rsl-input">
                            @foreach ($options['charge_modes'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('new_customer_charge_mode', data_get($customer->meta, 'new_customer_charge_mode', $options['default_charge_mode'])) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <label class="rsl-field-check">
                    <input type="checkbox" name="allow_active_when_due" value="1" @checked(old('allow_active_when_due', data_get($customer->meta, 'allow_active_when_due')))>
                    <span>Keep online when bill is due</span>
                </label>
                @if ($customer->status !== 'active' && $portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE))
                    <label class="rsl-field-check" id="generate-bill-on-activate-wrap">
                        <input type="checkbox" name="generate_bill_on_activate" value="1" @checked(old('generate_bill_on_activate', true))>
                        <span>Generate this month's bill on reconnect</span>
                    </label>
                @endif
                <div class="rsl-field">
                    <label class="rsl-field-label" for="notes">Internal notes</label>
                    <textarea id="notes" name="notes" rows="3" class="rsl-input">{{ old('notes', $customer->notes) }}</textarea>
                </div>
            </section>

            <section class="rsl-form-section">
                <h2 class="rsl-form-section-title">PPPoE login</h2>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="mikrotik_secret_name">PPPoE username</label>
                    <input id="mikrotik_secret_name" name="mikrotik_secret_name" value="{{ old('mikrotik_secret_name', $customer->mikrotik_secret_name) }}" class="rsl-input font-mono">
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="mikrotik_ppp_password">PPPoE password</label>
                    <input type="text" id="mikrotik_ppp_password" name="mikrotik_ppp_password" class="rsl-input font-mono" placeholder="Leave blank to keep current" minlength="4">
                </div>
                <label class="rsl-field-check">
                    <input type="checkbox" name="provision_mikrotik" value="1" checked>
                    <span>Sync changes to MikroTik router</span>
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
                const sync = () => { wrap.style.display = status.value === 'active' ? '' : 'none'; };
                status.addEventListener('change', sync);
                sync();
            })();
        </script>
    @endif
@endsection
