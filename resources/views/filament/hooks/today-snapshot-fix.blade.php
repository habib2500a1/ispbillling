{{-- Last in HEAD: today snapshot icon sizing (after split CSS bundles) --}}
@unless (request()->routeIs('filament.admin.auth.*'))
<style id="isp-today-snapshot-fix">
    #isp-today-snapshot-root,
    .fi-body .isp-today-snapshot-wi,
    .fi-body .isp-today-strip {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
    #isp-today-snapshot-root .isp-today-strip,
    .fi-body .isp-today-strip {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.75rem;
    }
    #isp-today-snapshot-root .isp-today-tile,
    .fi-body .isp-today-tile {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 0.7rem;
        overflow: hidden;
        max-width: 100%;
        min-width: 0;
    }
    #isp-today-snapshot-root .isp-today-tile__icon,
    .fi-body .isp-today-tile__icon {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 2.5rem !important;
        height: 2.5rem !important;
        min-width: 2.5rem !important;
        max-width: 2.5rem !important;
        min-height: 2.5rem !important;
        max-height: 2.5rem !important;
        flex: 0 0 2.5rem !important;
        overflow: hidden !important;
        line-height: 0;
    }
    #isp-today-snapshot-root svg.isp-today-tile__icon-svg,
    .fi-body svg.isp-today-tile__icon-svg {
        display: block !important;
        width: 24px !important;
        height: 24px !important;
        min-width: 24px !important;
        min-height: 24px !important;
        max-width: 24px !important;
        max-height: 24px !important;
        flex: 0 0 24px !important;
        box-sizing: border-box !important;
    }
    .fi-body svg.isp-today-tile__icon-svg[fill='none'] {
        fill: none !important;
        stroke: currentColor !important;
        stroke-width: 2px;
    }
    .fi-body svg.isp-today-tile__icon-svg[fill='currentColor'] {
        fill: currentColor !important;
        stroke: none !important;
    }
    .fi-body .isp-today-tile--emerald .isp-today-tile__icon { color: #059669 !important; }
    .fi-body .isp-today-tile--rose .isp-today-tile__icon { color: #e11d48 !important; }
    .fi-body .isp-today-tile--amber .isp-today-tile__icon { color: #d97706 !important; }
    .fi-body .isp-today-tile--slate .isp-today-tile__icon { color: #475569 !important; }
    .dark .fi-body .isp-today-tile--emerald .isp-today-tile__icon,
    [data-theme='dark'] .fi-body .isp-today-tile--emerald .isp-today-tile__icon { color: #34d399 !important; }
    .dark .fi-body .isp-today-tile--rose .isp-today-tile__icon,
    [data-theme='dark'] .fi-body .isp-today-tile--rose .isp-today-tile__icon { color: #fb7185 !important; }
    .dark .fi-body .isp-today-tile--amber .isp-today-tile__icon,
    [data-theme='dark'] .fi-body .isp-today-tile--amber .isp-today-tile__icon { color: #fbbf24 !important; }
    .dark .fi-body .isp-today-tile--slate .isp-today-tile__icon,
    [data-theme='dark'] .fi-body .isp-today-tile--slate .isp-today-tile__icon { color: #94a3b8 !important; }
    .fi-body .fi-wi-widget:has(.isp-today-snapshot-wi) > div {
        overflow: hidden !important;
        max-width: 100% !important;
    }
    @media (max-width: 1024px) {
        .fi-body .isp-today-strip {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 640px) {
        .fi-body .isp-today-strip {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x proximity;
            gap: 0.65rem;
            padding-bottom: 0.15rem;
        }
        .fi-body .isp-today-tile {
            flex: 0 0 min(78%, 280px);
            max-width: 280px;
            scroll-snap-align: start;
        }
    }
</style>
@endunless
