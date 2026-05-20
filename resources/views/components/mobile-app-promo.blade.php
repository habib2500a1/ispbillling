@php
    $downloadUrl = $downloadUrl ?? \App\Support\MobileAppLinks::downloadUrl();
    $variant = $variant ?? 'default';
@endphp

@if ($variant === 'compact')
    <div class="isp-mobile-app-promo isp-mobile-app-promo--compact {{ $class ?? '' }}">
        <div class="isp-mobile-app-promo__icon" aria-hidden="true">📱</div>
        <div class="isp-mobile-app-promo__body">
            <strong>RADIANT ISP Mobile App</strong>
            <span>Admin, staff &amp; client — এক অ্যাপে</span>
        </div>
        <a href="{{ $downloadUrl }}" class="isp-mobile-app-promo__btn">Download APK</a>
    </div>
@else
    <div class="isp-mobile-app-promo {{ $class ?? '' }}">
        <div>
            <strong class="isp-mobile-app-promo__title">📱 RADIANT ISP Mobile App</strong>
            <p class="isp-mobile-app-promo__text">Android app — বিল দেখুন, usage, টিকেট, collection (Admin / Staff / Client)</p>
        </div>
        <a href="{{ $downloadUrl }}" class="isp-mobile-app-promo__btn isp-mobile-app-promo__btn--primary">Download APK</a>
    </div>
@endif

<style>
    .isp-mobile-app-promo {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        border-radius: 0.85rem;
        border: 1px solid rgba(59, 130, 246, 0.35);
        background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%);
        margin-top: 1rem;
    }
    .isp-mobile-app-promo--compact {
        text-align: left;
    }
    .isp-mobile-app-promo__title { display: block; font-size: 0.95rem; font-weight: 700; color: #1e3a8a; }
    .isp-mobile-app-promo__text { margin: 0.25rem 0 0; font-size: 0.8rem; color: #475569; max-width: 22rem; }
    .isp-mobile-app-promo__icon { font-size: 1.5rem; }
    .isp-mobile-app-promo__body { flex: 1; min-width: 0; }
    .isp-mobile-app-promo__body strong { display: block; font-size: 0.9rem; color: #1e3a8a; }
    .isp-mobile-app-promo__body span { display: block; font-size: 0.75rem; color: #64748b; }
    .isp-mobile-app-promo__btn {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.8rem;
        font-weight: 700;
        text-decoration: none;
        background: #1d4ed8;
        color: #fff;
        white-space: nowrap;
    }
    .isp-mobile-app-promo__btn:hover { filter: brightness(1.08); color: #fff; }
    .isp-mobile-app-promo__btn--primary { padding: 0.55rem 1.1rem; }
    .dark .isp-mobile-app-promo {
        background: linear-gradient(135deg, #1e293b 0%, #172554 100%);
        border-color: #3b82f6;
    }
    .dark .isp-mobile-app-promo__title,
    .dark .isp-mobile-app-promo__body strong { color: #93c5fd; }
    .dark .isp-mobile-app-promo__text,
    .dark .isp-mobile-app-promo__body span { color: #94a3b8; }
</style>
