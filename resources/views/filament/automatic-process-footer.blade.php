@php
    $stats = $stats ?? ['enabled' => 0, 'failed_24h' => 0, 'next_hour' => 0];
    $cron = $cron ?? ['healthy' => false, 'label' => 'Unknown'];
    $cronLine = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> storage/logs/scheduler.log 2>&1';
@endphp

<div class="mt-4 grid gap-3 sm:grid-cols-3">
    <div class="rounded-xl border border-teal-200/80 bg-teal-50/80 px-4 py-3 dark:border-teal-900/50 dark:bg-teal-950/30">
        <p class="text-xs font-medium uppercase tracking-wide text-teal-800/70 dark:text-teal-300/70">Enabled</p>
        <p class="mt-1 text-2xl font-semibold text-teal-950 dark:text-teal-50">{{ $stats['enabled'] }}</p>
    </div>
    <div class="rounded-xl border border-rose-200/80 bg-rose-50/80 px-4 py-3 dark:border-rose-900/50 dark:bg-rose-950/30">
        <p class="text-xs font-medium uppercase tracking-wide text-rose-800/70 dark:text-rose-300/70">Failed (24h)</p>
        <p class="mt-1 text-2xl font-semibold text-rose-950 dark:text-rose-50">{{ $stats['failed_24h'] }}</p>
    </div>
    <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/30">
        <p class="text-xs font-medium uppercase tracking-wide text-amber-800/70 dark:text-amber-300/70">Due within 1h</p>
        <p class="mt-1 text-2xl font-semibold text-amber-950 dark:text-amber-50">{{ $stats['next_hour'] }}</p>
    </div>
</div>

<div class="mt-4 rounded-xl border px-4 py-3 text-sm {{ $cron['healthy'] ? 'border-emerald-200/80 bg-emerald-50/80 text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100' : 'border-rose-200/80 bg-rose-50/80 text-rose-950 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-100' }}">
    <p class="font-medium">Server cron status</p>
    <p class="mt-1 opacity-90">{{ $cron['label'] }}</p>
    <p class="mt-2 font-mono text-xs break-all opacity-80">{{ $cronLine }}</p>
</div>

<div class="mt-4 rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
    <p class="font-medium">Scheduler</p>
    <p class="mt-1 text-amber-900/90 dark:text-amber-200/90">
        All tasks run from the database. Server needs only
        <span class="font-mono text-xs">php artisan schedule:run</span> every minute.
        All <strong>Execute at</strong> times are <strong>{{ config('isp.timezone_label', 'BDT') }}</strong> ({{ config('app.timezone', 'Asia/Dhaka') }}).
        Change under <a href="{{ \App\Filament\Pages\ManageCompanySetup::getUrl() }}" class="font-medium underline">System → Company setup</a> → Timezone & schedules.
        Use <strong>Edit</strong> to change time/interval; <strong>Run</strong> or <strong>History</strong> for manual runs and logs.
    </p>
</div>
