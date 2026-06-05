<script data-cfasync="false">
(function () {
    var version = '6';
    var flagKey = 'isp-topbar-persist-version';

    try {
        if (localStorage.getItem(flagKey) === version) {
            return;
        }

        var keysToRemove = [];

        for (var i = 0; i < localStorage.length; i++) {
            var key = localStorage.key(i);

            if (! key) {
                continue;
            }

            if (
                key.indexOf('topbar.end.panel') !== -1
                || key.indexOf('fi-global-search') !== -1
                || key.indexOf('global-search') !== -1
            ) {
                keysToRemove.push(key);
            }
        }

        keysToRemove.forEach(function (key) {
            localStorage.removeItem(key);
        });

        localStorage.setItem(flagKey, version);
    } catch (e) {}
})();
</script>
