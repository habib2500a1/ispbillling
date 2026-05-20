<div
    class="isp-sidebar-search"
    x-show="$store.sidebar.isOpen"
    x-collapse
    x-data="{
        query: '',
        clear() {
            this.query = '';
            window.ispFilterSidebarMenu?.('');
        },
    }"
    x-init="$watch('query', (v) => window.ispFilterSidebarMenu?.(v))"
>
    <label class="sr-only" for="isp-sidebar-menu-search">Search menu</label>
    <div class="isp-sidebar-search__field">
        <x-filament::icon icon="heroicon-m-magnifying-glass" class="isp-sidebar-search__icon h-5 w-5" />
        <input
            id="isp-sidebar-menu-search"
            type="search"
            x-model.debounce.120ms="query"
            class="isp-sidebar-search__input"
            placeholder="Menu search…"
            autocomplete="off"
            spellcheck="false"
        />
        <button
            type="button"
            class="isp-sidebar-search__clear"
            x-show="query.length > 0"
            x-cloak
            @click="clear()"
            aria-label="Clear menu search"
        >&times;</button>
    </div>
    <p id="isp-sidebar-search-empty" class="isp-sidebar-search__empty" hidden>No menu items match</p>
</div>
