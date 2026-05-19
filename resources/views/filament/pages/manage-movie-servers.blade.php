<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100">
        Manage portal movie / FTP server entries, display order, and availability from one screen.
        Active servers with <strong>Show on website</strong> appear on the public landing page; <strong>Show in customer portal</strong> shows them to logged-in subscribers.
    </div>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $editingId ? 'Edit server' : 'Add server' }}
                </h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Name</label>
                        <input
                            type="text"
                            wire:model="form.name"
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            required
                        />
                        @error('form.name') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">URL / IP</label>
                        <input
                            type="url"
                            wire:model="form.url"
                            placeholder="http://... or https://..."
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            required
                        />
                        @error('form.url') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Note</label>
                        <textarea
                            wire:model="form.note"
                            rows="3"
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        ></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Sort</label>
                        <input
                            type="number"
                            wire:model="form.sort"
                            min="0"
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                    </div>

                    <div class="space-y-2 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.is_active" class="rounded border-gray-300 text-primary-600">
                            <span>Active</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.show_on_landing" class="rounded border-gray-300 text-primary-600">
                            <span>Show on website (landing)</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.show_on_portal" class="rounded border-gray-300 text-primary-600">
                            <span>Show in customer portal</span>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <x-filament::button type="submit" size="sm">
                            {{ $editingId ? 'Update server' : 'Add server' }}
                        </x-filament::button>
                        @if ($editingId)
                            <x-filament::button type="button" color="gray" size="sm" wire:click="resetForm">
                                Cancel
                            </x-filament::button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">All servers</h2>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        {{ $this->servers->count() }} item(s)
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800/80 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2.5">ID</th>
                                <th class="px-4 py-2.5">Name</th>
                                <th class="px-4 py-2.5">URL</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5">Visibility</th>
                                <th class="px-4 py-2.5">Sort</th>
                                <th class="px-4 py-2.5">Updated</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($this->servers as $server)
                                <tr wire:key="movie-server-{{ $server->id }}" class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                    <td class="px-4 py-3 text-gray-500">{{ $server->id }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $server->name }}</td>
                                    <td class="max-w-[12rem] truncate px-4 py-3">
                                        <a href="{{ $server->url }}" target="_blank" rel="noopener noreferrer" class="font-mono text-xs text-rose-600 hover:underline dark:text-rose-400">
                                            {{ $server->url }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button
                                            type="button"
                                            wire:click="toggleActive({{ $server->id }})"
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $server->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}"
                                        >
                                            {{ $server->is_active ? 'Active' : 'Hidden' }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                                        @if ($server->show_on_landing)<span class="mr-1 rounded bg-sky-100 px-1.5 py-0.5 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200">Web</span>@endif
                                        @if ($server->show_on_portal)<span class="rounded bg-violet-100 px-1.5 py-0.5 text-violet-800 dark:bg-violet-900/50 dark:text-violet-200">Portal</span>@endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $server->sort }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ $server->updated_at?->diffForHumans() }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex gap-1">
                                            <button
                                                type="button"
                                                wire:click="edit({{ $server->id }})"
                                                class="rounded-lg p-1.5 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-950/40"
                                                title="Edit"
                                            >
                                                <x-heroicon-o-pencil-square class="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="delete({{ $server->id }})"
                                                wire:confirm="Remove this server?"
                                                class="rounded-lg p-1.5 text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-950/40"
                                                title="Delete"
                                            >
                                                <x-heroicon-o-trash class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                        No servers yet — add Sam Online, Discovery FTP, or your CDN links on the left.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
