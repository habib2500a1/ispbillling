@props(['placeholder' => 'Search reports, customers, zones, tickets…'])

<div
    class="isp-bi-search"
    x-data="{ query: '' }"
    x-on:bi-search.window="query = $event.detail"
>
    <svg class="isp-bi-search__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
    </svg>
    <input
        type="search"
        class="isp-bi-search__input"
        placeholder="{{ $placeholder }}"
        x-model.debounce.250ms="query"
        x-on:input="$dispatch('bi-search', query)"
        autocomplete="off"
    />
</div>
