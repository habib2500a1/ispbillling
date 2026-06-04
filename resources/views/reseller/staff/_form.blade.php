<div class="rsl-field">
    <label class="rsl-field-label" for="staff_name">Full name</label>
    <input id="staff_name" name="name" value="{{ old('name', $staffMember?->name) }}" required class="rsl-input">
    @error('name')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
</div>

<div class="rsl-field">
    <label class="rsl-field-label" for="staff_login">Login ID</label>
    <input id="staff_login" name="login" value="{{ old('login', $staffMember?->login) }}" required pattern="[A-Za-z0-9_-]+" class="rsl-input font-mono">
    <p class="rsl-field-hint">Letters, numbers, dash and underscore only. Used at /reseller/login</p>
    @error('login')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
</div>

<div class="rsl-form-grid rsl-form-grid--2">
    <div class="rsl-field">
        <label class="rsl-field-label" for="staff_email">Email (optional)</label>
        <input id="staff_email" type="email" name="email" value="{{ old('email', $staffMember?->email) }}" class="rsl-input">
        @error('email')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
    </div>
    <div class="rsl-field">
        <label class="rsl-field-label" for="staff_phone">Phone (optional)</label>
        <input id="staff_phone" name="phone" value="{{ old('phone', $staffMember?->phone) }}" class="rsl-input">
        @error('phone')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
    </div>
</div>

<div class="rsl-field">
    <label class="rsl-field-label" for="staff_password">{{ $staffMember ? 'New password (leave blank to keep)' : 'Password' }}</label>
    <input id="staff_password" type="text" name="password" {{ $staffMember ? '' : 'required' }} autocomplete="new-password" class="rsl-input font-mono">
    @if ($staffMember?->passwordPlain())
        <p class="rsl-field-hint">Current password: <code>{{ $staffMember->passwordPlain() }}</code></p>
    @endif
    @error('password')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
</div>

<div class="rsl-field">
    <span class="rsl-field-label">Portal permissions</span>
    <div class="rsl-form-grid rsl-form-grid--2">
        @foreach ($permissionOptions as $key => $label)
            <label class="rsl-settings-tile" style="padding:0.75rem 1rem;cursor:pointer">
                <span class="flex items-start gap-2 text-sm" style="display:flex;gap:0.5rem">
                    <input type="checkbox" name="portal_permissions[]" value="{{ $key }}" style="margin-top:0.2rem"
                        @checked(in_array($key, $selectedPermissions, true))>
                    <span>{{ $label }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('portal_permissions')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
</div>

<div class="rsl-field">
    <label class="flex items-center gap-2 text-sm" style="display:flex;gap:0.5rem;cursor:pointer">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staffMember?->is_active ?? true))>
        Account active
    </label>
</div>
