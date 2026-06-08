/**
 * Billing invoice list v2 — view toggle, mobile cards, sidebar compact.
 */
(function () {
    'use strict';

    const STORAGE_VIEW = 'isp-billing-inv-view';
    const STORAGE_SIDEBAR = 'isp-billing-sidebar-compact';

    function initViewToggle(root) {
        if (!root) return;

        const buttons = root.querySelectorAll('[data-bl-view]');
        const saved = localStorage.getItem(STORAGE_VIEW) || 'table';

        root.setAttribute('data-view', saved);
        buttons.forEach((btn) => {
            btn.classList.toggle('bl-inv-view-toggle__btn--active', btn.dataset.blView === saved);
            btn.addEventListener('click', () => {
                const view = btn.dataset.blView || 'table';
                root.setAttribute('data-view', view);
                localStorage.setItem(STORAGE_VIEW, view);
                buttons.forEach((b) => b.classList.toggle('bl-inv-view-toggle__btn--active', b === btn));
                if (view === 'cards') {
                    buildMobileCards(root);
                }
            });
        });

        if (saved === 'cards') {
            buildMobileCards(root);
        }
    }

    function buildMobileCards(root) {
        const container = root.querySelector('[data-bl-mobile-cards]');
        const table = root.querySelector('.fi-ta-table tbody');
        if (!container || !table) return;

        container.innerHTML = '';
        container.removeAttribute('aria-hidden');

        const rows = table.querySelectorAll('tr');
        rows.forEach((row) => {
            const cells = row.querySelectorAll('td');
            if (cells.length < 4) return;

            const card = document.createElement('article');
            card.className = 'bl-inv-card';

            const invoiceNum = cells[0]?.textContent?.trim() || '—';
            const customer = cells[1]?.textContent?.trim() || '—';
            const total = cells[5]?.textContent?.trim() || '—';
            const due = cells[6]?.textContent?.trim() || '—';
            const statusCell = cells[9] || cells[cells.length - 2];
            const status = statusCell?.textContent?.trim().toLowerCase() || 'open';

            const badgeClass = status.includes('paid')
                ? 'bl-inv-card__badge--paid'
                : status.includes('partial')
                    ? 'bl-inv-card__badge--due'
                    : status.includes('draft')
                        ? 'bl-inv-card__badge--open'
                        : 'bl-inv-card__badge--due';

            const editLink = row.querySelector('a[href*="/edit"]')?.href || '#';
            const collectBtn = row.querySelector('button')?.textContent?.trim();

            card.innerHTML = `
                <div class="bl-inv-card__head">
                    <div>
                        <div class="bl-inv-card__number">${escapeHtml(invoiceNum)}</div>
                        <div class="bl-inv-card__customer">${escapeHtml(customer)}</div>
                    </div>
                    <span class="bl-inv-card__badge ${badgeClass}">${escapeHtml(status)}</span>
                </div>
                <div class="bl-inv-card__grid">
                    <div class="bl-inv-card__field"><label>Total</label><span>${escapeHtml(total)}</span></div>
                    <div class="bl-inv-card__field"><label>Due</label><span>${escapeHtml(due)}</span></div>
                </div>
                <div class="bl-inv-card__actions">
                    <a href="${editLink}" class="bl-inv-card__action">View</a>
                    ${collectBtn ? `<a href="${editLink}" class="bl-inv-card__action bl-inv-card__action--primary">Collect</a>` : ''}
                </div>
            `;

            container.appendChild(card);
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initSidebarCompact() {
        const compact = localStorage.getItem(STORAGE_SIDEBAR) === '1';
        document.body.classList.toggle('isp-billing-sidebar-compact', compact);

        let btn = document.querySelector('.bl-sidebar-toggle');
        if (!btn) {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bl-sidebar-toggle';
            btn.setAttribute('aria-label', 'Toggle compact sidebar');
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 013.75 8.25V6z"/></svg>';
            document.body.appendChild(btn);
        }

        btn.addEventListener('click', () => {
            const next = !document.body.classList.contains('isp-billing-sidebar-compact');
            document.body.classList.toggle('isp-billing-sidebar-compact', next);
            localStorage.setItem(STORAGE_SIDEBAR, next ? '1' : '0');
        });
    }

    function markBillingModule() {
        const path = window.location.pathname || '';
        if (/billing|invoices|bill-collection|payments-report|collection-desk/.test(path)) {
            document.body.classList.add('isp-billing-module');
        }
    }

    function boot() {
        markBillingModule();
        initSidebarCompact();
        document.querySelectorAll('.bl-pro').forEach(initViewToggle);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', () => {
        markBillingModule();
        document.querySelectorAll('.bl-pro').forEach(initViewToggle);
    });
})();
