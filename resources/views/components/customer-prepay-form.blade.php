@props([
    'quote' => null,
    'action' => '',
    'paymentMethods' => [],
    'maxMonths' => 12,
    'quickMonths' => [1, 2, 3, 6, 12],
    'variant' => 'portal',
])

@php
    $hasQuote = $quote !== null;
    $monthly = (float) ($quote['monthly_rate'] ?? 0);
    $currentDue = (float) ($quote['current_due'] ?? 0);
    $defaultMonths = (int) ($quote['months'] ?? 1);
@endphp

<section class="portal-prepay-card {{ $variant === 'bill-pay' ? 'bp-prepay-card' : '' }}" data-prepay-form data-monthly="{{ $monthly }}" data-current-due="{{ $currentDue }}">
        <div class="portal-prepay-card__head">
            <div>
                <p class="portal-prepay-card__eyebrow">Advance payment</p>
                <h2 class="portal-prepay-card__title">Pay for multiple months</h2>
                <p class="portal-prepay-card__lead">
                    Choose 1, 2, 12, or any month up to {{ $maxMonths }}.
                    Current due is cleared first, then your service validity is extended.
                    @if ($quote['package_name'] ?? null)
                        Package: <strong>{{ $quote['package_name'] }}</strong> · {{ number_format($monthly, 2) }} BDT/month
                    @endif
                </p>
            </div>
        </div>

        @if (! $hasQuote)
            <p class="portal-prepay-card__warn">
                Advance payment is not available for this account right now (missing package pricing).
            </p>
        @else
            <form method="post" action="{{ $action }}" class="portal-prepay-card__form">
                @csrf
                <label class="portal-prepay-card__label" for="prepay-months-{{ $variant }}">How many months?</label>
                <div class="portal-prepay-card__quick">
                    @foreach ($quickMonths as $monthOption)
                        <button type="button" class="portal-prepay-chip" data-prepay-month="{{ $monthOption }}">{{ $monthOption }} mo</button>
                    @endforeach
                </div>
                <input
                    id="prepay-months-{{ $variant }}"
                    type="number"
                    name="months"
                    class="portal-prepay-card__input {{ $variant === 'bill-pay' ? 'bp-input' : '' }}"
                    min="1"
                    max="{{ $maxMonths }}"
                    value="{{ $defaultMonths }}"
                    required
                >

                <div class="portal-prepay-card__breakdown">
                    <div><span>Current due</span><strong data-prepay-current>{{ number_format($currentDue, 2) }} BDT</strong></div>
                    <div><span>Advance (<span data-prepay-months-label>{{ $defaultMonths }}</span> × {{ number_format($monthly, 2) }})</span><strong data-prepay-advance>{{ number_format($monthly * $defaultMonths, 2) }} BDT</strong></div>
                    <div class="portal-prepay-card__total"><span>Total to pay</span><strong data-prepay-total>{{ number_format($currentDue + ($monthly * $defaultMonths), 2) }} BDT</strong></div>
                </div>

                @if ($quote['projected_expires_at'] ?? null)
                    <p class="portal-prepay-card__meta">
                        Service valid until approx.
                        <strong data-prepay-expiry>{{ $quote['projected_expires_at']->format('M j, Y') }}</strong>
                    </p>
                @endif

                @if (count($paymentMethods) > 0)
                    <div class="portal-prepay-card__gateways">
                        @include('bill-payment.partials.payment-methods', ['methods' => $paymentMethods, 'compact' => $variant === 'portal'])
                    </div>
                @else
                    <p class="portal-prepay-card__warn">Online payment is not enabled right now.</p>
                @endif
            </form>
        @endif
</section>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-prepay-form]').forEach((root) => {
                const monthly = parseFloat(root.dataset.monthly || '0');
                const currentDue = parseFloat(root.dataset.currentDue || '0');
                const input = root.querySelector('input[name="months"]');
                const monthLabel = root.querySelector('[data-prepay-months-label]');
                const advanceEl = root.querySelector('[data-prepay-advance]');
                const totalEl = root.querySelector('[data-prepay-total]');

                if (!input) return;

                const render = () => {
                    const months = Math.max(1, parseInt(input.value || '1', 10));
                    const advance = monthly * months;
                    const total = currentDue + advance;
                    if (monthLabel) monthLabel.textContent = String(months);
                    if (advanceEl) advanceEl.textContent = advance.toFixed(2) + ' BDT';
                    if (totalEl) totalEl.textContent = total.toFixed(2) + ' BDT';
                };

                root.querySelectorAll('[data-prepay-month]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        input.value = btn.dataset.prepayMonth;
                        render();
                    });
                });

                input.addEventListener('input', render);
                render();
            });
        </script>
    @endpush
@endonce
