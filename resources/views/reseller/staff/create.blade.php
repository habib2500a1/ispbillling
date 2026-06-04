@extends('reseller.layout')

@section('title', 'Add staff')

@section('content')
    <div class="rsl-card p-6 max-w-2xl">
        <h1 class="text-xl font-bold">Add staff member</h1>
        <p class="mt-1 text-sm text-slate-600">Create a login for a team member (collector, support, etc.).</p>

        <form method="post" action="{{ route('reseller.staff.store') }}" class="mt-6 grid gap-4">
            @csrf
            @include('reseller.staff._form', [
                'permissionOptions' => $permissionOptions,
                'selectedPermissions' => old('portal_permissions', $defaultPermissions),
                'staffMember' => null,
            ])
            <div class="flex gap-2">
                <button type="submit" class="rsl-btn">Create staff</button>
                <a href="{{ route('reseller.staff.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
