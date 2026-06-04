@extends('reseller.layout')

@section('title', 'Staff')

@section('content')
    <div class="rsl-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Staff accounts</h1>
                <p class="mt-1 text-sm text-slate-600">Team members who can log in to your partner portal with limited access.</p>
            </div>
            <a href="{{ route('reseller.staff.create') }}" class="rsl-btn-sm">+ Add staff</a>
        </div>
    </div>

    <div class="rsl-card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
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
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-medium">{{ $member->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $member->login }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $member->passwordPlain() ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ count($member->portalPermissions()) }} enabled</td>
                            <td class="px-4 py-3">
                                @if ($member->is_active)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('reseller.staff.edit', $member) }}" class="text-indigo-600 font-semibold">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No staff yet. Add collectors or support staff with their own login.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($staff->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $staff->links() }}</div>
        @endif
    </div>

    <div class="rsl-card mt-6 p-6 text-sm text-slate-600">
        <p class="font-semibold text-slate-800">How staff login works</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>Staff use the same portal URL: <a href="{{ route('reseller.login') }}" class="text-indigo-600 underline">{{ route('reseller.login') }}</a></li>
            <li>Each staff member gets a unique login ID and password you set here.</li>
            <li>Permissions cannot exceed what your partner account already has.</li>
            <li>Only the main partner login can manage staff — staff cannot add other staff.</li>
        </ul>
    </div>
@endsection
