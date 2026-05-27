/**
 * Filter Filament admin sidebar groups/items by label text.
 * Expands matching groups while searching (user-friendly across all modules).
 */
(function () {
    function normalize(value) {
        return (value || '').toLowerCase().trim();
    }

    function expandGroupForSearch(group) {
        group.classList.add('isp-sidebar-accordion-open');
        const items = group.querySelector('.fi-sidebar-group-items');
        if (items) {
            items.style.display = 'flex';
            items.style.flexDirection = 'column';
        }
        const chevron = group.querySelector('.fi-sidebar-group-collapse-button');
        if (chevron) {
            chevron.classList.remove('-rotate-180');
        }
    }

    function filterSidebarMenu(query) {
        const nav = document.querySelector('.fi-sidebar-nav-groups');
        const empty = document.getElementById('isp-sidebar-search-empty');
        if (!nav) {
            return;
        }

        const q = normalize(query);
        const searching = q !== '';
        let visibleItems = 0;

        nav.querySelectorAll('.fi-sidebar-group').forEach((group) => {
            const groupLabel = group.querySelector('.fi-sidebar-group-label');
            const groupText = normalize(groupLabel?.textContent);
            let groupHasVisible = false;

            group.querySelectorAll('li.fi-sidebar-item').forEach((item) => {
                const itemText = normalize(item.querySelector('.fi-sidebar-item-label')?.textContent);
                const match = !searching || groupText.includes(q) || itemText.includes(q);

                item.classList.toggle('isp-sidebar-item--hidden', !match);
                item.style.display = match ? '' : 'none';

                if (match) {
                    groupHasVisible = true;
                    visibleItems++;
                }
            });

            group.classList.toggle('isp-sidebar-group--hidden', !groupHasVisible);
            group.style.display = groupHasVisible ? '' : 'none';

            if (searching && groupHasVisible) {
                expandGroupForSearch(group);
            }
        });

        if (empty) {
            empty.hidden = !searching || visibleItems > 0;
        }

        if (searching) {
            nav.classList.add('isp-sidebar-nav--searching');
        } else {
            nav.classList.remove('isp-sidebar-nav--searching');
            if (typeof window.__ispSidebarRestoreAccordion === 'function') {
                window.__ispSidebarRestoreAccordion();
            }
        }
    }

    window.ispFilterSidebarMenu = filterSidebarMenu;

    function focusMenuSearch() {
        const input = document.getElementById('isp-sidebar-menu-search');
        if (input) {
            setTimeout(() => {
                input.focus();
                input.select?.();
            }, 200);
        }
    }

    function isEditableTarget(target) {
        if (!target || !(target instanceof Element)) {
            return false;
        }

        const tag = target.tagName?.toLowerCase();

        return (
            tag === 'input'
            || tag === 'textarea'
            || tag === 'select'
            || target.isContentEditable
        );
    }

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === '/') {
            if (isEditableTarget(event.target)) {
                return;
            }

            event.preventDefault();
            focusMenuSearch();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        filterSidebarMenu('');

        document.addEventListener('livewire:navigated', () => {
            const input = document.getElementById('isp-sidebar-menu-search');
            filterSidebarMenu(input?.value || '');
        });
    });

    window.addEventListener('isp-focus-sidebar-menu-search', focusMenuSearch);
})();
