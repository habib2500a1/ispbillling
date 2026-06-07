<script data-cfasync="false">
(function () {
    function syncGlobalSearchInput(input) {
        if (!input || !window.Livewire) {
            return;
        }

        const root = input.closest('[wire\\:id]');

        if (!root) {
            return;
        }

        const component = window.Livewire.find(root.getAttribute('wire:id'));

        if (component) {
            component.set('search', input.value);
        }
    }

    document.addEventListener('input', function (event) {
        const input = event.target;

        if (!input?.matches?.('.fi-global-search-field input[type="search"]')) {
            return;
        }

        syncGlobalSearchInput(input);
    }, true);

    document.addEventListener('livewire:navigated', function () {
        window.setTimeout(function () {
            document.querySelectorAll('.fi-global-search-field input[type="search"]').forEach(function (input) {
                syncGlobalSearchInput(input);
            });
        }, 0);
    });
})();
</script>
