<style media="(max-width: 1023px)">
    .isp-mobile-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 55;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        padding: 0.5rem 0.65rem calc(0.55rem + env(safe-area-inset-bottom, 0px));
        background: #f8fafc;
        border-top: 1px solid rgba(148, 163, 184, 0.22);
    }
    .dark .isp-mobile-bar,
    [data-theme='dark'] .isp-mobile-bar {
        background: #0f172a;
        border-top-color: rgba(71, 85, 105, 0.45);
    }
    .isp-mobile-bar__search {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        min-height: 2.5rem;
        padding: 0.45rem 0.75rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 0.75rem;
        background: #fff;
        font-size: 0.8125rem;
        font-weight: 500;
        text-align: left;
        cursor: pointer;
        color: #0f172a;
    }
    .isp-mobile-bar__search-text {
        flex: 1;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>
