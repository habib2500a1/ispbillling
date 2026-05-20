@auth
    <footer class="isp-sidebar-footer" x-data>
        <button
            type="button"
            class="isp-sidebar-footer__toggle"
            x-show="$store.sidebar.isOpen"
            x-on:click.stop="$store.sidebar.close()"
            title="Collapse sidebar"
            aria-label="Collapse sidebar menu"
        >
            <x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5 shrink-0" />
            <span>Collapse menu</span>
        </button>
        <button
            type="button"
            class="isp-sidebar-footer__toggle isp-sidebar-footer__toggle--expand"
            x-show="! $store.sidebar.isOpen"
            x-on:click.stop="$store.sidebar.open()"
            title="Expand sidebar"
            aria-label="Expand sidebar menu"
        >
            <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 shrink-0" />
            <span class="sr-only">Expand menu</span>
        </button>
    </footer>
@endauth
