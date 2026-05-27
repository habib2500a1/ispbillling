@php
    $archives = $this->getArchives();
    $auto = $this->getAutoBackupStatus();
    $scheduler = $this->getSchedulerHealth();
    $drives = $this->backupDrivesList;
    $allowedRoots = implode(', ', config('backup.allowed_drive_roots', []));
    $google = $this->googleDriveSnapshot;
    $tab = $this->activeBackupTab;
    $statCards = [
        ['label' => 'Saved ZIPs', 'value' => (string) count($archives), 'hint' => 'Server backup archives', 'class' => 'isp-hub-stat--emerald'],
        ['label' => 'Auto backup', 'value' => $auto['enabled'] ? 'Enabled' : 'Disabled', 'hint' => $auto['process_exists'] ? 'Scheduler configured' : 'Scheduler pending', 'class' => $auto['enabled'] ? 'isp-hub-stat--teal' : 'isp-hub-stat--amber'],
        ['label' => 'Google Drive', 'value' => ($google['connected'] ?? false) ? 'Connected' : 'Not connected', 'hint' => ($google['configured'] ?? false) ? 'OAuth configured' : 'OAuth pending', 'class' => ($google['connected'] ?? false) ? 'isp-hub-stat--sky' : 'isp-hub-stat--slate'],
        ['label' => 'External drives', 'value' => (string) count($drives), 'hint' => 'USB / NAS destinations', 'class' => 'isp-hub-stat--violet'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Recovery workspace"
            title="Backup & restore"
            description="Server backup, Google Drive cloud, and USB/NFS destinations in one disaster-recovery workspace."
            class="isp-hub-hero--emerald"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ count($archives) }} archives available</span>
                    <span class="isp-hub-section__meta">{{ $auto['enabled'] ? 'Auto backup on' : 'Auto backup off' }}</span>
                </div>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <nav class="flex flex-wrap gap-2 rounded-xl border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-900" aria-label="Backup sections">
            <button type="button" wire:click="setBackupTab('overview')"
                @class([
                    'rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                    'bg-emerald-600 text-white shadow' => $tab === 'overview',
                    'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $tab !== 'overview',
                ])>
                Server backup
            </button>
            <button type="button" wire:click="setBackupTab('google')"
                @class([
                    'rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                    'bg-blue-600 text-white shadow' => $tab === 'google',
                    'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $tab !== 'google',
                ])>
                Google Drive
                @if ($google['connected'] ?? false)
                    <span class="ml-1 inline-block h-2 w-2 rounded-full bg-emerald-300"></span>
                @endif
            </button>
            <button type="button" wire:click="setBackupTab('drives')"
                @class([
                    'rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                    'bg-sky-600 text-white shadow' => $tab === 'drives',
                    'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $tab !== 'drives',
                ])>
                USB / NAS drives
            </button>
        </nav>

        @if ($tab === 'overview')
            <div class="space-y-6">
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
                                                    class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                            No backups yet. Use <strong>Backup now</strong> or header <strong>Create & download backup</strong>.
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
        @endif

        @if ($tab === 'google')
            <div class="rounded-xl border-2 border-blue-300 bg-blue-50/50 p-5 shadow-sm dark:border-blue-700 dark:bg-blue-950/30">
                <h3 class="text-lg font-semibold text-blue-950 dark:text-blue-100">Google Drive backup</h3>
                <p class="mt-1 text-sm text-blue-900/80 dark:text-blue-200/80">
                    Cloud-এ backup রাখতে Google account connect করুন। প্রতিটি backup এর পর ZIP automatically upload হবে।
                </p>

                <details class="mt-3 rounded-lg border border-blue-200/80 bg-white/70 p-3 text-sm dark:border-blue-800 dark:bg-gray-900/40">
                    <summary class="cursor-pointer font-semibold text-blue-900 dark:text-blue-200">Google Cloud setup (একবার)</summary>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-gray-700 dark:text-gray-300">
                        <li><a href="https://console.cloud.google.com/" target="_blank" class="underline">Google Cloud Console</a> → New project</li>
                        <li>APIs &amp; Services → Enable <strong>Google Drive API</strong></li>
                        <li>OAuth consent screen → External → add your email as test user</li>
                        <li>Credentials → Create OAuth client ID → <strong>Web application</strong></li>
                        <li>Authorized redirect URI (exact copy):<br>
                            <code class="mt-1 block break-all rounded bg-gray-900 px-2 py-1 text-xs text-gray-100">{{ $google['redirect_uri'] ?? '' }}</code>
                        </li>
                        <li>Client ID + Client Secret নিচের ফর্মে দিন → Save → <strong>Connect Google Drive</strong></li>
                    </ol>
                </details>

                <div class="mt-4 space-y-4">
                    {{ $this->googleDriveForm }}
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button type="button" wire:click="saveGoogleDriveSettings" color="primary" icon="heroicon-o-check">
                        Save Google settings
                    </x-filament::button>
                    @if ($google['configured'] ?? false)
                        <a href="{{ route('admin.google-drive.connect') }}"
                           class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Connect Google Drive
                        </a>
                    @endif
                    @if ($google['connected'] ?? false)
                        <x-filament::button type="button" wire:click="testGoogleDriveUpload" color="gray" icon="heroicon-o-cloud-arrow-up">
                            Test upload (latest ZIP)
                        </x-filament::button>
                        <x-filament::button type="button" wire:click="disconnectGoogleDrive" color="danger"
                            wire:confirm="Disconnect Google Drive?">
                            Disconnect
                        </x-filament::button>
                    @endif
                </div>
            </div>
        @endif

        @if ($tab === 'drives')
            <div class="rounded-xl border border-sky-200 bg-sky-50/40 p-5 dark:border-sky-900/50 dark:bg-sky-950/20">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-sky-950 dark:text-sky-100">External backup drives (USB / NAS)</h3>
                        <p class="mt-1 text-sm text-sky-900/80 dark:text-sky-200/80">
                            Server-এ disk mount করে path যোগ করুন। Google Drive নয় — local extra disk।
                        </p>
                        @if ($allowedRoots !== '')
                            <p class="mt-1 text-xs text-sky-800 dark:text-sky-300">Allowed roots: {{ $allowedRoots }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                            wire:click="openAddDrive"
                            class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                            Add drive
                        </button>
                        @if (count($drives) > 0)
                            <button type="button"
                                wire:click="mirrorLatestToAllDrives"
                                wire:confirm="Copy latest server backup ZIP to all enabled drives?"
                                class="rounded-lg border border-sky-300 bg-white px-4 py-2 text-sm font-semibold text-sky-800 hover:bg-sky-100 dark:border-sky-700 dark:bg-gray-900 dark:text-sky-200">
                                Mirror latest to all
                            </button>
                        @endif
                    </div>
                </div>

                @if ($showDriveForm)
                    <div class="mt-4 rounded-lg border border-sky-200 bg-white p-4 dark:border-sky-800 dark:bg-gray-900">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $editingDriveId ? 'Edit drive' : 'New drive' }}
                        </h4>
                        <div class="mt-3 space-y-4">
                            {{ $this->driveForm }}
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-filament::button type="button" wire:click="saveDrive" color="success" icon="heroicon-o-check">
                                Save drive
                            </x-filament::button>
                            <x-filament::button type="button" wire:click="testDrivePath" color="gray" icon="heroicon-o-signal">
                                Test path
                            </x-filament::button>
                            <x-filament::button type="button" wire:click="resetDriveForm" color="gray">
                                Cancel
                            </x-filament::button>
                        </div>
                    </div>
                @endif

                <div class="mt-4 overflow-x-auto rounded-lg border border-sky-200/80 bg-white/70 dark:border-sky-800 dark:bg-gray-900/40">
                    <table class="w-full text-sm">
                        <thead class="bg-sky-50/80 text-left text-xs uppercase text-sky-800 dark:bg-sky-950/40 dark:text-sky-300">
                            <tr>
                                <th class="px-4 py-2">Name</th>
                                <th class="px-4 py-2">Path</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($drives as $drive)
                                <tr class="border-t dark:border-gray-800">
                                    <td class="px-4 py-3 font-semibold">{{ $drive['name'] }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $drive['mount_path'] }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $drive['archive_count'] }} ZIP</td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" wire:click="openEditDrive({{ $drive['id'] }})" class="rounded border px-2 py-1 text-xs">Edit</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No drives added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
