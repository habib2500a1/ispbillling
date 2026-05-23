/**
 * Admin sidebar accordion (legacy ISP style): one category open at a time.
 * Loads at end of body so Alpine + Filament sidebar store exist.
 */
(function () {
    const OPEN_GROUP_KEY = 'isp-sidebar-open-group';
    const COLLAPSED_STORAGE_KEY = 'collapsedGroups';

    /** Must match AdminPanelProvider::navigationGroups() order */
    const SIDEBAR_GROUP_ORDER = [
        'Overview',
        'Clients',
        'Billing',
        'Payments',
        'Inventory Pro',
        'OLT & Tools',
        'Network',
        'SMS Service',
        'Support',
        'Reports',
        'BW Client',
        'HRM',
        'Resellers',
        'Accounts',
        'Settings',
        'System',
    ];

    function groupsRoot() {
        return document.querySelector('.fi-main-sidebar .fi-sidebar-nav-groups');
    }

    function removeRetiredSidebarGroups() {
        const root = groupsRoot();
        if (!root) {
            return;
        }

        mergeLegacyOltSidebarGroup(root);
        moveOltLinksOutOfInventoryPro(root);
    }

    /** Move OLT pages out of Inventory Pro (legacy cache / old registry). */
    function moveOltLinksOutOfInventoryPro(root) {
        const inventory = root.querySelector(':scope > .fi-sidebar-group[data-group-label="Inventory Pro"]');
        const target = root.querySelector(':scope > .fi-sidebar-group[data-group-label="OLT & Tools"]');

        if (!inventory || !target) {
            return;
        }

        const inventoryList = inventory.querySelector('.fi-sidebar-group-items');
        const targetList = target.querySelector('.fi-sidebar-group-items');

        if (!inventoryList || !targetList) {
            return;
        }

        const oltPath = /\/olts|olt-hub|optical-noc|olt-mac-table|optical-laser-settings|network-topology/;

        [...inventoryList.querySelectorAll('.fi-sidebar-item')].forEach((item) => {
            const anchor = item.querySelector('a[href]');
            const href = anchor?.getAttribute('href') || '';
            const text = (anchor?.textContent || '').trim().toLowerCase();

            const isOlt =
                oltPath.test(href)
                || text === 'olts'
                || text === 'olt'
                || text === 'olt list'
                || text === 'optical database';

            if (isOlt) {
                targetList.appendChild(item);
            }
        });
    }

    /** Collapse duplicate bare «OLT» group into «OLT & Tools». */
    function mergeLegacyOltSidebarGroup(root) {
        const legacy = root.querySelector(':scope > .fi-sidebar-group[data-group-label="OLT"]');
        const target = root.querySelector(':scope > .fi-sidebar-group[data-group-label="OLT & Tools"]');

        if (!legacy) {
            return;
        }

        if (target) {
            const legacyList = legacy.querySelector('.fi-sidebar-group-items');
            const targetList = target.querySelector('.fi-sidebar-group-items');
            if (legacyList && targetList) {
                [...legacyList.children].forEach((node) => targetList.appendChild(node));
            }
            legacy.remove();

            return;
        }

        legacy.dataset.groupLabel = 'OLT & Tools';
        const labelEl = legacy.querySelector('.fi-sidebar-group-label');
        if (labelEl) {
            labelEl.textContent = 'OLT & Tools';
        }
    }

    function reorderSidebarGroups() {
        const root = groupsRoot();
        if (!root) {
            return;
        }

        removeRetiredSidebarGroups();

        const groups = [...root.querySelectorAll(':scope > .fi-sidebar-group[data-group-label]')];

        groups.sort((a, b) => {
            const ai = SIDEBAR_GROUP_ORDER.indexOf(a.dataset.groupLabel);
            const bi = SIDEBAR_GROUP_ORDER.indexOf(b.dataset.groupLabel);

            return (ai === -1 ? 999 : ai) - (bi === -1 ? 999 : bi);
        });

        groups.forEach((group) => root.appendChild(group));
    }

    function groupLabels() {
        return [...document.querySelectorAll('.fi-main-sidebar .fi-sidebar-group[data-group-label]')]
            .map((el) => el.dataset.groupLabel)
            .filter(Boolean);
    }

    function sidebarStore() {
        return window.Alpine?.store?.('sidebar');
    }

    function activeGroupLabel() {
        const item = document.querySelector('.fi-main-sidebar .fi-sidebar-item.fi-active');

        return item?.closest('.fi-sidebar-group[data-group-label]')?.dataset?.groupLabel ?? null;
    }

    function readRememberedOpenGroup() {
        try {
            const label = sessionStorage.getItem(OPEN_GROUP_KEY);
            const labels = groupLabels();

            return label && labels.includes(label) ? label : null;
        } catch (e) {
            return null;
        }
    }

    function rememberOpenGroup(label) {
        try {
            if (label) {
                sessionStorage.setItem(OPEN_GROUP_KEY, label);
            } else {
                sessionStorage.removeItem(OPEN_GROUP_KEY);
            }
        } catch (e) {
            /* ignore */
        }
    }

    function syncStore(openLabel) {
        const store = sidebarStore();
        const all = groupLabels();
        if (!store || !all.length) {
            return false;
        }

        if (!Array.isArray(store.collapsedGroups)) {
            store.collapsedGroups = [];
        }

        store.collapsedGroups = openLabel
            ? all.filter((label) => label !== openLabel)
            : [...all];

        try {
            localStorage.setItem(COLLAPSED_STORAGE_KEY, JSON.stringify(store.collapsedGroups));
        } catch (e) {
            /* ignore */
        }

        rememberOpenGroup(openLabel);

        return true;
    }

    function applyDomState(openLabel) {
        document.querySelectorAll('.fi-main-sidebar .fi-sidebar-group[data-group-label]').forEach((group) => {
            const label = group.dataset.groupLabel;
            const open = Boolean(openLabel && label === openLabel);
            const items = group.querySelector('.fi-sidebar-group-items');
            const chevron = group.querySelector('.fi-sidebar-group-collapse-button');

            group.classList.toggle('isp-sidebar-accordion-open', open);

            if (items) {
                items.style.display = open ? '' : 'none';
            }

            if (chevron) {
                chevron.classList.toggle('-rotate-180', !open);
            }
        });
    }

    function pathSuggestsClientsGroup() {
        const path = window.location.pathname || '';

        return (
            path.includes('/subscribers')
            || path.includes('clients-hub')
            || path.includes('online-clients-monitoring')
            || path.includes('import-clients-csv')
        );
    }

    function pathSuggestsPaymentsGroup() {
        const path = window.location.pathname || '';

        return (
            path.includes('personal-mfs')
            || path.includes('mfs-sms')
            || path.includes('payment-gateway')
            || path.includes('pending-gateway')
            || path.includes('mfs-sms-records')
            || path.includes('/payments')
        );
    }

    function pathSuggestsBillingGroup() {
        const path = window.location.pathname || '';

        return (
            path.includes('billing-fund-flow')
            || path.includes('bill-collection')
            || path.includes('staff-expenses')
            || path.includes('collection-desk')
            || path.includes('/invoices')
            || path.includes('manage-collection-discount')
            || path.includes('collector-mobile')
            || path.includes('collector-visits')
        );
    }

    function pathSuggestsOltGroup() {
        const path = window.location.pathname || '';

        return (
            path.includes('olt-hub')
            || path.includes('/olts')
            || path.includes('optical-noc')
            || path.includes('olt-mac-table')
            || path.includes('optical-laser-settings')
        );
    }

    function pathSuggestsSupportGroup() {
        const path = window.location.pathname || '';

        return (
            path.includes('sales-leads')
            || path.includes('sales-lead-pipeline')
            || path.includes('support-tickets')
            || path.includes('broadcast-outage')
            || path.includes('/outages')
            || path.includes('knowledge-articles')
            || path.includes('support-assignment-rules')
        );
    }

    function preferredOpenLabel() {
        const labels = groupLabels();
        if (!labels.length) {
            return null;
        }

        // OLT pages: always open «OLT & Tools», never Inventory Pro (legacy OLTs link cache).
        if (pathSuggestsOltGroup()) {
            try {
                sessionStorage.setItem(OPEN_GROUP_KEY, 'OLT & Tools');
            } catch (e) {
                /* ignore */
            }

            if (labels.includes('OLT & Tools')) {
                return 'OLT & Tools';
            }

            if (labels.includes('OLT')) {
                return 'OLT';
            }
        }

        const active = activeGroupLabel();

        if (active === 'Inventory Pro' && pathSuggestsOltGroup() && labels.includes('OLT & Tools')) {
            return 'OLT & Tools';
        }

        if (active) {
            return active;
        }

        if (pathSuggestsClientsGroup() && labels.includes('Clients')) {
            return 'Clients';
        }

        if (pathSuggestsBillingGroup() && labels.includes('Billing')) {
            return 'Billing';
        }

        if (pathSuggestsPaymentsGroup() && labels.includes('Payments')) {
            return 'Payments';
        }

        if (pathSuggestsSupportGroup() && labels.includes('Support')) {
            return 'Support';
        }

        const remembered = readRememberedOpenGroup();
        if (remembered) {
            return remembered;
        }

        if (labels.includes('Clients')) {
            return 'Clients';
        }

        if (labels.includes('Overview')) {
            return 'Overview';
        }

        return labels[0] ?? null;
    }

    function resolveOpenLabel(preferred) {
        const labels = groupLabels();
        if (!labels.length) {
            return null;
        }

        if (preferred && labels.includes(preferred)) {
            return preferred;
        }

        return preferredOpenLabel();
    }

    function scrollActiveItemIntoView() {
        const item = document.querySelector('.fi-main-sidebar .fi-sidebar-item.fi-active');
        if (item && typeof item.scrollIntoView === 'function') {
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function applyAccordion(preferred) {
        const openLabel = resolveOpenLabel(preferred);
        syncStore(openLabel);
        applyDomState(openLabel);

        return openLabel;
    }

    function patchToggle() {
        const store = sidebarStore();
        if (!store || store.__ispAccordionPatched) {
            return;
        }

        store.__ispAccordionPatched = true;

        store.toggleCollapsedGroup = function (group) {
            const all = groupLabels();
            const isCollapsed = this.collapsedGroups?.includes(group);

            if (isCollapsed) {
                applyAccordion(group);
            } else {
                applyAccordion(null);
            }
        };
    }

    function bindClicks() {
        const root = groupsRoot();
        if (!root || root.__ispAccordionClick) {
            return;
        }

        root.__ispAccordionClick = true;

        root.addEventListener(
            'click',
            (event) => {
                const trigger = event.target.closest(
                    '.fi-sidebar-group-button, .fi-sidebar-group-collapse-button',
                );
                if (!trigger) {
                    return;
                }

                const group = trigger.closest('.fi-sidebar-group[data-group-label]');
                const label = group?.dataset?.groupLabel;
                if (!label) {
                    return;
                }

                const wasOpen = group.classList.contains('isp-sidebar-accordion-open');

                window.setTimeout(() => {
                    if (wasOpen) {
                        applyAccordion(null);
                    } else {
                        applyAccordion(label);
                    }
                }, 0);
            },
            true,
        );
    }

    function init() {
        const root = groupsRoot();
        if (!root) {
            return false;
        }

        patchToggle();
        bindClicks();
        reorderSidebarGroups();
        moveOltLinksOutOfInventoryPro(root);
        applyAccordion(preferredOpenLabel());
        scrollActiveItemIntoView();

        return true;
    }

    function waitForSidebar(attempts) {
        if (init()) {
            return;
        }

        if (attempts < 40) {
            window.setTimeout(() => waitForSidebar(attempts + 1), 100);
        }
    }

    document.addEventListener('alpine:init', () => {
        window.setTimeout(() => waitForSidebar(0), 0);
    });

    document.addEventListener('livewire:navigated', () => {
        window.setTimeout(() => waitForSidebar(0), 50);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => waitForSidebar(0));
    } else {
        waitForSidebar(0);
    }
})();
