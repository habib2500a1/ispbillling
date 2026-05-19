@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ActivityLog> $auditTimeline */
@endphp

<section class="isp-rbac-audit mt-6 rounded-xl border border-gray-200 dark:border-gray-700">
    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/80">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Audit timeline</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400">Role permission changes and clones</p>
    </div>
    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($auditTimeline as $entry)
            <li class="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="font-mono text-xs text-violet-600 dark:text-violet-400">{{ $entry->event }}</span>
                    <p class="text-gray-800 dark:text-gray-200">{{ $entry->description }}</p>
                    @if (! empty($entry->properties['added'] ?? []))
                        <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">
                            + {{ implode(', ', $entry->properties['added']) }}
                        </p>
                    @endif
                    @if (! empty($entry->properties['removed'] ?? []))
                        <p class="text-xs text-rose-700 dark:text-rose-400">
                            − {{ implode(', ', $entry->properties['removed']) }}
                        </p>
                    @endif
                </div>
                <time class="shrink-0 text-xs text-gray-500" datetime="{{ $entry->created_at?->toIso8601String() }}">
                    {{ $entry->created_at?->diffForHumans() }}
                </time>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                No RBAC audit events yet for this role.
            </li>
        @endforelse
    </ul>
</section>
