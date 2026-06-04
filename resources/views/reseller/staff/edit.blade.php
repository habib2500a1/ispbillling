@extends('reseller.layout')

@section('title', 'Staff Edit')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Staff — '.$staffMember->name,
        'backUrl' => route('reseller.staff.index'),
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:40rem">
        <form method="post" action="{{ route('reseller.staff.update', $staffMember) }}" class="rsl-form-grid">
            @csrf
            @method('PUT')
            @include('reseller.staff._form', [
                'permissionOptions' => $permissionOptions,
                'selectedPermissions' => old('portal_permissions', $staffMember->portalPermissions()),
                'staffMember' => $staffMember,
            ])
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <button type="submit" class="rsl-btn">Save changes</button>
                <a href="{{ route('reseller.staff.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Cancel</a>
            </div>
        </form>

        @if ($staffMember->is_active)
            <form method="post" action="{{ route('reseller.staff.destroy', $staffMember) }}" class="mt-6 pt-6 border-t" style="border-color:var(--rsl-border)" onsubmit="return confirm('Deactivate this staff account?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold" style="color:var(--rsl-danger)">Deactivate account</button>
            </form>
        @endif
    </div>
@endsection
