<x-filament-panels::page class="isp-accounts-page">
    <div class="space-y-5">
        <section class="isp-accounts-hero">
            <div class="isp-accounts-hero__main">
                <p class="isp-accounts-hero__eyebrow">Accounts</p>
                <h2 class="isp-accounts-hero__title">Expenses</h2>
                <p class="isp-accounts-hero__sub">Vendor payments and approved field collector expenses.</p>
            </div>
            <div class="isp-accounts-filters">
                <div>
                    <label for="exp-from">From</label>
                    <input id="exp-from" type="date" wire:model.live="dateFrom" class="isp-accounts-filters__input" />
                </div>
                <div>
                    <label for="exp-to">To</label>
                    <input id="exp-to" type="date" wire:model.live="dateTo" class="isp-accounts-filters__input" />
                </div>
            </div>
        </section>

        <section class="isp-accounts-stats">
            <div class="isp-accounts-stat isp-accounts-stat--expense">
                <span class="isp-accounts-stat__label">Total expenses</span>
                <strong>{{ number_format($this->totalExpenses, 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Vendor</span>
                <strong>{{ number_format($this->vendorTotal, 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Collector (approved)</span>
                <strong>{{ number_format($this->collectorTotal, 2) }}</strong>
            </div>
        </section>

        <section class="isp-accounts-table-card">
            <div class="isp-accounts-table-card__head">
                <h3>Vendor payments</h3>
            </div>
            {{ $this->table }}
        </section>

        @if ($this->collectorExpenses->isNotEmpty())
            <section class="isp-accounts-table-card">
                <div class="isp-accounts-table-card__head">
                    <h3>Collector expenses</h3>
                </div>
                <div class="isp-accounts-scroll-table">
                    <table class="isp-accounts-data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Collector</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->collectorExpenses as $exp)
                                <tr>
                                    <td>{{ $exp->expense_date?->format('d/m/Y') }}</td>
                                    <td>{{ $exp->collector?->name ?? '—' }}</td>
                                    <td>{{ $exp->category?->name ?? '—' }}</td>
                                    <td><span class="isp-accounts-pill">{{ $exp->status }}</span></td>
                                    <td class="text-right font-semibold">{{ number_format($exp->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
