@if (session('error'))
    <div
        class="mb-4 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-950 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100"
        role="alert"
    >
        {{ session('error') }}
    </div>
@endif
