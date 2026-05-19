@if (session('success'))
    <div
        class="fi-global-notification fixed start-0 end-0 top-0 z-50 mx-auto flex max-w-lg justify-center p-4"
        role="status"
    >
        <div
            class="w-full rounded-lg bg-success-600 px-4 py-3 text-sm font-medium text-white shadow-lg dark:bg-success-500"
        >
            {{ session('success') }}
        </div>
    </div>
@endif

@if (session('danger'))
    <div
        class="fi-global-notification fixed start-0 end-0 top-0 z-50 mx-auto flex max-w-lg justify-center p-4"
        role="alert"
    >
        <div
            class="w-full rounded-lg bg-danger-600 px-4 py-3 text-sm font-medium text-white shadow-lg dark:bg-danger-500"
        >
            {{ session('danger') }}
        </div>
    </div>
@endif
