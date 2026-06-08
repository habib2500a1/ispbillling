/**
 * AI Operations Copilot — background chat + KPI refresh (no page reload).
 */
(function () {
    'use strict';

    var WELCOME = 'ISP Operations Copilot ready. Ask about billing, NOC, tickets, inventory, HR, or GIS. I analyze and recommend — I never change data without your approval.';
    var session = {};
    var busy = false;
    var pollTimer = null;
    var pollIntervalMs = 180000;
    var maxMessages = 24;
    var dashboardRefreshing = false;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.content : '';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function shell() {
        return document.querySelector('[data-ai-copilot]');
    }

    function messagesEl() {
        return document.querySelector('[data-ai-messages]');
    }

    function scrollMessages() {
        var box = messagesEl();
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    }

    function renderCards(cards) {
        if (!cards || !cards.length) {
            return '';
        }

        return '<div class="ai-insight-cards">' + cards.map(function (card) {
            var tone = card.tone || 'indigo';

            return '<div class="ai-insight-card ai-insight-card--' + escapeHtml(tone) + '">'
                + '<span class="ai-insight-card__title">' + escapeHtml(card.title || '') + '</span>'
                + '<strong>' + escapeHtml(card.value || '') + '</strong>'
                + (card.hint ? '<span class="ai-insight-card__hint">' + escapeHtml(card.hint) + '</span>' : '')
                + '</div>';
        }).join('') + '</div>';
    }

    function renderTable(table) {
        if (!table || !table.rows || !table.rows.length) {
            return '';
        }

        var headers = (table.headers || []).map(function (h) {
            return '<th>' + escapeHtml(h) + '</th>';
        }).join('');

        var rows = table.rows.map(function (row) {
            return '<tr>' + row.map(function (cell) {
                return '<td>' + escapeHtml(cell) + '</td>';
            }).join('') + '</tr>';
        }).join('');

        return '<div class="ai-table-wrap"><table class="ai-table"><thead><tr>'
            + headers
            + '</tr></thead><tbody>'
            + rows
            + '</tbody></table></div>';
    }

    function renderLinks(links) {
        if (!links || !links.length) {
            return '';
        }

        return '<div class="ai-msg__links">' + links.map(function (link) {
            return '<a href="' + escapeHtml(link.url || '#') + '" class="ai-link-btn">' + escapeHtml(link.label || 'Open') + '</a>';
        }).join('') + '</div>';
    }

    function renderMessage(msg) {
        var role = msg.role === 'user' ? 'user' : 'assistant';

        return '<article class="ai-msg ai-msg--' + role + '">'
            + '<div class="ai-msg__bubble">'
            + '<p>' + escapeHtml(msg.text || '') + '</p>'
            + renderCards(msg.cards)
            + renderTable(msg.table)
            + renderLinks(msg.links)
            + '</div>'
            + '</article>';
    }

    function appendMessage(msg) {
        var box = messagesEl();
        if (!box) {
            return;
        }

        box.insertAdjacentHTML('beforeend', renderMessage(msg));
        while (box.children.length > maxMessages) {
            box.removeChild(box.firstElementChild);
        }
        scrollMessages();
    }

    function setTyping(active) {
        var box = messagesEl();
        if (!box) {
            return;
        }

        var el = box.querySelector('[data-ai-typing]');
        if (active) {
            if (!el) {
                box.insertAdjacentHTML('beforeend', '<div class="ai-typing" data-ai-typing><span></span><span></span><span></span></div>');
            }
        } else if (el) {
            el.remove();
        }

        scrollMessages();
    }

    function setBusy(state) {
        busy = state;
        var send = document.querySelector('[data-ai-send]');
        var input = document.querySelector('[data-ai-input]');
        if (send) {
            send.disabled = state;
            send.classList.toggle('ai-send-btn--busy', state);
        }
        if (input) {
            input.disabled = state;
        }
        setTyping(state);
    }

    async function fetchJson(url, options) {
        var res = await fetch(url, Object.assign({
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        }, options || {}));

        if (!res.ok) {
            throw new Error('request failed');
        }

        return res.json();
    }

    async function ask(query) {
        var root = shell();
        if (!root || busy) {
            return;
        }

        var askUrl = root.getAttribute('data-ask-url');
        if (!askUrl) {
            return;
        }

        var trimmed = String(query || '').trim();
        if (trimmed === '') {
            return;
        }

        appendMessage({ role: 'user', text: trimmed });
        setBusy(true);

        try {
            var data = await fetchJson(askUrl, {
                method: 'POST',
                body: JSON.stringify({ query: trimmed, session: session }),
            });

            session = data.session && typeof data.session === 'object' ? data.session : session;

            appendMessage({
                role: 'assistant',
                text: data.reply || '',
                cards: data.cards || [],
                table: data.table || null,
                links: data.links || [],
            });
        } catch (err) {
            appendMessage({
                role: 'assistant',
                text: 'Could not reach the copilot. Please try again.',
            });
        } finally {
            setBusy(false);
        }
    }

    function formatKpi(key, value) {
        if (key === 'collected_today') {
            return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' BDT';
        }
        if (key === 'network_health') {
            return String(parseInt(value || 0, 10)) + '/100';
        }

        return String(value ?? 0);
    }

    function updateKpis(summary) {
        if (!summary) {
            return;
        }

        document.querySelectorAll('[data-ai-kpi]').forEach(function (el) {
            var key = el.getAttribute('data-ai-kpi');
            if (key && Object.prototype.hasOwnProperty.call(summary, key)) {
                el.textContent = formatKpi(key, summary[key]);
            }
        });
    }

    function updateAlerts(alerts) {
        var countEl = document.querySelector('[data-ai-alert-count]');
        var listEl = document.querySelector('[data-ai-alerts-list]');
        var count = Array.isArray(alerts) ? alerts.length : 0;

        if (countEl) {
            countEl.textContent = String(count);
            countEl.hidden = count === 0;
        }

        if (!listEl) {
            return;
        }

        if (count === 0) {
            listEl.innerHTML = '<p class="ai-empty">No active alerts.</p>';

            return;
        }

        listEl.innerHTML = alerts.map(function (alert) {
            var severity = alert.severity || 'medium';

            return '<article class="ai-alert-row ai-alert-row--' + escapeHtml(severity) + '">'
                + '<span class="ai-alert-row__domain">' + escapeHtml(alert.domain || 'ops') + '</span>'
                + '<strong>' + escapeHtml(alert.title || '') + '</strong>'
                + '<span class="ai-alert-row__hint">' + escapeHtml(alert.hint || '') + '</span>'
                + (alert.url ? '<a href="' + escapeHtml(alert.url) + '" class="ai-alert-row__link">Open →</a>' : '')
                + '</article>';
        }).join('');
    }

    async function refreshDashboard(opts) {
        var root = shell();
        if (!root || dashboardRefreshing) {
            return;
        }

        if (document.hidden && (!opts || !opts.force)) {
            return;
        }

        var dashboardUrl = root.getAttribute('data-dashboard-url');
        if (!dashboardUrl) {
            return;
        }

        dashboardRefreshing = true;
        try {
            var data = await fetchJson(dashboardUrl, { method: 'GET' });
            updateKpis(data.summary || {});
            updateAlerts(data.alerts || []);
        } catch (err) {
            if (!opts || !opts.quiet) {
                console.warn('AI dashboard refresh failed', err);
            }
        } finally {
            dashboardRefreshing = false;
        }
    }

    function startPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        pollTimer = setInterval(function () {
            if (!document.hidden) {
                refreshDashboard({ quiet: true });
            }
        }, pollIntervalMs);
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function initChat() {
        var root = shell();
        if (!root || root.getAttribute('data-ai-chat-ready') === '1') {
            return;
        }

        root.setAttribute('data-ai-chat-ready', '1');

        var box = messagesEl();
        if (box && box.childElementCount === 0) {
            appendMessage({ role: 'assistant', text: WELCOME });
        }

        var form = document.querySelector('[data-ai-composer]');
        if (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var input = document.querySelector('[data-ai-input]');
                if (!input) {
                    return;
                }
                var value = input.value;
                input.value = '';
                ask(value);
            });
        }

        document.querySelectorAll('[data-ai-chip]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                ask(btn.getAttribute('data-ai-chip') || btn.textContent || '');
            });
        });

        var clearBtn = document.querySelector('[data-ai-clear]');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                session = {};
                var messages = messagesEl();
                if (messages) {
                    messages.innerHTML = '';
                }
                appendMessage({ role: 'assistant', text: 'Conversation cleared. How can I help?' });
            });
        }

        if (pollTimer) {
            clearInterval(pollTimer);
        }
        startPoll();
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPoll();
            } else {
                refreshDashboard({ quiet: true, force: true });
                startPoll();
            }
        }, { passive: true });

        refreshDashboard({ quiet: true, force: true });
    }

    function initTheme() {
        var root = document.querySelector('.ai-copilot-module') || document.body;
        var stored = localStorage.getItem('ai-copilot-theme');
        if (stored === 'light') {
            root.classList.add('ai-theme-light');
        }
        document.querySelectorAll('[data-ai-theme-toggle]').forEach(function (btn) {
            if (btn.getAttribute('data-ai-theme-ready') === '1') {
                return;
            }
            btn.setAttribute('data-ai-theme-ready', '1');
            btn.addEventListener('click', function () {
                root.classList.toggle('ai-theme-light');
                localStorage.setItem('ai-copilot-theme', root.classList.contains('ai-theme-light') ? 'light' : 'dark');
            });
        });
    }

    function initVoicePlaceholder() {
        document.querySelectorAll('[data-ai-voice]').forEach(function (btn) {
            if (btn.getAttribute('data-ai-voice-ready') === '1') {
                return;
            }
            btn.setAttribute('data-ai-voice-ready', '1');
            btn.addEventListener('click', function () {
                var input = document.querySelector('[data-ai-input]');
                if (!input) {
                    return;
                }
                if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                    input.placeholder = 'Voice input not supported in this browser — type your question.';
                    input.focus();
                    return;
                }
                var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                var rec = new SpeechRecognition();
                rec.lang = 'en-US';
                rec.interimResults = false;
                rec.maxAlternatives = 1;
                btn.classList.add('ai-voice-btn--active');
                rec.onresult = function (event) {
                    input.value = event.results[0][0].transcript;
                    btn.classList.remove('ai-voice-btn--active');
                };
                rec.onerror = rec.onend = function () {
                    btn.classList.remove('ai-voice-btn--active');
                };
                rec.start();
            });
        });
    }

    function registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw-ai.js', { scope: '/admin/ai-copilot' }).catch(function () {});
        }
    }

    function init() {
        initTheme();
        initVoicePlaceholder();
        initChat();
        registerServiceWorker();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', function () {
        initTheme();
        initVoicePlaceholder();
        initChat();
    });
})();
