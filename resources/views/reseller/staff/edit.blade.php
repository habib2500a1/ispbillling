@extends('reseller.layout')

@section('title', 'Edit staff')

@section('content')
    <div class="rsl-card p-6 max-w-2xl">
        <h1 class="text-xl font-bold">Edit staff — {{ $staffMember->name }}</h1>

        <form method="post" action="{{ route('reseller.staff.update', $staffMember) }}" class="mt-6 grid gap-4">
            @csrf
            @method('PUT')
            @include('reseller.staff._form', [
                'permissionOptions' => $permissionOptions,
                'selectedPermissions' => old('portal_permissions', $staffMember->portalPermissions()),
                'staffMember' => $staffMember,
            ])
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rsl-btn">Save changes</button>
                <a href="{{ route('reseller.staff.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Cancel</a>
            </div>
        </form>

        @if ($staffMember->is_active)
            <form method="post" action="{{ route('reseller.staff.destroy', $staffMember) }}" class="mt-8 border-t border-slate-200 pt-6" onsubmit="return confirm('Deactivate this staff account?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-red-600">Deactivate account</button>
            </form>
        @endif
    </div>
@endsection
