<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
        Announcements for subscribers on the <strong>landing page</strong> and <strong>customer portal</strong>. Use start/end dates for scheduled notices.
    </div>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $editingId ? 'Edit notice' : 'Add notice' }}</h2>
                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Title</label>
                        <input type="text" wire:model="form.title" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" required />
                        @error('form.title') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Message</label>
                        <textarea wire:model="form.body" rows="4" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Sort</label>
                        <input type="number" wire:model="form.sort" min="0" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Start</label>
                            <input type="datetime-local" wire:model="form.starts_at" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">End</label>
                            <input type="datetime-local" wire:model="form.ends_at" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                        </div>
                    </div>
                    <div class="space-y-2 text-sm">
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_active" class="rounded border-gray-300 text-primary-600"> Active</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.show_on_landing" class="rounded border-gray-300 text-primary-600"> Show on website</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.show_on_portal" class="rounded border-gray-300 text-primary-600"> Show in portal</label>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-2">
                        <x-filament::button type="submit" size="sm">{{ $editingId ? 'Update' : 'Add' }}</x-filament::button>
                        @if ($editingId)
                            <x-filament::button type="button" color="gray" size="sm" wire:click="resetForm">Cancel</x-filament::button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
        <div class="lg:col-span-8">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">All notices</h2>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800">{{ $this->notices->count() }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-4 py-2.5">Title</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5">Visibility</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($this->notices as $notice)
                                <tr wire:key="notice-{{ $notice->id }}">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $notice->title }}</p>
                                        @if ($notice->body)<p class="mt-0.5 text-xs text-gray-500 line-clamp-2">{{ $notice->body }}</p>@endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button" wire:click="toggleActive({{ $notice->id }})" class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $notice->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $notice->is_active ? 'Active' : 'Hidden' }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        @if ($notice->show_on_landing)<span class="mr-1 rounded bg-sky-100 px-1.5 py-0.5 text-sky-800">Web</span>@endif
                                        @if ($notice->show_on_portal)<span class="rounded bg-violet-100 px-1.5 py-0.5 text-violet-800">Portal</span>@endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" wire:click="edit({{ $notice->id }})" class="rounded-lg p-1.5 text-primary-600 hover:bg-primary-50"><x-heroicon-o-pencil-square class="h-4 w-4" /></button>
                                        <button type="button" wire:click="delete({{ $notice->id }})" wire:confirm="Delete this notice?" class="rounded-lg p-1.5 text-danger-600 hover:bg-danger-50"><x-heroicon-o-trash class="h-4 w-4" /></button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No notices yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
