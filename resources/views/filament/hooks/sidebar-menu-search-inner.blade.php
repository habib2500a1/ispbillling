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
            placeholder="Search menu… (e.g. clients, bills, bKash)"
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
    <p id="isp-sidebar-search-empty" class="isp-sidebar-search__empty" hidden>No menu match — try another word</p>
    <p class="isp-sidebar-search__hint" x-show="query.length === 0" x-cloak>All modules · tap a group to expand · Ctrl+/</p>
</div>
