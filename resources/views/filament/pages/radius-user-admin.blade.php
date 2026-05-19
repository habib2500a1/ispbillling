<x-filament-panels::page>
    @if (! $this->isRadiusAvailable())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            <p class="font-semibold">RADIUS database not connected</p>
            <p class="mt-2">Set <code class="text-xs">RADIUS_ADMIN_ENABLED=true</code> and configure <code class="text-xs">RADIUS_DB_*</code> in .env (FreeRADIUS MySQL).</p>
        </div>
    @else
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            Manage <code class="text-xs">radcheck</code> / <code class="text-xs">radusergroup</code>. Match subscribers via <strong>radius_username</strong> on customer records.
        </p>
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Username</th>
                        <th class="px-4 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                    @forelse ($this->usernames as $username)
                        <tr wire:key="radius-{{ $username }}">
                            <td class="px-4 py-3 font-mono">{{ $username }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-filament::button size="xs" color="danger" wire:click="rejectUser('{{ $username }}')">
                                        Reject
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="success" wire:click="allowUser('{{ $username }}')">
                                        Allow
                                    </x-filament::button>
                                    <x-filament::button
                                        size="xs"
                                        color="danger"
                                        wire:click="deleteUser('{{ $username }}')"
                                        wire:confirm="Remove {{ $username }} from RADIUS tables?"
                                    >
                                        Delete
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-8 text-center text-gray-500">No RADIUS users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
