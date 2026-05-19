@php
    $archives = $this->getArchives();
    $auto = $this->getAutoBackupStatus();
    $scheduler = $this->getSchedulerHealth();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-teal-50/50 p-6 shadow-sm dark:border-emerald-900/40 dark:from-emerald-950/40 dark:via-gray-900 dark:to-gray-900">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Backup & restore</h2>
            <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                Download a full snapshot (database + uploaded files). Upload the same ZIP to restore.
            </p>
        </div>

        <div class="rounded-xl border border-violet-200 bg-violet-50/50 p-5 dark:border-violet-900/50 dark:bg-violet-950/20">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-violet-950 dark:text-violet-100">Daily auto backup</h3>
                    <p class="mt-1 text-sm text-violet-900/80 dark:text-violet-200/80">
                        প্রতিদিন রাতে সার্ভারে ZIP backup তৈরি হবে (ডাউনলোড আলাদা)।
                        @if ($auto['process_exists'])
                            সময়: <strong>{{ $auto['execute_at'] }}</strong>
                            @if ($auto['next_run_at'])
                                · পরের run: {{ $auto['next_run_at'] }}
                            @endif
                        @endif
                    </p>
                    @if ($auto['last_run_at'])
                        <p class="mt-1 text-xs text-violet-800 dark:text-violet-300">
                            শেষ run: {{ $auto['last_run_at'] }}
                            @if ($auto['last_status'])
                                ({{ $auto['last_status'] }})
                            @endif
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                        wire:click="toggleAutoBackup"
                        @class([
                            'rounded-lg px-4 py-2 text-sm font-semibold text-white',
                            'bg-violet-600 hover:bg-violet-700' => ! $auto['enabled'],
                            'bg-gray-500 hover:bg-gray-600' => $auto['enabled'],
                        ])>
                        {{ $auto['enabled'] ? 'Disable auto backup' : 'Enable daily auto backup' }}
                    </button>
                    <button type="button"
                        wire:click="runBackupNow"
                        wire:confirm="Create backup now on server?"
                        class="rounded-lg border border-violet-300 bg-white px-4 py-2 text-sm font-semibold text-violet-800 hover:bg-violet-100 dark:border-violet-700 dark:bg-gray-900 dark:text-violet-200">
                        Backup now
                    </button>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-violet-200/80 bg-white/60 p-3 text-sm dark:border-violet-800 dark:bg-gray-900/40">
                <p class="font-semibold text-gray-800 dark:text-gray-200">Linux cron (required for auto backup)</p>
                <p @class([
                    'mt-1',
                    $scheduler['healthy'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300',
                ])>
                    Scheduler: {{ $scheduler['label'] }}
                </p>
                <code class="mt-2 block overflow-x-auto rounded bg-gray-900 px-2 py-1.5 text-xs text-gray-100">{{ $scheduler['cron_line'] }}</code>
                <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                    Or open <a href="{{ \App\Filament\Resources\AutomaticProcessResource::getUrl() }}" class="font-semibold underline">Automatic processes</a>
                    → “{{ $auto['name'] }}” → Run now.
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Saved backups on server</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2">File</th>
                            <th class="px-4 py-2">Created</th>
                            <th class="px-4 py-2">Size</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($archives as $archive)
                            <tr class="border-t dark:border-gray-800">
                                <td class="px-4 py-3 font-mono text-xs">{{ $archive['label'] }}</td>
                                <td class="px-4 py-3">{{ $archive['created_at'] }}</td>
                                <td class="px-4 py-3">{{ $archive['size_human'] }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if (str_ends_with($archive['label'], '.zip'))
                                            <a href="{{ route('admin.backups.download', ['id' => $archive['id']]) }}"
                                               class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                Download
                                            </a>
                                        @endif
                                        <button type="button"
                                            wire:click="deleteBackup('{{ $archive['id'] }}')"
                                            wire:confirm="Delete this backup permanently?"
                                            class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                    No backups yet. Use <strong>Backup now</strong> or <strong>Create & download backup</strong>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form wire:submit="restoreUploadedBackup" class="rounded-xl border border-rose-200 bg-rose-50/30 p-6 dark:border-rose-900/50 dark:bg-rose-950/20">
            <h3 class="text-base font-semibold text-rose-950 dark:text-rose-100">Restore from upload</h3>
            <p class="mt-1 text-sm text-rose-900/80 dark:text-rose-200/80">
                Upload a backup ZIP previously downloaded from this system.
            </p>
            <div class="mt-4 space-y-4">
                {{ $this->restoreForm }}
            </div>
            <div class="mt-4">
                <x-filament::button type="submit" color="danger" icon="heroicon-o-arrow-up-tray">
                    Restore now
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
