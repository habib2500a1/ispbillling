<x-filament-panels::page>
    @php($s = $this->getMobileStatus())

    @if (! $s['domain_matches'])
        <x-filament::section class="mb-4">
            <x-slot name="heading">Domain mismatch</x-slot>
            <p class="text-sm text-danger-600 dark:text-danger-400">
                Current <code>APP_URL</code> is <strong>{{ $s['app_url'] }}</strong>
                @if ($s['build_info'])
                    but the APK on this server was built for <strong>{{ $s['build_info']['app_url'] ?? 'unknown' }}</strong>.
                @else
                    but no APK build info was found on this server.
                @endif
                Users who install the old APK will connect to the wrong domain until you click <strong>Rebuild APK for this domain</strong>.
            </p>
        </x-filament::section>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Website download links</x-slot>
            <dl class="space-y-2 text-sm">
                <div><dt class="font-medium">Radiant ISP APK</dt><dd><a class="text-primary-600 underline break-all" href="{{ $s['radiant_download'] }}" target="_blank">{{ $s['radiant_download'] }}</a></dd></div>
                <div><dt class="font-medium">MFS Verify APK</dt><dd><a class="text-primary-600 underline break-all" href="{{ $s['mfs_download'] }}" target="_blank">{{ $s['mfs_download'] }}</a></dd></div>
                <div><dt class="font-medium">API base (mobile)</dt><dd><code>{{ $s['api_base_url'] }}</code></dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">APK on server</x-slot>
            <dl class="space-y-2 text-sm">
                <div><dt class="font-medium">Radiant file</dt><dd>{{ $s['radiant_exists'] ? "Yes ({$s['radiant_size_mb']} MB, {$s['radiant_modified']})" : 'Missing' }}</dd></div>
                <div><dt class="font-medium">MFS file</dt><dd>{{ $s['mfs_exists'] ? 'Yes' : 'Missing' }}</dd></div>
                <div><dt class="font-medium">Built for domain</dt><dd>{{ $s['build_info']['app_url'] ?? 'Unknown' }}</dd></div>
                <div><dt class="font-medium">Built at</dt><dd>{{ $s['build_info']['built_at'] ?? '—' }}</dd></div>
                <div><dt class="font-medium">Flutter on server</dt><dd>{{ $s['flutter_available'] ? 'Yes — full rebuild possible' : 'No — use GitHub sync or CI build' }}</dd></div>
            </dl>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-4">
        <x-slot name="heading">Logo & branding (no rebuild needed)</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
            Company logo from <a class="underline text-primary-600" href="{{ \App\Filament\Pages\ManageCompanySetup::getUrl() }}">Company setup</a>
            syncs automatically to the mobile app login screens via <code>/api/v1/mobile/config</code>.
            Change the logo there and save — installed apps show the new logo on next open.
        </p>
        @if ($s['logo_url'])
            <img src="{{ $s['logo_url'] }}" alt="Company logo" class="max-h-16 rounded border border-gray-200 dark:border-gray-700 bg-white p-2">
        @endif
        <p class="text-sm text-gray-500 mt-3">
            Home-screen launcher icon still uses the default app icon until you rebuild the APK.
            In-app logo and company name do <strong>not</strong> require a new build.
        </p>
    </x-filament::section>

    <x-filament::section class="mt-4">
        <x-slot name="heading">New domain checklist</x-slot>
        <ol class="list-decimal list-inside text-sm space-y-1 text-gray-600 dark:text-gray-300">
            <li>Set <code>APP_URL=https://new-domain.com</code> in <code>.env</code> and run <code>php artisan config:cache</code></li>
            <li>Click <strong>Rebuild APK for this domain</strong> above (or run <code>php artisan isp:rebuild-mobile-apks</code>)</li>
            <li>Users download fresh APK from <code>/downloads/isp-radiant.apk</code></li>
            <li>Or in the app: Login screen → <strong>Server settings</strong> → enter new domain (no reinstall)</li>
        </ol>
    </x-filament::section>
</x-filament-panels::page>
