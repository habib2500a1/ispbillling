@extends('reseller.layout')

@section('title', 'Edit subscriber')

@section('content')
    <div class="rsl-card p-6 max-w-2xl">
        <h1 class="rsl-title">Edit {{ $customer->name }}</h1>
        <p class="rsl-subtitle mt-1">{{ $customer->customer_code }} · PPP user: <span class="font-mono">{{ $customer->mikrotik_secret_name ?: '—' }}</span></p>

        <form method="post" action="{{ route('reseller.customers.update', $customer) }}" class="mt-6 grid gap-6">
            @csrf
            @method('PUT')

            <section class="grid gap-4">
                <h2 class="rsl-heading text-sm uppercase tracking-wide">Customer info</h2>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Name</label><input name="name" value="{{ old('name', $customer->name) }}" required class="rsl-input mt-1"></div>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Phone</label><input name="phone" value="{{ old('phone', $customer->phone) }}" required class="rsl-input mt-1"></div>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Email</label><input name="email" type="email" value="{{ old('email', $customer->email) }}" class="rsl-input mt-1"></div>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">Address</label><input name="address" value="{{ old('address', $customer->address) }}" class="rsl-input mt-1"></div>
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Package</label>
                    <select name="package_id" class="rsl-input mt-1">
                        @foreach ($options['packages'] as $pkg)
                            <option value="{{ $pkg['id'] }}" @selected(old('package_id', $customer->package_id) == $pkg['id'])>{{ $pkg['name'] }} — {{ number_format($pkg['selling_price'], 0) }} BDT</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Billing mode</label>
                    <select name="billing_mode" class="rsl-input mt-1">
                        @foreach ($options['billing_modes'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('billing_mode', $customer->billing_mode) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($options['areas']->isNotEmpty())
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold uppercase rsl-text-muted">Area</label>
                            <select name="area_id" class="rsl-input mt-1">
                                <option value="">—</option>
                                @foreach ($options['areas'] as $area)
                                    <option value="{{ $area->id }}" @selected(old('area_id', $customer->area_id) == $area->id)>{{ $area->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase rsl-text-muted">Zone</label>
                            <select name="zone_id" class="rsl-input mt-1">
                                <option value="">—</option>
                                @foreach ($options['zones'] as $zone)
                                    <option value="{{ $zone->id }}" @selected(old('zone_id', $customer->zone_id) == $zone->id)>{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Status</label>
                    <select name="status" class="rsl-input mt-1">
                        @foreach ($options['status_options'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('status', $customer->status) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            <section class="grid gap-4 border-t border-slate-200 pt-6">
                <h2 class="rsl-heading text-sm uppercase tracking-wide">PPPoE login</h2>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">PPPoE username</label><input name="mikrotik_secret_name" value="{{ old('mikrotik_secret_name', $customer->mikrotik_secret_name) }}" class="rsl-input mt-1 font-mono"></div>
                <div><label class="block text-xs font-bold uppercase rsl-text-muted">PPPoE password</label><input type="text" name="mikrotik_ppp_password" class="rsl-input mt-1 font-mono" placeholder="Leave blank to keep current" minlength="4"></div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="provision_mikrotik" value="1" class="rounded border-slate-300" checked>
                    Sync changes to MikroTik router
                </label>
            </section>

            <button type="submit" class="rsl-btn">Save changes</button>
        </form>
    </div>
@endsection
