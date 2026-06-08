/**
 * Router / MikroTik NOC v2 — view toggle, mobile cards, sidebar compact.
 */
(function () {
    'use strict';

    const STORAGE_VIEW = 'isp-network-router-view';
    const STORAGE_SIDEBAR = 'isp-network-sidebar-compact';

    function initViewToggle(root) {
        if (!root) return;

        const buttons = root.querySelectorAll('[data-nr-view]');
        const saved = localStorage.getItem(STORAGE_VIEW) || 'table';

        root.setAttribute('data-view', saved);
        buttons.forEach((btn) => {
            btn.classList.toggle('nr-inv-view-toggle__btn--active', btn.dataset.nrView === saved);
            btn.addEventListener('click', () => {
                const view = btn.dataset.nrView || 'table';
                root.setAttribute('data-view', view);
                localStorage.setItem(STORAGE_VIEW, view);
                buttons.forEach((b) => b.classList.toggle('nr-inv-view-toggle__btn--active', b === btn));
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
        const container = root.querySelector('[data-nr-mobile-cards]');
        const table = root.querySelector('.fi-ta-table tbody');
        if (!container || !table) return;

        container.innerHTML = '';
        container.removeAttribute('aria-hidden');

        table.querySelectorAll('tr').forEach((row) => {
            const cells = row.querySelectorAll('td');
            if (cells.length < 3) return;

            const name = cells[0]?.textContent?.trim() || '—';
            const host = cells[1]?.textContent?.trim() || '—';
            const subs = cells[3]?.textContent?.trim() || '—';
            const status = (cells[4]?.textContent?.trim() || 'unknown').toLowerCase();
            const statusClass = status.includes('online') ? 'online' : status.includes('offline') ? 'offline' : 'unknown';

            const card = document.createElement('article');
            card.className = 'nr-router-card';
            card.innerHTML =
                '<div class="nr-router-card__head">' +
                '<div class="nr-router-card__icon" aria-hidden="true">⬡</div>' +
                '<div><strong>' + escapeHtml(name) + '</strong><div style="font-size:0.78rem;color:var(--nr-muted);">' + escapeHtml(host) + '</div></div>' +
                '<span class="nr-router-card__status nr-router-card__status--' + statusClass + '">' + escapeHtml(status) + '</span>' +
                '</div>' +
                '<div style="margin-top:0.65rem;font-size:0.78rem;color:var(--nr-muted);">Subscribers: ' + escapeHtml(subs) + '</div>';

            const actionCell = cells[cells.length - 1];
            if (actionCell) {
                const clone = actionCell.cloneNode(true);
                clone.style.marginTop = '0.65rem';
                card.appendChild(clone);
            }

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
        const stored = localStorage.getItem(STORAGE_SIDEBAR);
        if (stored === '1') {
            document.body.classList.add('isp-sidebar-compact');
        }
    }

    function boot() {
        document.querySelectorAll('.nr-pro[data-network-list]').forEach(initViewToggle);
        initSidebarCompact();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', boot);
})();
