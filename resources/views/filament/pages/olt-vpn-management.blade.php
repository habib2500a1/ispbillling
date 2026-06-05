<x-filament-panels::page class="isp-olt-vpn-page">
    <div class="space-y-5">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <strong>নতুন OLT:</strong> উপরে ডানে <strong>Add new OLT</strong> (অথবা OLT list → Create) → IP + type Save → এই তালিকায় আসবে → <strong>Manage</strong> থেকে VPN সেট করুন।
                <strong>Test now</strong> = তৎক্ষণাৎ ফলাফল।
            </p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                Server egress IP (MikroTik allow): <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">{{ $this->getEgressIpHint() }}</code>
            </p>
        </section>
        <section>
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
