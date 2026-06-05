{{-- Must run before/alongside Livewire boot — BODY_END scripts can miss livewire:init. --}}
<script data-cfasync="false">
(function () {
    function bind() {
        if (!window.Livewire?.hook) return false;
        window.Livewire.hook('request', function (_ref) {
            var fail = _ref.fail;
            fail(function (_ref2) {
                var status = _ref2.status, preventDefault = _ref2.preventDefault;
                if (status === 419) {
                    preventDefault();
                    window.location.reload();
                }
            });
        });
        return true;
    }
    document.addEventListener('livewire:init', bind);
    if (!bind()) {
        var t = setInterval(function () { if (bind()) clearInterval(t); }, 50);
        setTimeout(function () { clearInterval(t); }, 10000);
    }
})();
</script>
