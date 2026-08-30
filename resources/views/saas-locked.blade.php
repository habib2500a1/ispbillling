<x-guest-layout>
    <div class="container">
        <div class="box box__sm">
            <div class="box__right">
                <div class="form">
                    <h2 class="form__title">{{ __('Subscription locked') }}</h2>
                    <p class="form__text">
                        {{ __('This ISP admin is locked because the SaaS bill is unpaid or the account was suspended.') }}
                    </p>
                    <p class="form__text">
                        <strong>{{ $operator->company }}</strong><br>
                        {{ __('Plan') }}: {{ $operator->plan }} · {{ $operator->billing_cycle }}<br>
                        {{ __('Due') }}: {{ optional($operator->next_due_at)->format('d M Y') ?: '—' }}<br>
                        {{ __('Amount') }}: ৳{{ number_format((int) ($invoice->amount ?? $operator->amount)) }}
                        @if($operator->user_base_count)
                            · {{ __('User base') }}: {{ number_format($operator->user_base_count) }}
                        @endif
                    </p>
                    <p class="form__text">{{ __('Pay the platform owner to unlock. After payment this login works again.') }}</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="form__button">{{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
