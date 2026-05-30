{{-- Run before sidebar HTML: reset stale localStorage that kept every group expanded. --}}
<script data-cfasync="false">
(function () {
    var versionKey = 'isp-sidebar-accordion-v9';
    if (localStorage.getItem(versionKey) === '9') {
        return;
    }
    localStorage.setItem(versionKey, '9');
    localStorage.removeItem('collapsedGroups');
    sessionStorage.removeItem('isp-sidebar-open-group');
})();

(function () {
    var KEY = 'collapsedGroups';
    var NON_COLLAPSIBLE = ['Overview'];

    try {
        var raw = localStorage.getItem(KEY);
        if (!raw) {
            return;
        }

        var groups = JSON.parse(raw);
        if (!Array.isArray(groups)) {
            localStorage.removeItem(KEY);
            return;
        }

        var cleaned = groups.filter(function (label) {
            return NON_COLLAPSIBLE.indexOf(label) === -1;
        });

        if (cleaned.length !== groups.length) {
            localStorage.setItem(KEY, JSON.stringify(cleaned));
        }
    } catch (e) {
        localStorage.removeItem(KEY);
    }
})();
</script>
