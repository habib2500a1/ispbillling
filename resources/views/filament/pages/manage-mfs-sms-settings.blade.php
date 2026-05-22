<x-filament-panels::page>
    @php
        $d = $downloads;
        $mfsApk = $mfsApk ?? \App\Support\MobileApkRelease::mfsVerify();
    @endphp

    <x-filament::section class="mb-6">
        <x-slot name="heading">RCL SMS APK — direct update</x-slot>
        <x-slot name="description">ফোনে লিংক ওপেন করলে সরাসরি ইনস্টল/আপডেট (v{{ $mfsApk['version_label'] }})</x-slot>
        <dl class="grid gap-3 text-sm">
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400">Version</dt>
                <dd class="mt-0.5 font-semibold text-emerald-700 dark:text-emerald-300">{{ $mfsApk['version_label'] }}
                    @if($mfsApk['file_exists'])
                        · {{ $mfsApk['file_size_mb'] }} MB
                        @if($mfsApk['updated_at']) · {{ $mfsApk['updated_at'] }} @endif
                    @else
                        · <span class="text-warning-600">APK not on server — run build script</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400">Direct update link (copy / WhatsApp)</dt>
                <dd class="mt-1">
                    <code class="block rounded-lg bg-emerald-100 dark:bg-emerald-900/40 px-3 py-2 text-xs break-all select-all font-semibold text-emerald-900 dark:text-emerald-100">{{ $mfsApk['update_url'] }}</code>
                </dd>
            </div>
        </dl>
        <div class="mt-4 flex flex-wrap gap-2">
            <x-filament::button tag="a" href="{{ $mfsApk['update_url'] }}" icon="heroicon-o-arrow-down-tray" color="success" download>
                Install / update v{{ $mfsApk['version'] }}
            </x-filament::button>
            <x-filament::button tag="a" href="{{ $mfsApk['download_url'] }}" size="sm" color="gray" outlined>
                Plain APK link
            </x-filament::button>
        </div>
    </x-filament::section>

    @include('filament.partials.personal-mfs-setup-panel', ['setup' => $setup])

    <form wire:submit="save" class="space-y-6 mt-6">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="false"
        />
    </form>

    <div class="grid gap-6 lg:grid-cols-2 mt-8">
        <x-filament::section>
            <x-slot name="heading">Mobile apps (download)</x-slot>
            <x-slot name="description">Admin unified app + payment-SIM RCL SMS APK</x-slot>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <p class="font-semibold">{{ $d['unified_label'] }}</p>
                    <p class="text-sm text-gray-500 mt-1">Admin + Client + RCL SMS (staff login)</p>
                    <x-filament::button tag="a" href="{{ $d['unified'] }}" icon="heroicon-o-arrow-down-tray" download class="mt-3">
                        Download unified APK
                    </x-filament::button>
                </div>
                <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/30 p-4">
                    <p class="font-semibold">{{ $d['mfs_verify_label'] }}</p>
                    <p class="text-sm text-gray-500 mt-1">Merchant SIM — device key only</p>
                    <code class="mt-2 block text-xs break-all select-all text-emerald-800 dark:text-emerald-200">{{ $d['mfs_verify_update'] ?? $d['mfs_verify'] }}</code>
                    <x-filament::button tag="a" href="{{ $d['mfs_verify_update'] ?? $d['mfs_verify'] }}" icon="heroicon-o-arrow-down-tray" color="success" download class="mt-3">
                        Update RCL SMS APK
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Status</x-slot>
            <dl class="grid gap-3 text-sm">
                <div>
                    <dt class="text-gray-500">bKash personal</dt>
                    <dd class="font-medium">
                        {{ $bkashPersonal ? 'Active' : 'Off' }}
                        @if($bkashPersonal)
                            · Auto-verify: <span class="{{ $bkashAutoVerify ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600' }}">{{ $bkashAutoVerify ? 'ON' : 'OFF' }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Nagad personal</dt>
                    <dd class="font-medium">
                        {{ $nagadPersonal ? 'Active' : 'Off' }}
                        @if($nagadPersonal)
                            · Auto-verify: <span class="{{ $nagadAutoVerify ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600' }}">{{ $nagadAutoVerify ? 'ON' : 'OFF' }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
            <p class="mt-2 text-xs text-gray-500">Auto-verify toggle: sidebar → <strong>Personal bKash</strong> / <strong>Personal Nagad</strong></p>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button tag="a" href="{{ $gatewayUrl }}" size="sm" color="gray" icon="heroicon-o-device-phone-mobile">
                    Personal bKash / Nagad
                </x-filament::button>
                <x-filament::button tag="a" href="{{ $ledgerUrl }}" size="sm" color="success" icon="heroicon-o-chat-bubble-left-right">
                    RCL SMS ledger
                </x-filament::button>
                <x-filament::button tag="a" href="{{ $pendingUrl }}" size="sm" color="warning" icon="heroicon-o-clock">
                    Pending gateway
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Workflow</x-slot>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 dark:text-gray-300">
            <li><strong>Auto-verify setup</strong> বাটন চাপুন (উপরে) — TrxID মিললে সাথে সাথে payment verify</li>
            <li><strong>RCL SMS APK</strong> — API base: <code class="text-xs">{{ $setup['api_base'] }}</code> (staff URL নয়)</li>
            <li><strong>Device key</strong> — Generate → APK-তে পেস্ট → Save</li>
            <li><strong>Personal bKash/Nagad</strong> — Gateway settings-এ personal নম্বর + auto-verify ON</li>
            <li>ভুল TrxID বা SMS না এলে → <a href="{{ $pendingUrl }}" class="text-primary-600 underline">Pending gateway</a> থেকে approve</li>
        </ol>
    </x-filament::section>
</x-filament-panels::page>
