@extends('reseller.layout')

@section('title', 'Add staff')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Add staff',
        'subtitle' => 'Separate logins for collectors, support, etc.',
        'backUrl' => route('reseller.staff.index'),
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:40rem">
        <form method="post" action="{{ route('reseller.staff.store') }}" class="rsl-form-grid">
            @csrf
            @include('reseller.staff._form', [
                'permissionOptions' => $permissionOptions,
                'selectedPermissions' => old('portal_permissions', $defaultPermissions),
                'staffMember' => null,
            ])
            <div class="flex gap-2 flex-wrap" style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <button type="submit" class="rsl-btn">Create staff</button>
                <a href="{{ route('reseller.staff.index') }}" class="rsl-btn-sm rsl-btn-sm--outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
