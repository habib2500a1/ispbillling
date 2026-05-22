<x-filament-panels::page>
    @include('filament.partials.personal-mfs-setup-panel', ['setup' => $setup])

    <div class="mb-4 mt-6 rounded-xl border-2 border-emerald-500/70 bg-emerald-50 p-4 text-sm text-emerald-950 dark:border-emerald-600/50 dark:bg-emerald-950/30 dark:text-emerald-100">
        <p class="font-bold text-base">Personal MFS verify (PipraPay-style)</p>
        <p class="mt-1">Merchant API (bKash Tokenized / Nagad PG / PipraPay) আলাদা — <a href="{{ $merchantUrl }}" class="underline font-semibold">Merchant gateways</a></p>
        <ol class="mt-2 list-decimal list-inside space-y-1 opacity-90">
            <li>এখানে personal নম্বর সেট করুন (bKash / Nagad ট্যাব)</li>
            <li><a href="{{ $smsUrl }}" class="underline">RCL SMS & apps</a> — ingest + device key + <a href="{{ $mfsApk['update_url'] }}" class="underline font-semibold">APK update v{{ $mfsApk['version_label'] }}</a></li>
            <li><a href="{{ $ledgerUrl }}" class="underline">RCL SMS ledger</a> — SMS approve</li>
            <li><a href="{{ $pendingUrl }}" class="underline">Pending gateway</a> — manual TrxID verify</li>
        </ol>
        <p class="mt-2 text-xs">
            bKash: {{ $bkashActive ? '✓ Active' : '○ Not configured' }} ·
            Nagad: {{ $nagadActive ? '✓ Active' : '○ Not configured' }}
        </p>
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="false"
        />
    </form>
</x-filament-panels::page>
