<x-filament-panels::page>
    @php
        $links = [
            ['eyebrow' => 'Hub', 'label' => 'Support center', 'hint' => 'Full tools & SLA table', 'url' => \App\Filament\Pages\SupportHub::getUrl(), 'icon' => 'heroicon-o-lifebuoy'],
            ['eyebrow' => 'Queue', 'label' => 'All tickets', 'hint' => 'Filter & assign', 'url' => \App\Filament\Resources\SupportTicketResource::getUrl('index'), 'icon' => 'heroicon-o-queue-list'],
            ['eyebrow' => 'Tasks', 'label' => 'Kanban board', 'hint' => 'Technician jobs', 'url' => \App\Filament\Pages\TaskKanbanBoard::getUrl(), 'icon' => 'heroicon-o-view-columns'],
        ];
    @endphp

    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            eyebrow="Support operations"
            title="Support dashboard"
            description="Tickets, SLA breaches, escalations and technician workload — live for support & call center."
            class="isp-hub-hero--amber"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Support shortcuts</h2>
                    <p class="isp-hub-section__desc">Jump into queue triage, SLA drilldown, and field-task coordination from one place.</p>
                </div>
                <span class="isp-hub-section__meta">Ops focus</span>
            </div>
            <div class="isp-hub-link-grid isp-hub-link-grid--2 isp-hub-link-grid--3">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="isp-module-card group">
                        <div class="flex items-start gap-3">
                            <span class="isp-module-icon text-amber-600">
                                <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="isp-module-card__eyebrow">{{ $link['eyebrow'] }}</p>
                                <p class="isp-module-card__title">{{ $link['label'] }}</p>
                                <p class="isp-module-card__desc">{{ $link['hint'] }}</p>
                            </div>
                            <span class="isp-module-card__arrow" aria-hidden="true">→</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
