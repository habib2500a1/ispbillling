<div
    x-data="{
        open: false,
        q: '',
        items: @js($commandItems ?? []),
        entityResults: [],
        searching: false,
        searchError: '',
        searchUrl: @js(route('admin.smart-search')),
        get filtered() {
            const s = this.q.toLowerCase().trim();
            const match = (item) => {
                if (!s) return true;
                if (item.label.toLowerCase().includes(s)) return true;
                if ((item.group || '').toLowerCase().includes(s)) return true;
                if (Array.isArray(item.keywords) && item.keywords.some(k => k.includes(s) || s.includes(k))) return true;
                return false;
            };
            return !s ? this.items.slice(0, 10) : this.items.filter(match).slice(0, 12);
        },
        async searchEntities() {
            if (this.q.length < 2) { this.entityResults = []; this.searchError = ''; return; }
            this.searching = true;
            this.searchError = '';
            try {
                const r = await fetch(this.searchUrl + '?q=' + encodeURIComponent(this.q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!r.ok) {
                    this.entityResults = [];
                    this.searchError = 'Search failed (' + r.status + ')';
                    return;
                }
                const j = await r.json();
                this.entityResults = j.results || [];
            } catch (e) {
                this.entityResults = [];
                this.searchError = 'Search unavailable';
            }
            this.searching = false;
        },
    }"
    @isp-open-command-palette.window="open = true; q = ''; entityResults = []; searchError = ''; $nextTick(() => $refs.search?.focus())"
    @keydown.escape.window="open = false"
>
    <template x-if="open">
        <div class="isp-cmd-palette" @click.self="open = false">
            <div class="isp-cmd-panel isp-cmd-panel--wide" role="dialog" aria-label="Smart search">
                <input
                    x-ref="search"
                    type="text"
                    class="isp-cmd-input text-gray-900 dark:text-white"
                    placeholder="ID, name, phone, address, PPP user, invoice…"
                    x-model="q"
                    @input.debounce.300ms="searchEntities()"
                    @keydown.escape="open = false"
                />
                <div class="max-h-96 overflow-y-auto py-1">
                    <template x-if="entityResults.length > 0">
                        <div>
                            <p class="px-4 py-1 text-xs font-bold uppercase text-gray-400">Subscribers & records</p>
                            <template x-for="item in entityResults" :key="item.url + (item.view_url || '')">
                                <div class="isp-cmd-item text-gray-800 dark:text-gray-200">
                                    <span class="text-xs uppercase text-teal-600" x-text="item.type"></span>
                                    <span class="block font-medium" x-text="item.label"></span>
                                    <span class="text-xs text-gray-500" x-text="item.sublabel"></span>
                                    <div class="mt-2 flex flex-wrap gap-2" x-show="item.view_url">
                                        <a
                                            :href="item.view_url"
                                            class="rounded-md bg-teal-600 px-2 py-1 text-xs font-semibold text-white hover:bg-teal-700"
                                            @click="open = false"
                                        >View</a>
                                        <a
                                            :href="item.edit_url"
                                            class="rounded-md border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                            @click="open = false"
                                        >Edit</a>
                                        <a
                                            :href="item.pay_url"
                                            class="rounded-md bg-violet-600 px-2 py-1 text-xs font-semibold text-white hover:bg-violet-700"
                                            @click="open = false"
                                        >Collect payment</a>
                                    </div>
                                    <a
                                        x-show="!item.view_url"
                                        :href="item.url"
                                        class="mt-1 inline-block text-xs font-semibold text-teal-600"
                                        @click="open = false"
                                    >Open</a>
                                </div>
                            </template>
                        </div>
                    </template>
                    <p class="px-4 py-1 text-xs font-bold uppercase text-gray-400">Pages</p>
                    <template x-for="item in filtered" :key="item.url">
                        <a :href="item.url" class="isp-cmd-item text-gray-800 dark:text-gray-200" @click="open = false">
                            <span class="text-xs uppercase text-gray-400" x-text="item.group"></span>
                            <span class="block font-medium" x-text="item.label"></span>
                        </a>
                    </template>
                    <p x-show="searchError" class="px-4 py-2 text-sm text-rose-600" x-text="searchError"></p>
                    <p x-show="filtered.length === 0 && entityResults.length === 0 && !searching && !searchError && q.length >= 2" class="px-4 py-6 text-sm text-gray-500">No matches.</p>
                    <p x-show="searching" class="px-4 py-4 text-sm text-gray-500">Searching…</p>
                </div>
            </div>
        </div>
    </template>
</div>
