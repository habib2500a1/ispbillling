@php
    /** @var \App\Models\Reseller $record */
    $record = $getRecord();
    $loginUrl = route('staff.resellers.portal-login', ['reseller' => $record->getKey()]);
@endphp
<a
    href="{{ $loginUrl }}"
    target="_blank"
    rel="noopener noreferrer"
    class="isp-portal-login-col inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700"
    title="Open partner portal as this reseller"
>
    <x-filament::icon icon="heroicon-o-arrow-right-on-rectangle" class="h-3.5 w-3.5" />
    Portal login
</a>
