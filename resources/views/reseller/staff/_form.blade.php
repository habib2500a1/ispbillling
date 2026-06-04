<div>
    <label class="block text-xs font-bold uppercase text-slate-500">Full name</label>
    <input name="name" value="{{ old('name', $staffMember?->name) }}" required class="mt-1 w-full rounded-lg border px-3 py-2">
    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-xs font-bold uppercase text-slate-500">Login ID</label>
    <input name="login" value="{{ old('login', $staffMember?->login) }}" required pattern="[A-Za-z0-9_-]+" class="mt-1 w-full rounded-lg border px-3 py-2 font-mono">
    <p class="mt-1 text-xs text-slate-500">Letters, numbers, dash and underscore only. Used at /reseller/login</p>
    @error('login')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-xs font-bold uppercase text-slate-500">Email (optional)</label>
        <input type="email" name="email" value="{{ old('email', $staffMember?->email) }}" class="mt-1 w-full rounded-lg border px-3 py-2">
        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-bold uppercase text-slate-500">Phone (optional)</label>
        <input name="phone" value="{{ old('phone', $staffMember?->phone) }}" class="mt-1 w-full rounded-lg border px-3 py-2">
        @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label class="block text-xs font-bold uppercase text-slate-500">{{ $staffMember ? 'New password (leave blank to keep)' : 'Password' }}</label>
    <input type="text" name="password" {{ $staffMember ? '' : 'required' }} autocomplete="new-password" class="mt-1 w-full rounded-lg border px-3 py-2 font-mono">
    @if ($staffMember?->passwordPlain())
        <p class="mt-1 text-xs text-slate-500">Current password: <code>{{ $staffMember->passwordPlain() }}</code></p>
    @endif
    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-xs font-bold uppercase text-slate-500 mb-2">Portal permissions</label>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach ($permissionOptions as $key => $label)
            <label class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <input type="checkbox" name="portal_permissions[]" value="{{ $key }}" class="mt-0.5"
                    @checked(in_array($key, $selectedPermissions, true))>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    @error('portal_permissions')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staffMember?->is_active ?? true))>
        Account active
    </label>
</div>
