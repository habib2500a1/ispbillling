<x-filament-widgets::widget>
    <style>
        #isp-today-snapshot-root .isp-today-strip{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:.75rem!important;width:100%!important;max-width:100%!important;overflow:hidden!important}
        #isp-today-snapshot-root .isp-today-tile{display:flex!important;flex-direction:row!important;align-items:center!important;gap:.7rem!important;overflow:hidden!important;min-width:0!important;max-width:100%!important}
        #isp-today-snapshot-root .isp-today-tile__icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:2.5rem!important;height:2.5rem!important;min-width:2.5rem!important;max-width:2.5rem!important;min-height:2.5rem!important;max-height:2.5rem!important;flex:0 0 2.5rem!important;overflow:hidden!important;line-height:0!important}
        #isp-today-snapshot-root svg.isp-today-tile__icon-svg{display:block!important;width:24px!important;height:24px!important;min-width:24px!important;min-height:24px!important;max-width:24px!important;max-height:24px!important;flex:0 0 24px!important;box-sizing:border-box!important}
        @media (max-width:1024px){#isp-today-snapshot-root .isp-today-strip{grid-template-columns:repeat(3,minmax(0,1fr))!important}}
        @media (max-width:640px){#isp-today-snapshot-root .isp-today-strip{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;-webkit-overflow-scrolling:touch;scroll-snap-type:x proximity;gap:.65rem!important}#isp-today-snapshot-root .isp-today-tile{flex:0 0 min(78%,280px)!important;max-width:280px!important;scroll-snap-align:start}}
    </style>
    <div id="isp-today-snapshot-root" class="isp-today-snapshot-wi">
        <div class="isp-today-strip" role="group" aria-label="Today at a glance">
            {!! $tilesHtml ?? '' !!}
        </div>
    </div>
</x-filament-widgets::widget>
