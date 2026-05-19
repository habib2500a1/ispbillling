<x-filament-panels::page class="isp-accounts-page">
    <div class="space-y-5">
        <section class="isp-accounts-hero">
            <div class="isp-accounts-hero__main">
                <p class="isp-accounts-hero__eyebrow">Accounts</p>
                <h2 class="isp-accounts-hero__title">Income</h2>
                <p class="isp-accounts-hero__sub">Completed subscriber payments in the selected period.</p>
            </div>
            <div class="isp-accounts-filters">
                <div>
                    <label for="inc-from">From</label>
                    <input id="inc-from" type="date" wire:model.live="dateFrom" class="isp-accounts-filters__input" />
                </div>
                <div>
                    <label for="inc-to">To</label>
                    <input id="inc-to" type="date" wire:model.live="dateTo" class="isp-accounts-filters__input" />
                </div>
                <div class="isp-accounts-stat isp-accounts-stat--income isp-accounts-stat--inline">
                    <span class="isp-accounts-stat__label">Total</span>
                    <strong>{{ number_format($this->totalIncome, 2) }} BDT</strong>
                </div>
            </div>
        </section>

        <section class="isp-accounts-table-card">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
