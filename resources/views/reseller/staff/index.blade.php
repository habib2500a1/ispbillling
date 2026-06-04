@extends('reseller.layout')

@section('title', 'Staff')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Staff accounts',
        'subtitle' => 'Team members can sign in with limited permissions.',
        'actionUrl' => route('reseller.staff.create'),
        'actionLabel' => '+ Add staff',
    ])

    <div class="rsl-panel">
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Login ID</th>
                        <th class="px-4 py-3">Password</th>
                        <th class="px-4 py-3">Permissions</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $member)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $member->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $member->login }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $member->passwordPlain() ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">{{ count($member->portalPermissions()) }} enabled</td>
                            <td class="px-4 py-3">
                                @if ($member->is_active)
                                    <span class="rsl-badge-pill rsl-badge-pill--ok">Active</span>
                                @else
                                    <span class="rsl-badge-pill rsl-badge-pill--muted">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('reseller.staff.edit', $member) }}" class="rsl-link-action">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm" style="color:var(--rsl-text-muted)">No staff yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($staff->hasPages())
            <div class="rsl-panel-pad border-t" style="border-color:var(--rsl-border)">{{ $staff->links() }}</div>
        @endif
    </div>

    <div class="rsl-panel rsl-panel-pad mt-4 text-sm" style="color:var(--rsl-text-muted)">
        <p class="rsl-panel-title">Staff login</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>URL: <a href="{{ route('reseller.login') }}" class="rsl-link">{{ route('reseller.login') }}</a></li>
            <li>Each staff member has their own login ID and password</li>
            <li>Permissions cannot exceed the main partner</li>
        </ul>
    </div>
@endsection
