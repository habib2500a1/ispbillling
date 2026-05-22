<x-filament-widgets::widget>
    <x-filament::section
        class="!border-warning-500/40 !bg-warning-50 dark:!bg-warning-950/40"
        icon="heroicon-o-arrow-right-circle"
        icon-color="warning"
    >
        <x-slot name="heading">ভুল subscriber ID — Transfer</x-slot>
        <x-slot name="description">
            টেবিলে <strong>Amount</strong> এর পরে হলুদ <strong>Transfer</strong> বাটন দেখতে হবে (Linked payment সারিতে)।
            না দেখলে <kbd class="rounded bg-gray-200 px-1 dark:bg-gray-700">Ctrl+Shift+R</kbd> হার্ড রিফ্রেশ করুন।
        </x-slot>

        <div class="flex flex-wrap gap-2">
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\PaymentResource::getUrl('index') }}"
                color="warning"
                icon="heroicon-o-banknotes"
            >
                Payments তে Transfer
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
