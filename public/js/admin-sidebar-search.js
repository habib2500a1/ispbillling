/**
 * Filter Filament admin sidebar groups/items by label text.
 */
(function () {
    function normalize(value) {
        return (value || '').toLowerCase().trim();
    }

    function filterSidebarMenu(query) {
        const nav = document.querySelector('.fi-sidebar-nav-groups');
        const empty = document.getElementById('isp-sidebar-search-empty');
        if (!nav) {
            return;
        }

        const q = normalize(query);
        let visibleItems = 0;

        nav.querySelectorAll('.fi-sidebar-group').forEach((group) => {
            const groupLabel = group.querySelector('.fi-sidebar-group-label');
            const groupText = normalize(groupLabel?.textContent);
            let groupHasVisible = false;

            group.querySelectorAll('li.fi-sidebar-item').forEach((item) => {
                const itemText = normalize(item.querySelector('.fi-sidebar-item-label')?.textContent);
                const match = q === '' || groupText.includes(q) || itemText.includes(q);

                item.classList.toggle('isp-sidebar-item--hidden', !match);
                item.style.display = match ? '' : 'none';

                if (match) {
                    groupHasVisible = true;
                    visibleItems++;
                }
            });

            group.classList.toggle('isp-sidebar-group--hidden', !groupHasVisible);
            group.style.display = groupHasVisible ? '' : 'none';
        });

        if (empty) {
            empty.hidden = q === '' || visibleItems > 0;
        }
    }

    window.ispFilterSidebarMenu = filterSidebarMenu;

    function focusMenuSearch() {
        const input = document.getElementById('isp-sidebar-menu-search');
        if (input) {
            setTimeout(() => input.focus(), 200);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        filterSidebarMenu('');

        document.addEventListener('livewire:navigated', () => {
            const input = document.getElementById('isp-sidebar-menu-search');
            filterSidebarMenu(input?.value || '');
        });
    });

    window.addEventListener('isp-focus-sidebar-menu-search', focusMenuSearch);
})();
