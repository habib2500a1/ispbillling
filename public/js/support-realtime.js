/**
 * Support desk live refresh — listens on tenant support channel when Pusher/Echo is configured.
 */
(function () {
    function refreshSupportViews() {
        if (typeof Livewire !== 'undefined') {
            Livewire.dispatch('support-desk-refresh');
        }
        document.querySelectorAll('[data-sh-hub]').forEach(function (el) {
            el.dispatchEvent(new CustomEvent('support:refresh', { bubbles: true }));
        });
    }

    function bindEcho() {
        if (typeof window.Echo === 'undefined' || typeof window.ISP_BROADCAST === 'undefined') {
            return;
        }

        var tenantId = window.ISP_BROADCAST.tenantId;
        if (!tenantId) {
            return;
        }

        window.Echo.channel('tenant.' + tenantId + '.support')
            .listen('.support.ticket.updated', function () {
                refreshSupportViews();
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEcho);
    } else {
        bindEcho();
    }
})();
