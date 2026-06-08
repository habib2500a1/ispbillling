/**
 * Support ticket list v3 — view toggle, mobile cards, sidebar compact.
 */
(function () {
    'use strict';

    const STORAGE_VIEW = 'isp-support-ticket-view';
    const STORAGE_SIDEBAR = 'isp-support-sidebar-compact';

    function initViewToggle(root) {
        const buttons = root.querySelectorAll('[data-sp-view]');
        const saved = localStorage.getItem(STORAGE_VIEW) || 'table';

        root.setAttribute('data-view', saved);
        buttons.forEach((btn) => {
            btn.classList.toggle('sp-view-toggle__btn--active', btn.dataset.spView === saved);
            btn.addEventListener('click', () => {
                const view = btn.dataset.spView || 'table';
                root.setAttribute('data-view', view);
                localStorage.setItem(STORAGE_VIEW, view);
                buttons.forEach((b) => b.classList.toggle('sp-view-toggle__btn--active', b === btn));
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
        const container = root.querySelector('[data-sp-mobile-cards]');
        const table = root.querySelector('.fi-ta-table tbody');
        if (!container || !table) return;

        container.innerHTML = '';

        table.querySelectorAll('tr').forEach((row) => {
            const cells = row.querySelectorAll('td');
            if (cells.length < 4) return;

            const ticketNum = cells[0]?.textContent?.trim() || '—';
            const customer = cells[1]?.textContent?.trim() || '—';
            const priority = (cells[3]?.textContent?.trim() || 'medium').toLowerCase();
            const status = (cells[4]?.textContent?.trim() || 'open').toLowerCase();
            const sla = cells[5]?.textContent?.trim() || '';
            const editLink = row.querySelector('a[href*="/edit"]')?.href
                || row.querySelector('a[href*="/view"]')?.href
                || '#';

            const priClass = priority.includes('critical')
                ? 'critical'
                : priority.includes('high')
                    ? 'high'
                    : priority.includes('low')
                        ? 'low'
                        : 'medium';

            const card = document.createElement('a');
            card.href = editLink;
            card.className = `sp-ticket-card sp-ticket-card--${priClass}`;
            card.innerHTML = `
                <div class="sp-ticket-card__head">
                    <div>
                        <div class="sp-ticket-card__number">${escapeHtml(ticketNum)}</div>
                        <div class="sp-ticket-card__subject">${escapeHtml(customer)}</div>
                    </div>
                    <span class="sp-priority-badge sp-priority-badge--${priClass}">${escapeHtml(priority)}</span>
                </div>
                <div class="sp-ticket-card__meta">
                    <span>${escapeHtml(status)}</span>
                    <span class="${sla.toLowerCase().includes('overdue') || sla.includes('-') ? 'sp-sla-badge--breach' : ''}">${escapeHtml(sla)}</span>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function initSidebarCompact() {
        const compact = localStorage.getItem(STORAGE_SIDEBAR) === '1';
        document.body.classList.toggle('isp-support-sidebar-compact', compact);

        let btn = document.querySelector('.sp-sidebar-toggle');
        if (!btn) {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sp-sidebar-toggle';
            btn.setAttribute('aria-label', 'Toggle compact support sidebar');
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 013.75 8.25V6z"/></svg>';
            document.body.appendChild(btn);
        }

        btn.addEventListener('click', () => {
            const next = !document.body.classList.contains('isp-support-sidebar-compact');
            document.body.classList.toggle('isp-support-sidebar-compact', next);
            localStorage.setItem(STORAGE_SIDEBAR, next ? '1' : '0');
        });
    }

    function markSupportModule() {
        const path = window.location.pathname || '';
        if (/support-hub|support-tickets|call-center|knowledge-articles|outages/.test(path)) {
            document.body.classList.add('isp-support-module');
        }
    }

    function bootRoot(root) {
        initViewToggle(root);
    }

    function boot() {
        markSupportModule();
        initSidebarCompact();
        document.querySelectorAll('.sp-pro').forEach(bootRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', () => {
        markSupportModule();
        document.querySelectorAll('.sp-pro').forEach(bootRoot);
    });
})();
