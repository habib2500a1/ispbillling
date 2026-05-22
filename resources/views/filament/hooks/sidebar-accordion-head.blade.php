{{-- Run before sidebar HTML: reset stale localStorage that kept every group expanded. --}}
<script data-cfasync="false">
(function () {
    var versionKey = 'isp-sidebar-accordion-v7';
    if (localStorage.getItem(versionKey) === '7') {
        return;
    }
    localStorage.setItem(versionKey, '7');
    localStorage.removeItem('collapsedGroups');
    sessionStorage.removeItem('isp-sidebar-open-group');
})();
</script>
