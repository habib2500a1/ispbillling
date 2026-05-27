import './bootstrap';

document.addEventListener('DOMContentLoaded', function() {
    function initMobileSidebarScroll() {
        if (window.matchMedia('(max-width: 1023px)').matches) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && 
                        (mutation.attributeName === 'class' || mutation.attributeName === 'style')) {
                        
                        const sidebar = document.querySelector('.fi-sidebar.fi-sidebar-open, .fi-sidebar-nav, .fi-sidebar-nav-groups');
                        if (sidebar) {
                            const navGroups = document.querySelector('.fi-sidebar-nav-groups');
                            if (navGroups) {
                                navGroups.style.overflowY = 'auto';
                                navGroups.style.webkitOverflowScrolling = 'touch';
                            }
                        }
                    }
                });
            });

            const sidebarContainer = document.body;
            if (sidebarContainer) {
                observer.observe(sidebarContainer, {
                    attributes: true,
                    attributeFilter: ['class', 'style']
                });
            }

            const navGroups = document.querySelector('.fi-sidebar-nav-groups');
            if (navGroups) {
                navGroups.style.overflowY = 'auto';
                navGroups.style.webkitOverflowScrolling = 'touch';
            }
        }
    }

    initMobileSidebarScroll();

    window.addEventListener('resize', initMobileSidebarScroll);

    document.addEventListener('click', function(e) {
        const menuButton = e.target.closest('[x-on\\:click*="$store.sidebar.open"], [x-on\\:click*="syncSidebarBodyClass"]');
        if (menuButton) {
            setTimeout(function() {
                const navGroups = document.querySelector('.fi-sidebar-nav-groups');
                if (navGroups) {
                    navGroups.style.overflowY = 'auto';
                    navGroups.style.webkitOverflowScrolling = 'touch';
                }
            }, 100);
        }
    });

    const originalOpen = window.$store?.sidebar?.open;
    if (originalOpen) {
        window.$store.sidebar.open = function() {
            originalOpen.apply(this, arguments);
            setTimeout(function() {
                const navGroups = document.querySelector('.fi-sidebar-nav-groups');
                if (navGroups) {
                    navGroups.style.overflowY = 'auto';
                    navGroups.style.webkitOverflowScrolling = 'touch';
                }
            }, 100);
        };
    }
});
